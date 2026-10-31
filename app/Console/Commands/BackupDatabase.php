<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class BackupDatabase extends Command
{
    protected $signature = 'backup:database {--keep=14 : Number of backups to keep}';

    protected $description = 'Write a consistent copy of the SQLite database to the backups disk and delete old copies';

    public function handle(): int
    {
        if (DB::connection()->getDriverName() !== 'sqlite') {
            $this->error('Backups werden nur für SQLite unterstützt.');

            return self::FAILURE;
        }

        $disk = Storage::disk('backups');
        File::ensureDirectoryExists($disk->path(''));

        $filename = 'finanzpilot-'.now()->format('Y-m-d-His').'.sqlite';

        // Unlike a file copy, VACUUM INTO is consistent while the app writes to the WAL.
        DB::statement('VACUUM INTO ?', [$disk->path($filename)]);

        $outdatedBackups = collect($disk->files())
            ->filter(fn (string $path) => preg_match('/^finanzpilot-[\d-]+\.sqlite$/', $path))
            ->sortDesc()
            ->slice(max(1, (int) $this->option('keep')));

        $disk->delete($outdatedBackups->all());

        $this->info("Backup {$filename} erstellt, {$outdatedBackups->count()} alte Backups gelöscht.");

        return self::SUCCESS;
    }
}
