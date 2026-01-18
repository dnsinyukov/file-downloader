<?php

namespace CoderDen\FileDownloader;

use CoderDen\FileDownloader\Contracts\DownloaderInterface;
use CoderDen\FileDownloader\Contracts\ValidatorInterface;
use CoderDen\FileDownloader\Downloaders\HttpDownloader;
use CoderDen\FileDownloader\Exceptions\DownloadFailedException;
use CoderDen\FileDownloader\Strategies\ChunkedDownloadStrategy;

class FileDownloadManager
{
   private array $downloaders = [];
    private array $handlers = [];
    private array $validators = [];
    private ?ChunkedDownloadStrategy $chunkedStrategy = null;
    private array $options = [
        'timeout' => 30,
        'max_file_size' => 104857600, // 100MB
        'chunked_download' => false,
        'chunk_size' => 1048576, // 1MB
    ];

    public function __construct()
    {
        $this->registerDefaultDownloaders();
    }

    public function download(
        string|array $sources, 
        string $destinationDir, 
        array $options = []
    ): array
    {
        $this->options = array_merge($this->options, $options);
        
        $results = [];
        $sources = is_array($sources) ? $sources : [$sources];

        foreach ($sources as $source) {
            try {
                $downloader = $this->getDownloaderForSource($source);
                $downloader->setOptions($this->options);

                $fileName = $this->generateFileName($source);
                $destinationPath = rtrim($destinationDir, '/') . '/' . $fileName;

                if ($this->options['chunked_download']) {
                    $this->downloadWithChunks($source, $destinationPath, $downloader);
                } else {
                    $downloader->download($source, $destinationPath);
                }

                $this->validateFile($destinationPath);
                $destinationPath = $this->processFile($destinationPath);

                $results[] = [
                    'success' => true,
                    'source' => $source,
                    'destination' => $destinationPath,
                    'filename' => $fileName,
                ];
            } catch (\Exception $e) {
                $results[] = [
                    'success' => false,
                    'source' => $source,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function addDownloader(DownloaderInterface $downloader): void
    {
        $this->downloaders[] = $downloader;
    }


    public function addValidator(ValidatorInterface $validator): void
    {
        $this->validators[] = $validator;
    }

    private function getDownloaderForSource(string $source): DownloaderInterface
    {
        foreach ($this->downloaders as $downloader) {
            if ($downloader->supports($source)) {
                return $downloader;
            }
        }

        throw new DownloadFailedException("No downloader found for source: {$source}");
    }

    private function validateFile(string $filePath): void
    {
        foreach ($this->validators as $validator) {
            if (!$validator->validate($filePath)) {
                throw new \Exception($validator->getErrorMessage());
            }
        }
    }

    private function processFile(string $filePath): string
    {
        $mimeType = mime_content_type($filePath);

        foreach ($this->handlers as $handler) {
            if ($handler->supports($mimeType)) {
                return $handler->process($filePath);
            }
        }

        return $filePath;
    }

    private function generateFileName(string $source): string
    {
        $extension = pathinfo(parse_url($source, PHP_URL_PATH), PATHINFO_EXTENSION);
        return uniqid('download_', true) . ($extension ? '.' . $extension : '');
    }


    private function registerDefaultDownloaders(): void
    {
        $this->addDownloader(new HttpDownloader($this->options['http'] ?? []));
        $this->addDownloader(new Downloaders\FtpDownloader());
        $this->addDownloader(new Downloaders\LocalDownloader());
    }

    private function downloadWithChunks(
        string $source,
        string $destination,
        DownloaderInterface $downloader
    ): void {
        if ($downloader instanceof HttpDownloader && str_starts_with($source, 'http')) {
            if (!$this->chunkedStrategy) {
                $this->chunkedStrategy = new ChunkedDownloadStrategy($downloader->getClient());
            }

            $this->chunkedStrategy->setChunkSize($this->options['chunk_size']);
            $this->chunkedStrategy->downloadLargeFile(
                $source,
                $destination,
                function ($bytesDownloaded, $bytesTotal) {
                    // $this->fireProgressEvent($bytesDownloaded, $bytesTotal);
                },
                $this->options
            );
        } else {
            // Для других протоколов используем обычную загрузку
            $downloader->download($source, $destination);
        }
    }

    public function downloadAsync(array $sources, string $destinationDir): array
    {
        $httpDownloader = null;
        
        // Находим HTTP загрузчик
        foreach ($this->downloaders as $downloader) {
            if ($downloader instanceof HttpDownloader) {
                $httpDownloader = $downloader;
                break;
            }
        }

        if (!$httpDownloader) {
            throw new \RuntimeException('HTTP downloader not available for async downloads');
        }

        return $httpDownloader->downloadMultipleAsync($sources, $destinationDir);
    }

    public function getFileInfo(string $url): array
    {
        foreach ($this->downloaders as $downloader) {
            if ($downloader->supports($url) && method_exists($downloader, 'getFileInfo')) {
                return $downloader->getFileInfo($url);
            }
        }

        return ['exists' => false, 'error' => 'Unsupported protocol'];
    }

    public function getDownloaders() : array 
    {
        return $this->downloaders;   
    }
}