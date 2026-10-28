<?php

namespace Tests\Engine;

use App\Models\ArtaSurvey;
use App\Models\Survey;
use App\Services\SqlDialectHelper;
use App\Services\SurveyReportQueries;
use App\Services\SurveyResponseTable;
use App\Services\SurveySubmission;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Support\EngineDatabase;

/** Separate suite: no SQLite substitutions and no application DatabaseSeeder. */
class DeploymentEngineTest extends \PHPUnit\Framework\TestCase
{
    private $app;
    private $token;

    public function test_migrations_queries_and_real_concurrent_submission_retries()
    {
        if (getenv('ENGINE_TEST_DRIVER') === false) {
            $this->markTestSkipped('No isolated engine database configured. See docs/DEPLOYMENT_READINESS.md.');
        }
        $this->app = EngineDatabase::boot();
        $this->token = bin2hex(random_bytes(32));
        EngineDatabase::claimEmptyDatabase($this->token);
        $this->createLegacySchema();
        $this->verifyForwardMigrations();
        $this->verifyReports();
        foreach (['survey', 'arta'] as $kind) {
            $data = $this->payload($kind);
            $results = $this->race($kind, [$data, $data]);
            $this->assertSame(['saved', 'saved'], array_column($results, 'status'));
            $this->assertSame($results[0]['id'], $results[1]['id']);
            $model = $kind === 'survey' ? Survey::class : ArtaSurvey::class;
            $this->assertSame(1, $model::where('submission_id', $data['submission_id'])->count());
            $this->assertSame($results[0]['id'], (int) SurveySubmission::save($model, array_merge($data, ['submission_id' => strtoupper($data['submission_id'])]))->id);
            $other = $this->payload($kind);
            $changed = array_merge($other, [$kind === 'survey' ? 'overall_rating' : 'sqd0' => $kind === 'survey' ? 'Dissatisfied' : 'Disagree']);
            $results = $this->race($kind, [$other, $changed]);
            $statuses = array_column($results, 'status'); sort($statuses);
            $this->assertSame(['rejected', 'saved'], $statuses);
            $this->assertSame(1, $model::where('submission_id', $other['submission_id'])->count());
            $winner = $results[0]['status'] === 'saved' ? $other : $changed;
            $loser = $results[0]['status'] === 'saved' ? $changed : $other;
            $this->assertSame(1, $model::where('submission_id', $winner['submission_id'])->count());
            try { SurveySubmission::save($model, $loser); $this->fail('Changed answers accepted.'); }
            catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('submission_id', $e->errors()); }
        }
        // Keep the isolated database and synthetic fixtures for inspection; never wipe/drop a database.
    }

    private function migrate(string $filename): void
    {
        $migration = $this->app['migrator']->resolvePath(database_path('migrations/' . $filename . '.php'));
        $migration->up();
    }

    private function createLegacySchema(): void
    {
        foreach (['2026_02_23_070500_create_permission_tables', '2026_02_24_022107_create_priorities_table',
            '2026_02_24_022453_create_departments_table', '2026_02_24_022509_create_divisions_table',
            '2026_02_24_022528_create_issues_table', '2026_02_24_022530_create_users_table',
            '2026_02_24_024606_create_statuses_table', '2026_02_24_073426_create_tickets_table',
            '2026_03_05_074049_surveys_table', '2026_03_11_061811_create_ticket_comments_table',
            '2026_04_16_021820_create_arta_surveys_table', '2026_07_15_090000_create_ticket_reassignment_requests_table'] as $migration) {
            $this->migrate($migration);
        }
        DB::table('departments')->insert(['department_name' => 'Synthetic department']);
        DB::table('divisions')->insert(['department_id' => 1, 'division_name' => 'Synthetic division']);
        DB::table('issues')->insert(['department_id' => 1, 'division_id' => 1, 'issue_description' => 'Synthetic service']);
        DB::table('users')->insert(['name' => 'Synthetic staff', 'username' => 'engine_staff', 'password' => 'unused', 'department_id' => 1, 'division_id' => 1]);
        foreach (['Pending', 'Resolved', 'Reopened', 'Unassigned'] as $name) { DB::table('statuses')->insert(['status_name' => $name, 'status_color' => '#fff']); }
        DB::table('tickets')->insert(['reference_id' => 'ENGINE-LEGACY', 'user_id' => 1, 'status_id' => 2, 'message' => 'Synthetic',
            'attachments' => '["attachments/legacy.txt"]', 'created_at' => '2026-10-01 10:00:00', 'resolved_at' => '2026-10-01 11:30:00']);
        DB::table('ticket_comments')->insert(['ticket_id' => 1, 'user_id' => 1, 'comment' => 'Synthetic', 'attachment' => '["attachments/comment.txt"]']);
        DB::table('tickets')->insert(['reference_id' => 'ENGINE-UNICODE', 'user_id' => 1, 'status_id' => 2, 'message' => 'Synthetic',
            'attachments' => json_encode(['attachments/漢字.txt'], JSON_UNESCAPED_UNICODE)]);
        DB::table('ticket_comments')->insert(['ticket_id' => 1, 'user_id' => 1, 'comment' => 'Synthetic Unicode',
            'attachment' => json_encode(['attachments/漢字.txt'], JSON_UNESCAPED_UNICODE)]);
        DB::table('ticket_comments')->insert(['ticket_id' => 1, 'user_id' => 1, 'comment' => 'Synthetic NULL', 'attachment' => null]);
        for ($i = 0; $i < 2; $i++) {
            Survey::create(array_diff_key($this->payload('survey'), ['submission_id' => true]));
            ArtaSurvey::create(['sqd0' => 'Agree']);
        }
        $admin = Role::create(['name' => 'admin', 'guard_name' => 'web']);
        $hr = Role::create(['name' => 'hr_staff', 'guard_name' => 'web']);
        $existing = Permission::create(['name' => 'ticket.view', 'guard_name' => 'web']);
        $admin->givePermissionTo($existing); $hr->givePermissionTo($existing);
    }

    private function verifyForwardMigrations(): void
    {
        $this->migrate('2026_10_07_100000_widen_ticket_attachment_columns');
        $this->assertSame('["attachments/legacy.txt"]', DB::table('tickets')->value('attachments'));
        $this->assertSame('["attachments/comment.txt"]', DB::table('ticket_comments')->value('attachment'));
        $unicode = json_encode(['attachments/漢字.txt'], JSON_UNESCAPED_UNICODE);
        $this->assertSame($unicode, DB::table('tickets')->where('reference_id', 'ENGINE-UNICODE')->value('attachments'));
        $this->assertSame($unicode, DB::table('ticket_comments')->where('comment', 'Synthetic Unicode')->value('attachment'));
        $this->assertNull(DB::table('ticket_comments')->where('comment', 'Synthetic NULL')->value('attachment'));
        $this->migrate('2026_10_14_100000_preserve_unicode_ticket_attachment_columns');
        if (DB::connection()->getDriverName() === 'sqlsrv') {
            $columns = DB::select("SELECT t.name AS type_name, c.max_length FROM sys.columns c JOIN sys.types t ON c.user_type_id=t.user_type_id
                WHERE (OBJECT_NAME(c.object_id)='tickets' AND c.name='attachments') OR (OBJECT_NAME(c.object_id)='ticket_comments' AND c.name='attachment')");
            $this->assertCount(2, $columns);
            foreach ($columns as $column) { $this->assertSame('nvarchar', $column->type_name); $this->assertSame(-1, (int) $column->max_length); }
        }
        $large = json_encode(array_fill(0, 20, 'attachments/' . str_repeat('a', 100) . '.txt'));
        foreach (['tickets' => 'attachments', 'ticket_comments' => 'attachment'] as $table => $column) {
            DB::table($table)->where('id', 1)->update([$column => $large]);
            $this->assertSame($large, DB::table($table)->where('id', 1)->value($column));
        }
        $this->migrate('2026_10_07_110000_add_survey_submission_ids');
        foreach (['surveys', 'arta_surveys'] as $table) { $this->assertSame(2, DB::table($table)->whereNull('submission_id')->count()); }
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlsrv') {
            $indexes = DB::select("SELECT name, is_unique, has_filter, filter_definition FROM sys.indexes WHERE name IN ('surveys_submission_id_unique', 'arta_surveys_submission_id_unique')");
            $this->assertCount(2, $indexes);
            foreach ($indexes as $index) {
                $this->assertSame(1, (int) $index->is_unique); $this->assertSame(1, (int) $index->has_filter);
                $this->assertStringContainsString('IS NOT NULL', strtoupper($index->filter_definition));
            }
        }
        foreach (['survey', 'arta'] as $kind) {
            $data = $this->payload($kind); $model = $kind === 'survey' ? Survey::class : ArtaSurvey::class;
            $entry = SurveySubmission::save($model, $data);
            $this->assertSame(strtolower($data['submission_id']), strtolower($entry->fresh()->submission_id));
            try { $model::create($data); $this->fail('Duplicate UUID inserted.'); }
            catch (\Illuminate\Database\QueryException $e) { $this->assertContains((int) ($e->errorInfo[1] ?? 0), [1062, 2601, 2627]); }
        }
        $this->migrate('2026_10_07_120000_add_explicit_ticket_assignment_permission');
        $this->migrate('2026_10_07_120000_add_explicit_ticket_assignment_permission');
        $this->assertSame(1, Permission::where('name', 'ticket.assign')->count());
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('ticket.assign'));
        $this->assertTrue(Role::findByName('admin')->hasPermissionTo('ticket.view'));
        $this->assertTrue(Role::findByName('hr_staff')->hasPermissionTo('ticket.view'));
        $this->assertFalse(Role::findByName('hr_staff')->hasPermissionTo('ticket.assign'));
        foreach (['2026_07_23_000001_add_indexes_to_tickets_table', '2026_07_23_000002_add_indexes_to_ticket_reassignment_requests_table',
            '2026_07_23_000003_add_indexes_to_users_table'] as $migration) { $this->migrate($migration); }
        $this->assertTrue(Schema::hasColumn('surveys', 'submission_id'));
    }

    private function verifyReports(): void
    {
        $baseline = SurveyReportQueries::summary()['summaryCards'];
        Survey::create(array_merge($this->payload('survey'), ['timeliness_rating' => 'satisfied', 'handling_rating' => 'Satisfied ',
            'quality_rating' => 'Legacy value']));
        $summary = SurveyReportQueries::summary()['summaryCards'];
        $this->assertSame($baseline['satisfied'] + 1, $summary['satisfied']);
        $this->assertSame($baseline['total_surveys'] + 1, $summary['total_surveys']);
        $diff = SqlDialectHelper::diffMinutesSql('created_at', 'resolved_at');
        $this->assertSame(90, (int) DB::table('tickets')->selectRaw($diff . ' AS elapsed')->where('id', 1)->value('elapsed'));
        $diff = SqlDialectHelper::diffHoursSql('created_at', 'resolved_at');
        $this->assertSame(1, (int) DB::table('tickets')->selectRaw($diff . ' AS elapsed')->where('id', 1)->value('elapsed'));
        foreach ([false, true] as $arta) {
            $model = $arta ? ArtaSurvey::class : Survey::class;
            $row = $model::create($this->payload($arta ? 'arta' : 'survey'));
            $row->forceFill(['created_at' => '2026-10-02 13:05:00'])->save();
            $request = Request::create('/', 'GET', ['search' => ['value' => '"10/02/2026" "1:05 PM"']]);
            $table = SurveyResponseTable::data($request, $arta);
            $this->assertSame(1, $table['recordsFiltered']);
            $this->assertContains('10/02/2026 - 1:05 PM', $table['data'][0]);
            $request = Request::create('/', 'GET', ['search' => ['value' => 'NoMatch%_[']]);
            $this->assertSame(0, SurveyResponseTable::data($request, $arta)['recordsFiltered']);
        }
        // Reuse full report parity fixtures on the real engine without the SQLite application trait.
        $parity = new \Tests\Support\EngineReportParity;
        $parity->useApplication($this->app);
        $parity->test_ticket_queries_equal_original_widgets_across_chunks_and_date_buckets();
        $parity->test_survey_grouped_queries_equal_original_for_all_staff_ratings_and_zero_activity();
        \Carbon\Carbon::setTestNow();
    }

    private function payload(string $kind): array
    {
        $data = ['submission_id' => (string) Str::uuid()];
        return $kind === 'arta' ? array_merge($data, ['sqd0' => 'Agree']) : array_merge($data,
            ['user_id' => 1, 'issue_id' => 1, 'timeliness_rating' => 'Satisfied', 'handling_rating' => 'Satisfied',
                'quality_rating' => 'Satisfied', 'overall_rating' => 'Satisfied']);
    }

    private function race(string $kind, array $payloads): array
    {
        return \Tests\Support\EngineSubmissionRace::run($kind, $payloads, $this->token);
    }
}
