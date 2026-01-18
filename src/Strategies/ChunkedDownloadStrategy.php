<?php

namespace CoderDen\FileDownloader\Strategies;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use CoderDen\FileDownloader\Exceptions\DownloadFailedException;

class ChunkedDownloadStrategy
{
    private Client $client;
    private int $chunkSize = 1024 * 1024; // 1MB
    private int $maxRetries = 3;
    private int $concurrency = 3;

    public function __construct(?Client $client = null)
    {
        $this->client = $client ?? new Client([
            'timeout' => 30,
            'headers' => [
                'User-Agent' => 'FileDownloader/1.0 (Chunked)',
            ],
        ]);
    }

    public function downloadLargeFile(
        string $source,
        string $destination,
        ?callable $progressCallback = null,
        array $options = []
    ): bool
    {
        if (!str_starts_with($source, 'http')) {
            throw new \InvalidArgumentException('Chunked download only supports HTTP/HTTPS URLs');
        }

        $fileInfo = $this->getRemoteFileInfo($source);
        $fileSize = $fileInfo['size'] ?? 0;

        if ($fileSize <= 0) {
            return $this->downloadWholeFile($source, $destination, $progressCallback);
        }

        $tempFile = $destination . '.part';
        $fileHandle = fopen($tempFile, 'w+b');
        
        if (!$fileHandle) {
            throw new DownloadFailedException("Cannot create temporary file: {$tempFile}");
        }

        $chunks = $this->calculateChunks($fileSize);
        
        $this->downloadChunksParallel($source, $fileHandle, $chunks, $progressCallback, $fileSize);

        fclose($fileHandle);

        if (!rename($tempFile, $destination)) {
            throw new DownloadFailedException("Cannot rename temporary file to destination");
        }

        return true;
    }

    public function downloadWholeFile(
        string $source,
        string $destination,
        ?callable $progressCallback = null
    ): bool
    {
        try {
            $options = ['sink' => $destination];
            
            if ($progressCallback) {
                $options['progress'] = function ($downloadTotal, $downloadedBytes) use ($progressCallback) {
                    $progressCallback($downloadedBytes, $downloadTotal);
                };
            }

            $response = $this->client->get($source, $options);
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            throw new DownloadFailedException("Failed to download file: {$e->getMessage()}", 0, $e);
        }
    }

    private function getRemoteFileInfo(string $url): array
    {
        try {
            $response = $this->client->head($url, [
                'timeout' => 10,
            ]);

            $acceptsRanges = $response->getHeaderLine('Accept-Ranges') === 'bytes';
            $contentLength = (int) $response->getHeaderLine('Content-Length');

            return [
                'size' => $contentLength,
                'accepts_ranges' => $acceptsRanges,
                'mime_type' => $response->getHeaderLine('Content-Type'),
                'last_modified' => $response->getHeaderLine('Last-Modified'),
            ];
        } catch (RequestException $e) {
            try {
                $response = $this->client->get($url, [
                    'headers' => ['Range' => 'bytes=0-0'],
                    'timeout' => 10,
                ]);

                $contentRange = $response->getHeaderLine('Content-Range');
                if (preg_match('/bytes 0-0\/(\d+)/', $contentRange, $matches)) {
                    return [
                        'size' => (int) $matches[1],
                        'accepts_ranges' => true,
                    ];
                }
            } catch (RequestException $e) {
            }

            return ['size' => 0, 'accepts_ranges' => false];
        }
    }

    private function calculateChunks(int $fileSize): array
    {
        $chunks = [];
        $numChunks = ceil($fileSize / $this->chunkSize);

        for ($i = 0; $i < $numChunks; $i++) {
            $start = $i * $this->chunkSize;
            $end = min($start + $this->chunkSize - 1, $fileSize - 1);
            
            $chunks[] = [
                'index' => $i,
                'start' => $start,
                'end' => $end,
                'size' => $end - $start + 1,
            ];
        }

        return $chunks;
    }

    private function downloadChunksParallel(
        string $source,
        $fileHandle,
        array $chunks,
        ?callable $progressCallback = null,
        int $totalSize
    ): void {
        $promises = [];
        $downloadedBytes = 0;

        foreach ($chunks as $chunk) {
            $promises[] = $this->client->getAsync($source, [
                'headers' => [
                    'Range' => sprintf('bytes=%d-%d', $chunk['start'], $chunk['end']),
                ],
            ])->then(
                function ($response) use ($fileHandle, $chunk, &$downloadedBytes, $progressCallback, $totalSize) {
                    fseek($fileHandle, $chunk['start']);
                    fwrite($fileHandle, (string) $response->getBody());
                    
                    $downloadedBytes += $chunk['size'];
                    if ($progressCallback) {
                        $progressCallback($downloadedBytes, $totalSize);
                    }

                    return $chunk['index'];
                },
                function ($exception) use ($chunk) {
                    throw new \RuntimeException(
                        sprintf('Failed to download chunk %d-%d: %s',
                            $chunk['start'],
                            $chunk['end'],
                            $exception->getMessage()
                        )
                    );
                }
            );
        }

        $pool = new \GuzzleHttp\Pool($this->client, $promises, [
            'concurrency' => $this->concurrency,
            'fulfilled' => function ($result, $index) {
            },
            'rejected' => function ($reason, $index) {
                throw new DownloadFailedException("Chunk download failed: {$reason->getMessage()}", 0, $reason);
            },
        ]);

        $pool->promise()->wait();
    }

    public function setChunkSize(int $bytes): void
    {
        $this->chunkSize = $bytes;
    }

    public function setConcurrency(int $concurrency): void
    {
        $this->concurrency = $concurrency;
    }

    public function setMaxRetries(int $retries): void
    {
        $this->maxRetries = $retries;
    }

    public function getClient(): Client
    {
        return $this->client;
    }
}