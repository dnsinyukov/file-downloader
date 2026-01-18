<?php

namespace CoderDen\FileDownloader\Validators;

use CoderDen\FileDownloader\Contracts\ValidatorInterface;

class MimeTypeValidator implements ValidatorInterface
{
    private array $allowedMimeTypes;
    private array $blockedMimeTypes;
    private ?string $errorMessage = null;

    public function __construct(array $allowedMimeTypes = [], array $blockedMimeTypes = [])
    {
        $this->allowedMimeTypes = $allowedMimeTypes;
        $this->blockedMimeTypes = $blockedMimeTypes;
    }

    public function validate(string $filePath): bool
    {
        if (!file_exists($filePath)) {
            $this->errorMessage = "File does not exist: {$filePath}";
            return false;
        }

        $mimeType = $this->detectMimeType($filePath);

        // Check blocked MIME types first
        if (!empty($this->blockedMimeTypes) && in_array($mimeType, $this->blockedMimeTypes)) {
            $this->errorMessage = "MIME type '{$mimeType}' is not allowed";
            return false;
        }

        // Check allowed MIME types if specified
        if (!empty($this->allowedMimeTypes) && !in_array($mimeType, $this->allowedMimeTypes)) {
            $this->errorMessage = sprintf(
                "MIME type '%s' is not allowed. Allowed MIME types: %s",
                $mimeType,
                implode(', ', $this->allowedMimeTypes)
            );
            return false;
        }

        return true;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage ?? 'Validation failed';
    }

    private function detectMimeType(string $filePath): string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filePath);
            finfo_close($finfo);
            return $mimeType;
        }

        if (function_exists('mime_content_type')) {
            return mime_content_type($filePath);
        }

        // Fallback to file extension detection
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
        $mimeMap = [
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'pdf' => 'application/pdf',
            'zip' => 'application/zip',
            'txt' => 'text/plain',
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
        ];

        return $mimeMap[$extension] ?? 'application/octet-stream';
    }
}