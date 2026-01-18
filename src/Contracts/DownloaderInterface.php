<?php

namespace CoderDen\FileDownloader\Contracts;

interface DownloaderInterface
{
    public function download(string $source, string $destination): bool;
    public function supports(string $protocol): bool;
    public function setOptions(array $options): void;
}