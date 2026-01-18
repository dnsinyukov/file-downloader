<?php

namespace CoderDen\FileDownloader\Downloaders;

use CoderDen\FileDownloader\Contracts\DownloaderInterface;
use CoderDen\FileDownloader\Exceptions\DownloadFailedException;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Promise\Utils;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\RequestInterface;

class HttpDownloader implements DownloaderInterface
{
    private Client $client;
    private array $options = [
        'timeout' => 30,
        'connect_timeout' => 10,
        'verify' => true,
        'http_errors' => true,
        'allow_redirects' => [
            'max' => 5,
            'strict' => true,
            'referer' => true,
            'protocols' => ['http', 'https'],
            'track_redirects' => true,
        ],
        'headers' => [
            'User-Agent' => 'FileDownloader/1.0',
        ],
        'progress' => null,
    ];

    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
        $this->initializeClient();
    }

    private function initializeClient(): void
    {
        $handlerStack = HandlerStack::create();
        
        $handlerStack->push(Middleware::retry(
            function (
                $retries,
                RequestInterface $request,
                ?ResponseInterface $response = null,
                ?RequestException $exception = null
            ) {
                if ($retries >= 3) {
                    return false;
                }

                if ($exception instanceof RequestException) {
                    return true;
                }

                if ($response && $response->getStatusCode() >= 500) {
                    return true;
                }

                return false;
            },
            function ($retries) {
                return 1000 * pow(2, $retries);
            }
        ));

        $this->client = new Client([
            'handler' => $handlerStack,
            'timeout' => $this->options['timeout'],
            'connect_timeout' => $this->options['connect_timeout'],
            'verify' => $this->options['verify'],
            'http_errors' => $this->options['http_errors'],
            'allow_redirects' => $this->options['allow_redirects'],
            'headers' => $this->options['headers'],
        ]);
    }

    public function download(string $source, string $destination): bool
    {
        try {
            $options = [
                'sink' => $destination,
            ];

            if ($this->options['progress'] && is_callable($this->options['progress'])) {
                $options['progress'] = $this->options['progress'];
            }

            $response = $this->client->get($source, $options);

            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            $message = $this->getErrorMessage($e);
            throw new DownloadFailedException("HTTP download failed: {$message}", 0, $e);
        } catch (\Exception $e) {
            throw new DownloadFailedException("HTTP download failed: {$e->getMessage()}", 0, $e);
        }
    }

    public function downloadWithProgress(string $source, string $destination, callable $progressCallback): bool
    {
        $options = [
            'sink' => $destination,
            'progress' => function ($downloadTotal, $downloadedBytes) use ($progressCallback) {
                $progressCallback($downloadedBytes, $downloadTotal);
            },
        ];

        try {
            $response = $this->client->get($source, $options);
            return $response->getStatusCode() === 200;
        } catch (RequestException $e) {
            $message = $this->getErrorMessage($e);
            throw new DownloadFailedException("HTTP download failed: {$message}", 0, $e);
        }
    }

    public function downloadAsync(string $source, string $destination): \GuzzleHttp\Promise\PromiseInterface
    {
        return $this->client->getAsync($source, ['sink' => $destination]);
    }

    public function downloadMultipleAsync(array $urls, string $destinationDir): array
    {
        $promises = [];
        $results = [];

        foreach ($urls as $key => $url) {
            $fileName = $this->generateFileName($url);
            $destination = rtrim($destinationDir, '/') . '/' . $fileName;
            
            $promises[$key] = $this->client->getAsync($url, [
                'sink' => $destination,
            ])->then(
                function (ResponseInterface $response) use ($key, $url, $destination) {
                    return [
                        'key' => $key,
                        'success' => true,
                        'source' => $url,
                        'destination' => $destination,
                        'status_code' => $response->getStatusCode(),
                    ];
                },
                function (RequestException $e) use ($key, $url) {
                    return [
                        'key' => $key,
                        'success' => false,
                        'source' => $url,
                        'error' => $this->getErrorMessage($e),
                        'status_code' => $e->hasResponse() ? $e->getResponse()->getStatusCode() : 0,
                    ];
                }
            );
        }

        $results = Utils::settle($promises)->wait();

        return array_map(function ($result) {
            return $result['value'];
        }, $results);
    }

    public function getFileInfo(string $url): array
    {
        try {
            $response = $this->client->head($url);
            
            return [
                'exists' => true,
                'size' => (int) $response->getHeaderLine('Content-Length'),
                'mime_type' => $response->getHeaderLine('Content-Type'),
                'last_modified' => $response->getHeaderLine('Last-Modified'),
                'etag' => $response->getHeaderLine('ETag'),
                'headers' => $response->getHeaders(),
            ];
        } catch (RequestException $e) {
            return [
                'exists' => false,
                'error' => $this->getErrorMessage($e),
            ];
        }
    }

    public function supports(string $protocol): bool
    {
        return str_starts_with($protocol, 'http://') || 
               str_starts_with($protocol, 'https://');
    }

    public function setOptions(array $options): void
    {
        $this->options = array_merge($this->options, $options);
        $this->initializeClient();
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    private function getErrorMessage(RequestException $e): string
    {
        if ($e->hasResponse()) {
            $response = $e->getResponse();
            return sprintf(
                'HTTP %d: %s',
                $response->getStatusCode(),
                $response->getReasonPhrase()
            );
        }

        return $e->getMessage();
    }

    private function generateFileName(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        
        if (empty($extension)) {
            try {
                $info = $this->getFileInfo($url);
                if ($info['exists'] && isset($info['mime_type'])) {
                    $extension = $this->mimeToExtension($info['mime_type']);
                }
            } catch (\Exception $e) {
            }
        }
        
        return uniqid('download_', true) . ($extension ? '.' . $extension : '');
    }

    private function mimeToExtension(string $mimeType): string
    {
        $mimeMap = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'application/pdf' => 'pdf',
            'application/zip' => 'zip',
            'application/x-rar-compressed' => 'rar',
            'text/plain' => 'txt',
            'text/html' => 'html',
            'application/json' => 'json',
        ];

        return $mimeMap[$mimeType] ?? '';
    }
}