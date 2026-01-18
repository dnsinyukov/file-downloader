<?php

use CoderDen\FileDownloader\DownloadBuilder;
use CoderDen\FileDownloader\FileDownloadManager;

class FileDownloadHelper
{
    private static ?FileDownloadManager $manager = null;
    private static array $config = [];

    /**
     * Initialize helper with configuration
     */
    public static function init(array $config = []): void
    {
        self::$config = array_merge([
            'default_destination' => sys_get_temp_dir(),
            'chunked_download' => false,
            'timeout' => 30,
            'max_retries' => 3,
        ], $config);
    }

    /**
     * Configure manager with default settings
     */
    private static function configureManager(FileDownloadManager $manager): void
    {
    }

    /**
     * Get or create manager instance
     */
    private static function getManager(): FileDownloadManager
    {
        if (self::$manager === null) {
            self::$manager = new FileDownloadManager();
            self::configureManager(self::$manager);
        }

        return self::$manager;
    }

    /**
     * Quick download single file
     */
    public static function download(string $url, ?string $destination = null): array
    {
        $destination = $destination ?: self::$config['default_destination'];
        
        return (new DownloadBuilder(self::getManager()))
            ->from($url)
            ->to($destination)
            ->withOptions(self::$config)
            ->download();
    }

    /**
     * Download multiple files
     */
    public static function downloadMultiple(array $urls, ?string $destination = null): array
    {
        $destination = $destination ?: self::$config['default_destination'];
        
        return (new DownloadBuilder(self::getManager()))
            ->from($urls)
            ->to($destination)
            ->withOptions(self::$config)
            ->download();
    }

     /**
     * Download with retry logic
     */
    public static function downloadWithRetry(
        string $url, 
        ?string $destination = null, 
        ?int $maxRetries = null
    ): array {
        $maxRetries = $maxRetries ?: self::$config['max_retries'];
        $attempt = 0;
        
        while ($attempt < $maxRetries) {
            try {
                return self::download($url, $destination);
            } catch (\Exception $e) {
                $attempt++;
                if ($attempt === $maxRetries) {
                    throw $e;
                }
                sleep(1); // Wait before retry
            }
        }
        
        throw new \RuntimeException("Failed to download after {$maxRetries} attempts");
    }

    /**
     * Download large file with chunking
     */
    public static function downloadLargeFile(
        string $url, 
        string $destination,
        int $chunkSize = 1048576
    ): array {
        return (new DownloadBuilder(self::getManager()))
            ->from($url)
            ->to($destination)
            ->chunked(true)
            ->chunkSize($chunkSize)
            ->download();
    }

    /**
     * Download via FTP
     */
    public static function downloadViaFtp(
        string $ftpUrl,
        string $destination,
        array $ftpOptions = []
    ): array {
        $options = array_merge(self::$config, [
            'ftp_username' => $ftpOptions['username'] ?? null,
            'ftp_password' => $ftpOptions['password'] ?? null,
            'ftp_port' => $ftpOptions['port'] ?? 21,
        ]);
        
        return (new DownloadBuilder(self::getManager()))
            ->from($ftpUrl)
            ->to($destination)
            ->withOptions($options)
            ->download();
    }

    /**
     * Download image and get image info
     */
    public static function downloadImage(
        string $imageUrl, 
        ?string $destination = null
    ): array {
        $result = self::download($imageUrl, $destination);
        
        if ($result[0]['success'] ?? false) {
            $filePath = $result[0]['destination'];
            
            if (function_exists('getimagesize')) {
                $imageInfo = getimagesize($filePath);
                $result[0]['image_info'] = [
                    'width' => $imageInfo[0] ?? null,
                    'height' => $imageInfo[1] ?? null,
                    'mime' => $imageInfo['mime'] ?? null,
                ];
            }
        }
        
        return $result;
    }

    /**
     * Get download progress callback
     */
    public static function createProgressCallback(?callable $progressHandler = null): callable {
        return function (int $bytesDownloaded, ?int $bytesTotal = null) use ($progressHandler) {
            if ($progressHandler) {
                $progressHandler($bytesDownloaded, $bytesTotal);
            }
        };
    }

     /**
     * Set custom manager
     */
    public static function setManager(FileDownloadManager $manager): void
    {
        self::$manager = $manager;
    }

    /**
     * Reset manager to default
     */
    public static function reset(): void
    {
        self::$manager = null;
        self::$config = [];
    }

