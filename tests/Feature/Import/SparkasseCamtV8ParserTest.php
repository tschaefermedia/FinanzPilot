<?php

namespace Tests\Feature\Import;

use App\Services\Import\SparkasseCamtV8Parser;
use Tests\TestCase;

class SparkasseCamtV8ParserTest extends TestCase
{
    private function fixture(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'spk').'.csv';
        file_put_contents($path, $content);

        return $path;
    }

    private function csv(): string
    {
        return implode("\n", [
            'Auftragskonto;Buchungstag;Betrag;Verwendungszweck;Beguenstigter/Zahlungspflichtiger;Kundenreferenz (End-to-End);Buchungstext',
            'DE123;01.02.2026;-56,78;Netflix Abo;Netflix;NOTPROVIDED;LASTSCHRIFT',
            'DE123;15.02.2026;1.234,56;Gehalt Februar;Arbeitgeber GmbH;REF123;GUTSCHRIFT',
        ]);
    }

    public function test_parses_german_dates_amounts_and_fields(): void
    {
        $path = $this->fixture($this->csv());
        $result = (new SparkasseCamtV8Parser)->parse($path);
        unlink($path);

        $this->assertCount(2, $result);

        $this->assertSame('2026-02-01', $result[0]->date);
        $this->assertSame(-56.78, $result[0]->amount);
        $this->assertSame('Netflix', $result[0]->counterparty);

        $this->assertSame('2026-02-15', $result[1]->date);
        $this->assertSame(1234.56, $result[1]->amount);
        $this->assertSame('Arbeitgeber GmbH', $result[1]->counterparty);
        $this->assertSame('REF123', $result[1]->reference);
    }

    public function test_notprovided_reference_falls_back_to_description(): void
    {
        $path = $this->fixture($this->csv());
        $result = (new SparkasseCamtV8Parser)->parse($path);
        unlink($path);

        $this->assertSame('Netflix Abo', $result[0]->reference);
    }

    public function test_two_digit_year_is_expanded(): void
    {
        $path = $this->fixture(implode("\n", [
            'Auftragskonto;Buchungstag;Betrag;Verwendungszweck;Beguenstigter/Zahlungspflichtiger;Kundenreferenz (End-to-End);Buchungstext',
            'DE123;01.02.26;-10,00;Test;Foo;REF;X',
        ]));
        $result = (new SparkasseCamtV8Parser)->parse($path);
        unlink($path);

        $this->assertSame('2026-02-01', $result[0]->date);
    }

    public function test_can_handle_detects_sparkasse_header(): void
    {
        $parser = new SparkasseCamtV8Parser;

        $spk = $this->fixture($this->csv());
        $other = $this->fixture("Date,Amount,Note\n2026-01-01,10,x");

        $this->assertTrue($parser->canHandle($spk));
        $this->assertFalse($parser->canHandle($other));

        unlink($spk);
        unlink($other);
    }
}
