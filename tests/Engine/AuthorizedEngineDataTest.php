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
use Illuminate\Support\Str;
use Tests\Support\AuthorizedEngineDatabase;
use Tests\Support\EngineSubmissionRace;

/** Existing schema only. No migration, seeder, reset, truncation, or file operation. */
class AuthorizedEngineDataTest extends \PHPUnit\Framework\TestCase
{
    private $token;
    private $tag;
    private $user;
    private $issue;
    private $owned = ['surveys' => [], 'arta_surveys' => []];

    public function test_authorized_engine_records_queries_and_concurrent_retries()
    {
        if (getenv('ENGINE_DATA_TEST_CONFIRM') === false) { $this->markTestSkipped('Existing-database data probes were not explicitly enabled.'); }
        AuthorizedEngineDatabase::boot();
        $this->token = bin2hex(random_bytes(16));
        $this->tag = 'ec_engine_probe_' . $this->token;
        $this->assertSame(0, DB::table('users')->where('username', $this->tag)->count());
        $this->issue = DB::table('issues')->value('id');
        $status = DB::table('statuses')->value('id');
        if (!$this->issue || !$status) { $this->fail('Existing issue/status prerequisites are missing; no fixture was created.'); }
        try {
            $this->user = DB::table('users')->insertGetId(['username' => $this->tag, 'name' => $this->tag,
                'email' => $this->tag . '@example.test', 'is_active' => false, 'password' => password_hash(bin2hex(random_bytes(32)), PASSWORD_BCRYPT, ['cost' => 4])]);
            $this->assertSame((int) $this->user, AuthorizedEngineDatabase::assertOwner($this->token));
            $this->verifyMetadataAndAttachmentCapacity($status);
            $this->verifyReports();
            foreach (['survey', 'arta'] as $kind) {
                $data = $this->payload($kind);
                $model = $kind === 'survey' ? Survey::class : ArtaSurvey::class;
                $results = EngineSubmissionRace::run($kind, [$data, $data], $this->token, 'authorized-data');
                $this->assertSame(['saved', 'saved'], array_column($results, 'status'));
                $this->assertSame($results[0]['id'], $results[1]['id']);
                $this->assertSame(1, $model::where('submission_id', $data['submission_id'])->count());
                $this->assertSame($results[0]['id'], (int) SurveySubmission::save($model, array_merge($data, ['submission_id' => strtoupper($data['submission_id'])]))->id);
                $data = $this->payload($kind);
                $changed = array_merge($data, [$kind === 'survey' ? 'overall_rating' : 'sqd0' => $kind === 'survey' ? 'Dissatisfied' : 'Disagree']);
                $results = EngineSubmissionRace::run($kind, [$data, $changed], $this->token, 'authorized-data');
                $statuses = array_column($results, 'status'); sort($statuses);
                $this->assertSame(['rejected', 'saved'], $statuses);
                $this->assertSame(1, $model::where('submission_id', $data['submission_id'])->count());
                $loser = $results[0]['status'] === 'saved' ? $changed : $data;
                try { SurveySubmission::save($model, $loser); $this->fail('Changed retry accepted.'); }
                catch (\Illuminate\Validation\ValidationException $e) { $this->assertArrayHasKey('submission_id', $e->errors()); }
            }
        } finally {
            $this->cleanupOwnedRecords();
        }
    }

