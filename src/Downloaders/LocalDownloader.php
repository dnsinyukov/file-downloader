<?php

namespace CoderDen\FileDownloader\Downloaders;

use CoderDen\FileDownloader\Contracts\DownloaderInterface;
use CoderDen\FileDownloader\Exceptions\DownloadFailedException;

class LocalDownloader implements DownloaderInterface
{
    private array $options = [];

    public function download(string $source, string $destination): bool
    {
        $localPath = str_replace('file://', '', $source);
        
        if (!file_exists($localPath)) {
            throw new DownloadFailedException("Local file does not exist: {$localPath}");
        }

        if (!copy($localPath, $destination)) {
            throw new DownloadFailedException(
                "Failed to copy file from {$localPath} to {$destination}"
            );
        }

        return true;
    }

    public function supports(string $protocol): bool
    {
        return str_starts_with($protocol, 'file://') || 
               !preg_match('/^[a-zA-Z]+:\/\//', $protocol);
    }

    public function setOptions(array $options): void
    {
        $this->options = $options;
    }
}