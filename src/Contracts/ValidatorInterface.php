<?php

namespace CoderDen\FileDownloader\Contracts;

interface ValidatorInterface
{
    public function validate(string $filePath): bool;
    public function getErrorMessage(): string;
}