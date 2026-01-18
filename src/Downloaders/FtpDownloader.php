<?php

namespace CoderDen\FileDownloader\Downloaders;

use CoderDen\FileDownloader\Contracts\DownloaderInterface;
use CoderDen\FileDownloader\Exceptions\DownloadFailedException;

class FtpDownloader implements DownloaderInterface
{
    private array $options = [
        'port' => 21,
        'timeout' => 90,
        'passive' => true,
        'username' => null,
        'password' => null,
    ];

    public function download(string $source, string $destination): bool
    {
        $parsedUrl = parse_url($source);
        
        $connection = ftp_connect(
            $parsedUrl['host'],
            $this->options['port'],
            $this->options['timeout']
        );

        if (!$connection) {
            throw new DownloadFailedException("FTP connection failed");
        }

        if (!ftp_login($connection, $this->options['username'], $this->options['password'])) {
            throw new DownloadFailedException("FTP authentication failed");
        }

        ftp_pasv($connection, $this->options['passive']);

        $remotePath = $parsedUrl['path'] ?? '/';
        $localFile = fopen($destination, 'wb');

        if (!ftp_fget($connection, $localFile, $remotePath, FTP_BINARY)) {
            fclose($localFile);
            ftp_close($connection);
            throw new DownloadFailedException("Failed to download file from FTP");
        }

        fclose($localFile);
        ftp_close($connection);

        return true;
    }

    public function supports(string $protocol): bool
    {
        return str_starts_with($protocol, 'ftp://');
    }

    public function setOptions(array $options): void
    {
        $this->options = array_merge($this->options, $options);
    }
}