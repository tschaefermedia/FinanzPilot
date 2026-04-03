<?php

namespace App\Services\Import;

interface ParserInterface
{
    /**
     * Parse a file and return an array of ParsedTransaction objects.
     *
     * @param string $filePath Absolute path to the uploaded file
     * @return ParsedTransaction[]
     */
    public function parse(string $filePath): array;

    /**
     * Check if this parser can handle the given file.
     */
    public function canHandle(string $filePath): bool;

    /**
     * Get the source type identifier.
     */
    public function getSourceType(): string;
}
