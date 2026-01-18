<?php

namespace CoderDen\FileDownloader\Exceptions;

class ValidationFailedException extends \RuntimeException
{
    private ?string $filePath;
    private ?string $validatorClass;

    public function __construct(
        string $message = "File validation failed",
        ?string $filePath = null,
        ?string $validatorClass = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->filePath = $filePath;
        $this->validatorClass = $validatorClass;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function getValidatorClass(): ?string
    {
        return $this->validatorClass;
    }
}