<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Category;
use App\Models\ImportBatch;
use App\Models\ImportMapping;
use App\Models\Transaction;
use App\Services\Categorization\CategoryRuleEngine;
use App\Services\Import\FormatDetector;
use App\Services\Import\GenericCsvParser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class ImportController extends Controller
{
    public function __construct(
        private CategoryRuleEngine $ruleEngine,
    ) {}

    /**
     * Step 1: Show the upload form.
     */
    public function index()
    {
        $batches = ImportBatch::orderByDesc('created_at')->limit(20)->get();
        $mappings = ImportMapping::orderBy('name')->get();

        return Inertia::render('Import/Index', [
            'batches' => $batches,
            'mappings' => $mappings,
        ]);
    }

    /**
     * Step 2: Upload file, detect format, parse, and show preview.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt|max:10240',
        ]);

        $file = $request->file('file');
        $path = $file->store('imports');
        $fullPath = Storage::path($path);

        // Auto-detect format
        $detector = new FormatDetector;
        $parser = $detector->detect($fullPath);
        if ($parser) {
            // Known format — parse immediately
            $parsed = $parser->parse($fullPath);

            return $this->showPreview($parsed, $file->getClientOriginalName(), $parser->getSourceType(), $path);
        }

        // Unknown format — show column mapping UI
        $delimiter = FormatDetector::detectDelimiter($fullPath);
        $preview = GenericCsvParser::previewFile($fullPath, $delimiter, 5);

        return Inertia::render('Import/ColumnMapping', [
            'filePath' => $path,
            'fileName' => $file->getClientOriginalName(),
            'delimiter' => $delimiter,
            'preview' => $preview,
            'mappings' => ImportMapping::orderBy('name')->get(),
        ]);
    }

    /**
     * Step 2b: Parse generic CSV with user-provided column mapping.
     */
    public function parseGeneric(Request $request)
    {
        $request->validate([
            'file_path' => ['required', 'string', 'regex:/^imports\//'],
            'delimiter' => 'required|string|max:1',
            'has_header' => 'required|boolean',
            'columns.date' => 'required|integer|min:0',
            'columns.amount' => 'required|integer|min:0',
            'columns.description' => 'required|integer|min:0',
            'columns.counterparty' => 'nullable|integer|min:0',
            'columns.reference' => 'nullable|integer|min:0',
            'date_format' => 'nullable|string',
            'save_mapping' => 'nullable|boolean',
            'mapping_name' => 'nullable|required_if:save_mapping,true|string|max:255',
        ]);

        $fullPath = Storage::path($request->file_path);

        if (! str_starts_with(realpath($fullPath) ?: '', realpath(storage_path('app/imports')) ?: '')) {
            abort(403, 'Ungültiger Dateipfad.');
        }

        if (! file_exists($fullPath)) {
            return redirect()->route('imports.index')->with('error', 'Datei nicht gefunden.');
        }

        // Save mapping if requested
        if ($request->save_mapping && $request->mapping_name) {
            ImportMapping::updateOrCreate(
                ['name' => $request->mapping_name],
                [
                    'source_type' => 'generic',
                    'column_mapping' => [
                        'columns' => $request->columns,
                        'delimiter' => $request->delimiter,
                        'has_header' => $request->has_header,
                        'date_format' => $request->date_format ?? 'd.m.Y',
                        'encoding' => 'UTF-8',
                    ],
                ]
            );
        }

        $parser = GenericCsvParser::fromMapping([
            'columns' => $request->columns,
            'delimiter' => $request->delimiter,
            'has_header' => $request->has_header,
            'date_format' => $request->date_format ?? 'd.m.Y',
            'source_name' => 'generic',
        ]);

        $parsed = $parser->parse($fullPath);

        return $this->showPreview($parsed, basename($request->file_path), 'generic', $request->file_path);
    }

    /**
     * Step 3: Show parsed transactions with dedup flags and auto-categorization.
     */
    private function showPreview(array $parsed, string $filename, string $sourceType, string $storagePath)
    {
        // Check for duplicates
        $existingHashes = Transaction::whereIn('hash', array_map(fn ($p) => $p->hash, $parsed))
            ->pluck('hash')
            ->toArray();

        // Auto-categorize using rule engine
        $categorizationItems = array_map(fn ($p) => [
            'description' => $p->description,
            'counterparty' => $p->counterparty,
        ], $parsed);
        $categorizations = $this->ruleEngine->categorizeBulk($categorizationItems);

        // Build preview data
        $previewData = [];
        foreach ($parsed as $index => $item) {
            $isDuplicate = in_array($item->hash, $existingHashes);
            $cat = $categorizations[$index] ?? null;

            $previewData[] = [
                'index' => $index,
                'date' => $item->date,
                'amount' => $item->amount,
                'description' => $item->description,
                'counterparty' => $item->counterparty,
                'reference' => $item->reference,
                'hash' => $item->hash,
                'is_duplicate' => $isDuplicate,
                'category_id' => $cat?->categoryId,
                'rule_id' => $cat?->ruleId,
                'confidence' => $cat?->confidence ?? 0,
                'match_type' => $cat?->matchType ?? 'none',
                'selected' => ! $isDuplicate, // Pre-select non-duplicates
            ];
        }

        // Load categories for manual assignment
        $categories = Category::tree();

        return Inertia::render('Import/Preview', [
            'transactions' => $previewData,
            'filename' => $filename,
            'sourceType' => $sourceType,
            'storagePath' => $storagePath,
            'totalCount' => count($parsed),
            'duplicateCount' => count(array_filter($previewData, fn ($t) => $t['is_duplicate'])),
            'categorizedCount' => count(array_filter($previewData, fn ($t) => $t['category_id'] !== null)),
            'categories' => $categories,
            'accounts' => Account::where('is_active', true)->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    /**
     * Step 4: Commit selected transactions to the database.
     */
    public function commit(Request $request)
    {
        $request->validate([
            'filename' => 'required|string',
            'source_type' => 'required|string',
            'storage_path' => ['required', 'string', 'regex:/^imports\//'],
            'transactions' => 'required|array|min:1',
            'transactions.*.date' => 'required|date',
            'transactions.*.amount' => 'required|numeric',
            'transactions.*.description' => 'required|string',
            'transactions.*.counterparty' => 'nullable|string',
            'transactions.*.reference' => 'nullable|string',
            'transactions.*.hash' => 'nullable|string',
            'transactions.*.category_id' => 'nullable|exists:categories,id',
            'account_id' => 'nullable|exists:accounts,id',
        ]);

        DB::transaction(function () use ($request) {
            // Create import batch
            $batch = ImportBatch::create([
                'filename' => $request->filename,
                'source_type' => $request->source_type,
                'uploaded_at' => now(),
                'row_count' => count($request->transactions),
                'status' => 'committed',
            ]);

            $sourceMap = [
                'sparkasse' => 'sparkasse',
                'paypal' => 'paypal',
                'generic' => 'generic',
            ];
            $source = $sourceMap[$request->source_type] ?? 'manual';

            foreach ($request->transactions as $txData) {
                $transaction = Transaction::create([
                    'date' => $txData['date'],
                    'amount' => $txData['amount'],
                    'description' => $txData['description'],
                    'counterparty' => $txData['counterparty'] ?? null,
                    'category_id' => $txData['category_id'] ?? null,
                    'source' => $source,
                    'reference' => $txData['reference'] ?? null,
                    'hash' => $txData['hash'] ?? null,
                    'notes' => null,
                    'import_batch_id' => $batch->id,
                    'account_id' => $request->account_id ?? null,
                ]);

                // Learn from manual categorizations
                if ($transaction->category_id) {
                    $this->ruleEngine->learn($transaction, $transaction->category_id);
                }
            }
        });

        // Clean up uploaded file
        if ($request->storage_path) {
            Storage::delete($request->storage_path);
        }

        return redirect()->route('imports.index')
            ->with('success', count($request->transactions).' Buchungen importiert.');
    }

    /**
     * Review queue: Show uncategorized transactions from imports.
     */
    public function review()
    {
        $transactions = Transaction::whereNull('category_id')
            ->whereIn('source', ['sparkasse', 'paypal', 'generic'])
            ->with('importBatch')
            ->orderByDesc('date')
            ->paginate(50);

        $categories = Category::tree();

        return Inertia::render('Import/Review', [
            'transactions' => $transactions,
            'categories' => $categories,
        ]);
    }

    /**
     * Categorize a single transaction from the review queue.
     */
    public function categorize(Request $request, Transaction $transaction)
    {
        $request->validate([
            'category_id' => 'required|exists:categories,id',
        ]);

        $transaction->update(['category_id' => $request->category_id]);

        // Learn from this categorization
        $this->ruleEngine->learn($transaction, $request->category_id);

        // Get rule suggestion for the user
        $suggestion = $this->ruleEngine->suggestRule($transaction, $request->category_id);

        if ($suggestion) {
            return redirect()->back()->with([
                'success' => 'Kategorie zugewiesen.',
                'ruleSuggestion' => $suggestion,
            ]);
        }

        return redirect()->back()->with('success', 'Kategorie zugewiesen.');
    }

    /**
     * Bulk categorize multiple transactions.
     */
    public function bulkCategorize(Request $request)
    {
        $request->validate([
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => 'exists:transactions,id',
            'category_id' => 'required|exists:categories,id',
        ]);

        $transactions = Transaction::whereIn('id', $request->transaction_ids)->get();

        foreach ($transactions as $transaction) {
            $transaction->update(['category_id' => $request->category_id]);
            $this->ruleEngine->learn($transaction, $request->category_id);
        }

        return redirect()->back()->with('success', count($transactions).' Buchungen kategorisiert.');
    }

}
