<?php

namespace CoderDen\FileDownloader\Validators;

use CoderDen\FileDownloader\Contracts\ValidatorInterface;

class SizeValidator implements ValidatorInterface
{
    private ?int $maxSize;
    private ?int $minSize;
    private ?string $errorMessage = null;

    public function __construct(?int $maxSize = null, ?int $minSize = null)
    {
        $this->maxSize = $maxSize;
        $this->minSize = $minSize;
    }

    public function validate(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            $this->errorMessage = "File does not exist: {$filePath}";
            return false;
        }

        $fileSize = filesize($filePath);

        if ($this->minSize !== null && $fileSize < $this->minSize) {
            $this->errorMessage = sprintf(
                "File size (%s) is less than minimum required size (%s)",
                $this->formatBytes($fileSize),
                $this->formatBytes($this->minSize)
            );
            return false;
        }

        if ($this->maxSize !== null && $fileSize > $this->maxSize) {
            $this->errorMessage = sprintf(
                "File size (%s) exceeds maximum allowed size (%s)",
                $this->formatBytes($fileSize),
                $this->formatBytes($this->maxSize)
            );
            return false;
        }

        return true;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage ?? 'Validation failed';
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        
        $bytes /= pow(1024, $pow);
        
        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}