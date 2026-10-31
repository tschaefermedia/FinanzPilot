<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneImportUploads extends Command
{
    protected $signature = 'imports:prune-uploads {--hours=24 : Delete uploads older than this many hours}';

    protected $description = 'Delete uploaded bank statement files from imports that were never committed';

    public function handle(): int
    {
        $cutoff = now()->subHours((int) $this->option('hours'))->getTimestamp();

        $staleFiles = collect(Storage::files('imports'))
            ->filter(fn (string $path) => Storage::lastModified($path) < $cutoff);

        Storage::delete($staleFiles->all());

        $this->info("{$staleFiles->count()} verwaiste Upload-Dateien gelöscht.");

        return self::SUCCESS;
    }
}
