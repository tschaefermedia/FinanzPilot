<?php

namespace App\Console\Commands;

use App\Http\Controllers\RecurringTemplateController;
use App\Models\RecurringTemplate;
use Illuminate\Console\Command;

class GenerateRecurringTransactions extends Command
{
    protected $signature = 'recurring:generate';
    protected $description = 'Generate transactions from recurring templates that are due';

    public function handle(): int
    {
        $templates = RecurringTemplate::where('is_active', true)
            ->where('auto_generate', true)
            ->where('next_due_date', '<=', now()->toDateString())
            ->get();

        if ($templates->isEmpty()) {
            $this->info('Keine fälligen Daueraufträge.');
            return self::SUCCESS;
        }

        $count = 0;
        foreach ($templates as $template) {
            // Generate all overdue instances (e.g., if scheduler missed a day)
            while ($template->next_due_date->lte(now())) {
                RecurringTemplateController::generateTransaction($template);
                $template->refresh();
                $count++;
            }
        }

        $this->info("{$count} Buchung(en) aus Daueraufträgen erstellt.");
        return self::SUCCESS;
    }
}
