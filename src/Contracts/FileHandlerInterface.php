<?php

namespace CoderDen\FileDownloader\Contracts;

interface FileHandlerInterface
{
    public function process(string $filePath): string;
    public function supports(string $mimeType): bool;
}