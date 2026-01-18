<?php

namespace CoderDen\FileDownloader\Validators;

use CoderDen\FileDownloader\Contracts\ValidatorInterface;

class ExtensionValidator implements ValidatorInterface
{
    private array $allowedExtensions;
    private array $blockedExtensions;
    private ?string $errorMessage = null;

    public function __construct(array $allowedExtensions = [], array $blockedExtensions = [])
    {
        $this->allowedExtensions = array_map('strtolower', $allowedExtensions);
        $this->blockedExtensions = array_map('strtolower', $blockedExtensions);
    }

    public function validate(string $filePath): bool
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        // Check blocked extensions first
        if (!empty($this->blockedExtensions) && in_array($extension, $this->blockedExtensions)) {
            $this->errorMessage = "File extension '{$extension}' is not allowed";
            return false;
        }

        // Check allowed extensions if specified
        if (!empty($this->allowedExtensions) && !in_array($extension, $this->allowedExtensions)) {
            $this->errorMessage = sprintf(
                "File extension '%s' is not allowed. Allowed extensions: %s",
                $extension,
                implode(', ', $this->allowedExtensions)
            );
            return false;
        }

        return true;
    }

    public function getErrorMessage(): string
    {
        return $this->errorMessage ?? 'Validation failed';
    }
}