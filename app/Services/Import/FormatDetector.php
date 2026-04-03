<?php

namespace App\Services\Import;

class FormatDetector
{
    private array $parsers;

    public function __construct()
    {
        $this->parsers = [
            new SparkasseCamtV8Parser(),
            new PayPalCsvParser(),
        ];
    }

    /**
     * Detect the format of an uploaded file and return the appropriate parser.
     * Returns null if no known format is detected (user should use generic CSV).
     */
    public function detect(string $filePath): ?ParserInterface
    {
        foreach ($this->parsers as $parser) {
            if ($parser->canHandle($filePath)) {
                return $parser;
            }
        }

        return null;
    }

    /**
     * Detect the delimiter of a CSV file by checking the first few lines.
     */
    public static function detectDelimiter(string $filePath): string
    {
        $content = file_get_contents($filePath, false, null, 0, 4096);
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);

        $firstLine = strtok($content, "\n");

        $delimiters = [';' => 0, ',' => 0, "\t" => 0, '|' => 0];

        foreach (array_keys($delimiters) as $d) {
            $delimiters[$d] = substr_count($firstLine, $d);
        }

        arsort($delimiters);
        return array_key_first($delimiters);
    }
}
