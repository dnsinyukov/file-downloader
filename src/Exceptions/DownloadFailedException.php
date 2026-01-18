<?php

namespace CoderDen\FileDownloader\Exceptions;

class DownloadFailedException extends \RuntimeException
{
    private ?string $source;
    private ?string $destination;

    public function __construct(
        string $message = "File download failed",
        ?string $source = null,
        ?string $destination = null,
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->source = $source;
        $this->destination = $destination;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getDestination(): ?string
    {
        return $this->destination;
    }
}