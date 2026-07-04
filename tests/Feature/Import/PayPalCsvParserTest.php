<?php

namespace Tests\Feature\Import;

use App\Services\Import\PayPalCsvParser;
use Tests\TestCase;

class PayPalCsvParserTest extends TestCase
{
    private function fixture(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'pp').'.csv';
        file_put_contents($path, $content);

        return $path;
    }

    private function csv(): string
    {
        return implode("\n", [
            '"Datum","Name","Typ","Währung","Brutto","Betreff","Transaktionscode"',
            '"01.02.2026","Spotify","Abbuchung","EUR","-9,99","Premium","TXN1"',
            '"02.02.2026","Foo Inc","Zahlung","USD","-10,00","Ausland","TXN2"',
            '"03.02.2026","Boss","Zahlung","EUR","1,234.56","Gehalt","TXN3"',
        ]);
    }

    public function test_parses_eur_rows_and_skips_foreign_currency(): void
    {
        $path = $this->fixture($this->csv());
        $result = (new PayPalCsvParser)->parse($path);
        unlink($path);

        // USD row is skipped
        $this->assertCount(2, $result);

        $this->assertSame('2026-02-01', $result[0]->date);
        $this->assertSame(-9.99, $result[0]->amount);
        $this->assertSame('Spotify', $result[0]->counterparty);
        $this->assertSame('Premium', $result[0]->description);
    }

    public function test_parses_english_number_format(): void
    {
        $path = $this->fixture($this->csv());
        $result = (new PayPalCsvParser)->parse($path);
        unlink($path);

        // "1,234.56" (English thousands separator) → 1234.56
        $this->assertSame(1234.56, $result[1]->amount);
        $this->assertSame('Boss', $result[1]->counterparty);
    }

    public function test_can_handle_detects_paypal_header(): void
    {
        $parser = new PayPalCsvParser;

        $pp = $this->fixture($this->csv());
        $other = $this->fixture("Date,Amount\n2026-01-01,10");

        $this->assertTrue($parser->canHandle($pp));
        $this->assertFalse($parser->canHandle($other));

        unlink($pp);
        unlink($other);
    }
}
