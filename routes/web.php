<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AiAnalysisController;
use App\Http\Controllers\AiInsightsController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\CategoryAnalysisController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\RecurringTemplateController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\TrendController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

// Accounts
Route::resource('accounts', AccountController::class)->except(['show', 'create', 'edit']);

Route::post('transactions/bulk-update-account', [TransactionController::class, 'bulkUpdateAccount'])->name('transactions.bulkUpdateAccount');
Route::resource('transactions', TransactionController::class);
Route::get('categories/analysis', CategoryAnalysisController::class)->name('categories.analysis');
Route::resource('categories', CategoryController::class)->except(['show']);

// Import pipeline
Route::get('imports', [ImportController::class, 'index'])->name('imports.index');
Route::post('imports/upload', [ImportController::class, 'upload'])->name('imports.upload');
Route::post('imports/parse-generic', [ImportController::class, 'parseGeneric'])->name('imports.parseGeneric');
Route::post('imports/commit', [ImportController::class, 'commit'])->name('imports.commit');

// Review queue
Route::get('imports/review', [ImportController::class, 'review'])->name('imports.review');
Route::put('imports/categorize/{transaction}', [ImportController::class, 'categorize'])->name('imports.categorize');
Route::post('imports/bulk-categorize', [ImportController::class, 'bulkCategorize'])->name('imports.bulkCategorize');

// Recurring templates
Route::resource('recurring', RecurringTemplateController::class)->except(['show', 'create', 'edit']);
Route::post('recurring/{recurring}/generate', [RecurringTemplateController::class, 'generate'])->name('recurring.generate');

// Loans
Route::resource('loans', LoanController::class)->except(['create', 'edit']);
Route::post('loans/{loan}/payments', [LoanController::class, 'addPayment'])->name('loans.addPayment');
Route::post('loans/{loan}/auto-match', [LoanController::class, 'autoMatch'])->name('loans.autoMatch');
Route::get('loans/{loan}/unmatched-transactions', [LoanController::class, 'unmatchedTransactions'])->name('loans.unmatchedTransactions');
Route::post('loans/{loan}/match-transaction', [LoanController::class, 'matchTransaction'])->name('loans.matchTransaction');

// Calendar
Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
Route::post('calendar/payments', [CalendarController::class, 'store'])->name('calendar.store');
Route::put('calendar/payments/{scheduledPayment}', [CalendarController::class, 'update'])->name('calendar.update');
Route::delete('calendar/payments/{scheduledPayment}', [CalendarController::class, 'destroy'])->name('calendar.destroy');
Route::post('calendar/payments/{scheduledPayment}/complete', [CalendarController::class, 'complete'])->name('calendar.complete');

// Trends
Route::get('trends', TrendController::class)->name('trends');

// Export
Route::get('export', [ExportController::class, 'index'])->name('export.index');
Route::post('export/month', [ExportController::class, 'exportMonth'])->name('export.month');
Route::post('export/range', [ExportController::class, 'exportRange'])->name('export.range');
Route::post('export/batch', [ExportController::class, 'exportBatch'])->name('export.batch');

// Settings
Route::get('settings', [SettingsController::class, 'index'])->name('settings.index');
Route::put('settings/ai', [SettingsController::class, 'updateAi'])->name('settings.updateAi');
Route::delete('settings/clear-all', [SettingsController::class, 'clearAll'])->name('settings.clearAll');

// AI Analysis page
Route::get('ai', [AiAnalysisController::class, 'index'])->name('ai.index');
Route::get('api/ai/insights', [AiAnalysisController::class, 'insights'])->name('api.ai.insights');
Route::post('api/ai/refresh', [AiAnalysisController::class, 'refresh'])->middleware('throttle:5,1')->name('api.ai.refresh');

// AI Insights (JSON API for dashboard widget)
Route::get('api/insights', [AiInsightsController::class, 'index'])->name('api.insights');
Route::post('api/insights/refresh', [AiInsightsController::class, 'refresh'])->middleware('throttle:5,1')->name('api.insights.refresh');
