<?php

namespace CoderDen\FileDownloader;

class DownloadBuilder
{
    private FileDownloadManager $manager;
    private array $sources = [];
    private string $destination;
    private array $options = [];

    public function __construct(?FileDownloadManager $manager = null)
    {
        $this->manager = $manager ?? new FileDownloadManager();
    }

    public function from(string|array $sources): self
    {
        $this->sources = is_array($sources) ? $sources : [$sources];
        return $this;
    }

    public function to(string $destination): self
    {
        $this->destination = $destination;
        return $this;
    }

    public function withOptions(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function chunked(bool $chunked = true): self
    {
        $this->options['chunked_download'] = $chunked;
        return $this;
    }

    public function chunkSize(int $bytes): self
    {
        $this->options['chunk_size'] = $bytes;
        return $this;
    }

    public function maxSize(int $bytes): self
    {
        $this->options['max_file_size'] = $bytes;
        return $this;
    }

    public function download(?string $fileName = null): array
    {
        if (empty($this->sources)) {
            throw new \InvalidArgumentException("No sources specified");
        }

        if (!isset($this->destination)) {
            throw new \InvalidArgumentException("No destination specified");
        }

        return $this->manager->download(
            $this->sources,
            $this->destination,
            $fileName,
            $this->options
        );
    }
}