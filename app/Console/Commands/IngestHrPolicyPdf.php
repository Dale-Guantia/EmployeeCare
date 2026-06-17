<?php
namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\PolicyIngestService;
use App\Models\HrPolicyDocument;

class IngestHrPolicyPdf extends Command
{
    protected $signature = 'hr:ingest
        {path : Absolute or relative path to the PDF file}
        {--title= : Document title (defaults to filename without extension)}
        {--category=general : Category: general, leave, benefits, conduct, discipline, performance}
        {--effective-date= : Effective date in YYYY-MM-DD format}
        {--force : Re-ingest even if a document with the same filename already exists}';

    protected $description = 'Parse a policy PDF and store searchable chunks in the database';

    protected $ingestService;

    // FIX: Inject PolicyIngestService instead of duplicating ingest logic here.
    // The old command had its own chunkText/detectSection/buildVector methods
    // that were already in PolicyIngestService — this was logic duplication.
    // Any future chunking improvement now only needs to be made in one place.
    public function __construct(PolicyIngestService $ingestService)
    {
        parent::__construct();
        $this->ingestService = $ingestService;
    }

    public function handle(): int
    {
        $path = $this->argument('path');

        if (!file_exists($path)) {
            $this->error("File not found: {$path}");
            return 1;
        }

        if (!is_readable($path)) {
            $this->error("File is not readable (check permissions): {$path}");
            return 1;
        }

        $filename = basename($path);

        // FIX: Check for duplicate filename before ingesting.
        // Previously, running the command twice on the same file silently created
        // a second document record with duplicate chunks.
        $existing = HrPolicyDocument::where('filename', $filename)->first();

        if ($existing && !$this->option('force')) {
            $this->warn("A document with filename '{$filename}' already exists (ID: {$existing->id}, status: {$existing->status}).");
            $this->warn("Use --force to delete it and re-ingest, or use the Policy Documents UI to update it.");
            return 1;
        }

        if ($existing && $this->option('force')) {
            $this->line("Removing existing document '{$existing->title}' (ID: {$existing->id}) and re-ingesting...");
            $this->ingestService->destroy($existing);
        }

        // Validate category
        $validCategories = ['general', 'leave', 'benefits', 'conduct', 'discipline', 'performance'];
        $category        = $this->option('category');

        if (!in_array($category, $validCategories)) {
            $this->error("Invalid category '{$category}'. Valid options: " . implode(', ', $validCategories));
            return 1;
        }

        // Build the fake UploadedFile-compatible data for PolicyIngestService::store()
        // Since this is CLI, we create the document record manually then call ingest()
        $title = $this->option('title') ?: pathinfo($path, PATHINFO_FILENAME);

        $this->line("Creating document record for '{$title}'...");

        // Copy the file into storage/policies/ first
        $storagePath = 'policies/' . time() . '_' . $filename;
        \Illuminate\Support\Facades\Storage::put(
            $storagePath,
            file_get_contents($path)
        );

        $document = HrPolicyDocument::create([
            'title'          => $title,
            'filename'       => $filename,
            'file_path'      => $storagePath,
            'category'       => $category,
            'effective_date' => $this->option('effective-date') ?: null,
            'is_active'      => true,
            'status'         => 'processing',
            'chunk_count'    => 0,
        ]);

        $this->line("Parsing and chunking PDF...");

        try {
            $this->ingestService->ingest($document);
            $document->refresh();

            $this->info("Done! Ingested {$document->chunk_count} chunks from '{$document->title}' (ID: {$document->id}).");
            return 0;

        } catch (\Throwable $e) {
            $this->error("Ingestion failed: " . $e->getMessage());
            $this->error("Document record saved with status 'failed' (ID: {$document->id}). You can retry via the Policy Documents UI.");
            return 1;
        }
    }
}
