<?php

namespace CoderDen\FileDownloader;

use FileDownloadHelper;

/**
 * Facade for quick file downloads
 * 
 * @method static array download(string $url, string|null $destination = null)
 * @method static array downloadMultiple(array $urls, string|null $destination = null)
 * @method static array downloadImage(string $imageUrl, string|null $destination = null)
 * @method static array downloadLargeFile(string $url, string $destination, int $chunkSize = 1048576)
 * @method static string quickDownload(string $url, string|null $saveAs = null)
 * @method static bool isDownloadable(string $url)
 * @method static int|null getRemoteFileSize(string $url)
 */
class FileDownloaderFacade
{
    public static function __callStatic(string $method, array $arguments)
    {
        return FileDownloadHelper::$method(...$arguments);
    }

    /**
     * Alias for quick download
     */
    public static function get(string $url, ?string $saveAs = null): string
    {
        return FileDownloadHelper::quickDownload($url, $saveAs);
    }

    /**
     * Alias for download multiple
     */
    public static function getAll(array $urls, ?string $destination = null): array
    {
        return FileDownloadHelper::downloadMultiple($urls, $destination);
    }

    /**
     * Alias for download with retry
     */
    public static function fetch(string $url, ?string $destination = null, int $retries = 3): array
    {
        return FileDownloadHelper::downloadWithRetry($url, $destination, $retries);
    }
}