    /**
     * Check if URL is downloadable
     */
    public static function isDownloadable(string $url): bool
    {
        try {
            $manager = self::getManager();
            
            // Проверяем, есть ли подходящий downloader
            foreach (self::getManager()->getDownloaders() as $downloader) {
                if ($downloader->supports($url)) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            return false;
        }
        
        return false;
    }

    /**
     * Get file size before download
     */
    public static function getRemoteFileSize(string $url): ?int
    {
        if (str_starts_with($url, 'http')) {
            $headers = get_headers($url, 1);
            if (isset($headers['Content-Length'])) {
                return (int) $headers['Content-Length'];
            }
        }
        
        return null;
    }

    /**
     * Download with custom headers
     */
    public static function downloadWithHeaders(
        string $url,
        string $destination,
        array $headers
    ): array {
        $options = array_merge(self::$config, [
            'headers' => $headers,
        ]);
        
        return (new DownloadBuilder(self::getManager()))
            ->from($url)
            ->to($destination)
            ->withOptions($options)
            ->download();
    }

    /**
     * Simple one-liner download
     */
    public static function quickDownload(
        string $url, 
        string $saveAs = null
    ): string {
        $saveAs = $saveAs ?: basename(parse_url($url, PHP_URL_PATH));
        $destination = rtrim(self::$config['default_destination'], '/') . '/' . $saveAs;
        
        $result = self::download($url, $destination);
        
        if (empty($result) || !($result[0]['success'] ?? false)) {
            throw new \RuntimeException('Failed to download file');
        }
        
        return $result[0]['destination'];
    }

    /**
     * Batch download with concurrency limit
     */
    public static function batchDownload(
        array $urls, 
        string $destinationDir,
        int $concurrency = 3
    ): array {
        $results = [];
        $chunks = array_chunk($urls, $concurrency);
        
        foreach ($chunks as $chunk) {
            $promises = [];
            
            foreach ($chunk as $url) {
                $results[] = self::download($url, $destinationDir);
            }
        }
        
        return $results;
    }

    /**
     * Async download multiple files
     */
    public static function downloadAsync(array $urls, string $destination): array
    {
        $manager = self::getManager();
        
        if (method_exists($manager, 'downloadAsync')) {
            return $manager->downloadAsync($urls, $destination);
        }

        throw new \RuntimeException('Async download not supported');
    }

    /**
     * Get remote file info using Guzzle
     */
    public static function getRemoteFileInfo(string $url): array
    {
        $manager = self::getManager();
        
        if (method_exists($manager, 'getFileInfo')) {
            return $manager->getFileInfo($url);
        }

        return ['exists' => false];
    }

    /**
     * Download with Guzzle Pool (parallel downloads)
     */
    public static function downloadParallel(array $urls, string $destinationDir, int $concurrency = 5): array
    {
        $manager = self::getManager();
        $httpDownloader = null;

        // Get HTTP downloader
        foreach (self::getDownloaders($manager) as $downloader) {
            if ($downloader instanceof \CoderDen\FileDownloader\Downloaders\HttpDownloader) {
                $httpDownloader = $downloader;
                break;
            }
        }

        if (!$httpDownloader) {
            throw new \RuntimeException('HTTP downloader not available for parallel downloads');
        }

        $client = $httpDownloader->getClient();
        $promises = [];
        $results = [];

        foreach ($urls as $index => $url) {
            $fileName = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_BASENAME) ?: 'file_' . $index;
            $destination = rtrim($destinationDir, '/') . '/' . $fileName;

            $promises[$index] = $client->getAsync($url, [
                'sink' => $destination,
                'timeout' => self::$config['timeout'] ?? 30,
            ])->then(
                function ($response) use ($index, $url, $destination) {
                    return [
                        'success' => true,
                        'index' => $index,
                        'url' => $url,
                        'destination' => $destination,
                        'size' => filesize($destination),
                        'status' => $response->getStatusCode(),
                    ];
                },
                function ($exception) use ($index, $url) {
                    return [
                        'success' => false,
                        'index' => $index,
                        'url' => $url,
                        'error' => $exception->getMessage(),
                    ];
                }
            );
        }

        // Create pool with concurrency control
        $pool = new \GuzzleHttp\Pool($client, $promises, [
            'concurrency' => $concurrency,
            'fulfilled' => function ($result, $index) use (&$results) {
                $results[] = $result;
            },
            'rejected' => function ($reason, $index) use (&$results) {
                $results[] = [
                    'success' => false,
                    'index' => $index,
                    'error' => $reason->getMessage(),
                ];
            },
        ]);

        $pool->promise()->wait();
        return $results;
    }

    /**
     * Download with rate limiting
     */
    public static function downloadWithRateLimit(
        array $urls,
        string $destinationDir,
        int $requestsPerSecond = 2
    ): array {
        $results = [];
        $delay = 1 / $requestsPerSecond;
        
        foreach ($urls as $index => $url) {
            $result = self::download($url, $destinationDir);
            $results[] = $result;
            
            if ($index < count($urls) - 1) {
                usleep($delay * 1000000);
            }
        }
        
        return $results;
    }

    /**
     * Download with custom Guzzle client
     */
    public static function downloadWithClient(
        string $url,
        string $destination,
        \GuzzleHttp\Client $client
    ): array {
        try {
            $response = $client->get($url, ['sink' => $destination]);
            
            return [
                'success' => true,
                'destination' => $destination,
                'size' => filesize($destination),
                'status' => $response->getStatusCode(),
                'headers' => $response->getHeaders(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    private static function getDownloaders($manager): array
    {
        $reflection = new \ReflectionClass($manager);
        $property = $reflection->getProperty('downloaders');
        $property->setAccessible(true);
        
        return $property->getValue($manager);
    }
}