    private function verifyMetadataAndAttachmentCapacity($status): void
    {
        $columns = DB::select("SELECT OBJECT_NAME(c.object_id) AS table_name, c.name AS column_name, t.name AS type_name, c.max_length, c.is_nullable
            FROM sys.columns c JOIN sys.types t ON c.user_type_id=t.user_type_id
            WHERE (OBJECT_NAME(c.object_id)='tickets' AND c.name='attachments') OR (OBJECT_NAME(c.object_id)='ticket_comments' AND c.name='attachment')
            OR (OBJECT_NAME(c.object_id) IN ('surveys','arta_surveys') AND c.name='submission_id')");
        $this->assertCount(4, $columns);
        foreach ($columns as $column) {
            $this->assertSame(1, (int) $column->is_nullable);
            if ($column->column_name === 'submission_id') { $this->assertSame('uniqueidentifier', $column->type_name); }
            else { $this->assertSame(-1, (int) $column->max_length); }
        }
        $indexes = DB::select("SELECT is_unique, has_filter, filter_definition FROM sys.indexes WHERE name IN ('surveys_submission_id_unique','arta_surveys_submission_id_unique')");
        $this->assertCount(2, $indexes);
        foreach ($indexes as $index) {
            $this->assertSame(1, (int) $index->is_unique); $this->assertSame(1, (int) $index->has_filter);
            $this->assertStringContainsString('IS NOT NULL', strtoupper($index->filter_definition));
        }
        $json = json_encode(array_fill(0, 20, 'attachments/' . $this->tag . str_repeat('a', 80) . '.txt'));
        $ticket = DB::table('tickets')->insertGetId(['reference_id' => $this->tag, 'user_id' => $this->user,
            'status_id' => $status, 'message' => $this->tag, 'attachments' => $json]);
        $comment = DB::table('ticket_comments')->insertGetId(['ticket_id' => $ticket, 'user_id' => $this->user, 'comment' => $this->tag, 'attachment' => $json]);
        $this->assertSame($json, DB::table('tickets')->where('id', $ticket)->value('attachments'));
        $this->assertSame($json, DB::table('ticket_comments')->where('id', $comment)->value('attachment'));
        DB::table('ticket_comments')->where('id', $comment)->where('user_id', $this->user)->update(['attachment' => null]);
        $this->assertNull(DB::table('ticket_comments')->where('id', $comment)->value('attachment'));
        // Multiple fixture NULLs coexist with any legacy NULL rows; no existing rows are updated.
        foreach (['survey', 'arta'] as $kind) {
            $model = $kind === 'survey' ? Survey::class : ArtaSurvey::class;
            for ($i = 0; $i < 2; $i++) { $model::create(array_diff_key($this->payload($kind), ['submission_id' => true])); }
        }
    }

    private function verifyReports(): void
    {
        $start = '1999-01-02 00:00:00'; $end = '1999-01-02 23:59:59';
        $baseline = SurveyReportQueries::summary($start, $end)['summaryCards'];
        $row = Survey::create(array_merge($this->payload('survey'), ['timeliness_rating' => 'satisfied', 'handling_rating' => 'Satisfied ', 'quality_rating' => 'Legacy value']));
        $row->forceFill(['created_at' => '1999-01-02 13:05:00'])->save();
        $summary = SurveyReportQueries::summary($start, $end)['summaryCards'];
        $this->assertSame($baseline['total_surveys'] + 1, $summary['total_surveys']);
        $this->assertSame($baseline['satisfied'] + 1, $summary['satisfied']);
        foreach ([false, true] as $arta) {
            if ($arta) {
                $row = ArtaSurvey::create($this->payload('arta'));
                $row->forceFill(['created_at' => '1999-01-02 13:05:00'])->save();
            }
            $request = Request::create('/', 'GET', ['search' => ['value' => $this->tag . ' "01/02/1999" "1:05 PM"']]);
            $result = SurveyResponseTable::data($request, $arta);
            $this->assertSame(1, $result['recordsFiltered']);
            $this->assertContains('01/02/1999 - 1:05 PM', $result['data'][0]);
        }
        foreach (['minutes' => 90, 'hours' => 1] as $unit => $expected) {
            $method = $unit === 'minutes' ? 'diffMinutesSql' : 'diffHoursSql';
            $sql = SqlDialectHelper::$method('CAST(? AS datetime2)', 'CAST(? AS datetime2)');
            $result = DB::selectOne('SELECT ' . $sql . ' AS elapsed', ['1999-01-02 10:00:00', '1999-01-02 11:30:00']);
            $this->assertSame($expected, (int) $result->elapsed);
        }
    }

    private function payload(string $kind): array
    {
        $id = (string) Str::uuid();
        $table = $kind === 'survey' ? 'surveys' : 'arta_surveys';
        if (DB::table($table)->where('submission_id', $id)->exists()) { throw new \LogicException('Fixture UUID collision; existing row retained.'); }
        $this->owned[$table][] = $id;
        return $kind === 'arta' ? ['submission_id' => $id, 'service_availed' => $this->tag, 'sqd0' => 'Agree']
            : ['submission_id' => $id, 'user_id' => $this->user, 'issue_id' => $this->issue, 'timeliness_rating' => 'Satisfied',
                'handling_rating' => 'Satisfied', 'quality_rating' => 'Satisfied', 'overall_rating' => 'Satisfied'];
    }

    private function cleanupOwnedRecords(): void
    {
        // Exact random fixture ownership, checked again in one transaction; no model events/file cleanup.
        $user = DB::table('users')->where('username', $this->tag)->where('is_active', false)->first(['id']);
        if (!$user) { return; }
        DB::transaction(function () use ($user) {
            DB::table('ticket_comments')->where('user_id', $user->id)->where('comment', $this->tag)->delete();
            DB::table('tickets')->where('user_id', $user->id)->where('reference_id', $this->tag)->delete();
            // Includes UUID-less fixture rows, always scoped to this inactive synthetic account/service tag.
            DB::table('surveys')->where('user_id', $user->id)->where(function ($q) {
                $q->whereIn('submission_id', $this->owned['surveys'])->orWhereNull('submission_id');
            })->delete();
            DB::table('arta_surveys')->where('service_availed', $this->tag)->where(function ($q) {
                $q->whereIn('submission_id', $this->owned['arta_surveys'])->orWhereNull('submission_id');
            })->delete();
            DB::table('users')->where('id', $user->id)->where('username', $this->tag)->where('is_active', false)->delete();
        });
        $this->assertSame(0, DB::table('users')->where('username', $this->tag)->count());
        $this->assertSame(0, DB::table('arta_surveys')->where('service_availed', $this->tag)->count());
        fwrite(STDOUT, "\nOwned engine fixtures cleaned: " . $this->tag . ". No attachments or schema were changed.\n");
    }
}
