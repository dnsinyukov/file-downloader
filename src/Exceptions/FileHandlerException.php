<?php

namespace CoderDen\FileDownloader\Exceptions;

class FileHandlerException extends \RuntimeException
{
    private ?string $filePath;
    private ?string $handlerClass;

    public function __construct(
        string $message = "File handler error",
        ?string $filePath = null,
        ?string $handlerClass = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->filePath = $filePath;
        $this->handlerClass = $handlerClass;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function getHandlerClass(): ?string
    {
        return $this->handlerClass;
    }
}