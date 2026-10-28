<?php

namespace Tests\Feature;

use App\Models\HrPolicyDocument;
use App\Models\User;
use App\Services\PolicyIngestService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PolicyUploadReadinessTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->actingAs(User::role('admin')->first(), 'web');
    }

    private function legacySchema(): void
    {
        // Only the guarded disposable SQLite :memory: connection is touched.
        \Tests\Support\IsolatedDatabase::assertSafeConfiguration(config('database.default'), config('database.connections.sqlite'));
        Schema::drop('hr_policy_documents');
        Schema::drop('policy_jobs');
        Schema::create('hr_policy_documents', function (Blueprint $table) {
            $table->id(); $table->string('title'); $table->string('filename'); $table->string('file_path')->nullable();
            $table->string('category')->default('general'); $table->boolean('is_active')->default(true);
            $table->date('effective_date')->nullable(); $table->string('status')->default('active');
            $table->unsignedInteger('chunk_count')->default(0); $table->text('ingest_error')->nullable();
            $table->boolean('ocr_used')->default(false); $table->timestamps();
        });
    }

    public function test_unmigrated_upload_returns_a_clear_form_error_without_storing_an_orphan_pdf()
    {
        $this->legacySchema();
        $this->post(route('hr.policy.upload.store'), ['title' => 'Retain my title', 'category' => 'leave',
            'pdf_file' => UploadedFile::fake()->create('scan.pdf', 1, 'application/pdf')])
            ->assertRedirect(route('hr.policy.upload.form'))->assertSessionHasErrors('pdf_file');
        $this->assertStringContainsString('policy ingestion queue migration', session('errors')->first('pdf_file'));
        $this->assertSame('Retain my title', session('_old_input.title'));
        $this->assertSame([], Storage::allFiles('policies'));
        $this->assertSame(0, HrPolicyDocument::count());
        $this->get(route('hr.policy.upload.form'))->assertOk()->assertSee('Text-based and scanned')->assertSee('policy ingestion queue migration');
    }

    public function test_the_forward_migration_preserves_legacy_records_and_supplies_upload_requirements()
    {
        $this->legacySchema();
        $id = DB::table('hr_policy_documents')->insertGetId(['title' => 'Existing policy', 'filename' => 'existing.pdf', 'status' => 'active']);
        require_once database_path('migrations/2026_10_14_110000_add_policy_ingestion_queue.php');
        (new \AddPolicyIngestionQueue)->up();
        app(PolicyIngestService::class)->assertUploadReady();
        $row = DB::table('hr_policy_documents')->find($id);
        $this->assertSame('Existing policy', $row->title);
        $this->assertSame('active', $row->status);
        $this->assertNull($row->ingestion_version);
        $this->assertNull($row->ingestion_queued_at);
        $this->assertNull($row->ingestion_started_at);
        $this->assertSame(0, DB::table('policy_jobs')->count());
    }

    public function test_missing_queue_schema_preserves_an_existing_policy_during_replacement()
    {
        $service = app(PolicyIngestService::class);
        $document = $service->store(UploadedFile::fake()->create('original.pdf', 1, 'application/pdf'), ['title' => 'Original', 'category' => 'general']);
        $oldPath = $document->file_path;
        Schema::drop('policy_jobs');
        $this->post(route('hr.policy.update.store', $document->id), ['title' => 'Changed', 'category' => 'general',
            'pdf_file' => UploadedFile::fake()->create('replacement.pdf', 1, 'application/pdf')])
            ->assertRedirect(route('hr.policy.update.form', $document->id))->assertSessionHasErrors('pdf_file');
        $this->assertSame('Original', $document->fresh()->title);
        $this->assertSame($oldPath, $document->fresh()->file_path);
        $this->assertSame([$oldPath], Storage::allFiles('policies'));
    }

    public function test_failed_record_creation_compensates_only_its_unused_uploaded_file()
    {
        HrPolicyDocument::creating(function () { throw new \RuntimeException('Synthetic insert failure'); });
        try {
            app(PolicyIngestService::class)->store(UploadedFile::fake()->create('policy.pdf', 1, 'application/pdf'), ['title' => 'Synthetic', 'category' => 'general']);
            $this->fail('Expected insert failure.');
        } catch (\RuntimeException $e) {
            $this->assertSame('Synthetic insert failure', $e->getMessage());
            $this->assertSame([], Storage::allFiles('policies'));
        } finally { HrPolicyDocument::flushEventListeners(); }
    }

    public function test_forward_migration_compiles_native_sql_server_types_without_destructive_queries()
    {
        // Compile the actual migration using SQL Server grammar; no SQL Server connection is opened.
        $connection = new \Illuminate\Database\SqlServerConnection(new \PDO('sqlite::memory:'), 'schema_preview');
        $original = DB::getFacadeRoot();
        DB::swap(new class($connection) {
            private $connection;
            public function __construct($connection) { $this->connection = $connection; }
            public function connection($name = null) { return $this->connection; }
        });
        try {
            require_once database_path('migrations/2026_10_14_110000_add_policy_ingestion_queue.php');
            $queries = $connection->pretend(function () { (new \AddPolicyIngestionQueue)->up(); });
        } finally { DB::swap($original); }
        $sql = strtolower(implode("\n", array_column($queries, 'query')));
        $this->assertStringContainsString('create table "policy_jobs"', $sql);
        $this->assertStringContainsString('"ingestion_version" uniqueidentifier null', $sql);
        $this->assertStringContainsString('"ingestion_queued_at" datetime null', $sql);
        $this->assertDoesNotMatchRegularExpression('/\b(drop|truncate|delete|update)\b/', $sql);
    }
}
