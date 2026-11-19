<?php

namespace Tests\Feature;

use App\Http\Livewire\TicketChat;
use App\Models\{Department, Division, Issue, Priority, Status, Ticket, User};
use App\Services\{OfficeRetirementRules, TicketNotificationService, TicketReportQueries, TicketReportWidgets};
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\{DB, Http, Schema};
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PermanentMasterDataCodesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(TicketNotificationService::class, \Mockery::mock(TicketNotificationService::class)->shouldIgnoreMissing());
        Http::fake(['*' => Http::response('// Theme asset fixture', 200, ['Content-Type' => 'application/javascript'])]);
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
    }

    private function migration()
    {
        return require database_path('migrations/2026_11_04_100000_add_permanent_master_data_codes.php');
    }

    private function offices(): array
    {
        $department = Department::create(['department_name' => 'Code test department']);
        $division = Division::create(['department_id' => $department->id, 'division_name' => 'Code test division']);
        return [$department, $division];
    }

    private function ticket(Department $department, Division $division, string $message = 'Code test ticket'): Ticket
    {
        return Ticket::create(['user_id' => backpack_user()->id, 'department_id' => $department->id,
            'division_id' => $division->id, 'is_custom_issue' => true, 'custom_issue' => 'Code test concern', 'message' => $message]);
    }

    public function test_seeded_codes_match_the_requested_values(): void
    {
        $this->assertSame(['hrdo'], Department::pluck('department_code')->all());
        $this->assertSame(['resolved', 'pending', 'unassigned', 'reopened'], Status::orderBy('id')->pluck('status_code')->all());
        $this->assertEqualsCanonicalizing(['pm', 'lnd', 'rsp', 'claims', 'records', 'payroll', 'admin', 'it', 'dh'], Division::pluck('division_code')->all());
        $this->assertSame(['high', 'medium', 'low'], Priority::orderBy('id')->pluck('priority_code')->all());
    }

    public function test_survey_reports_find_hrdo_division_heads_when_hrdo_is_not_id_one(): void
    {
        // Emulate an upgraded installation whose reference IDs differ from the demo seed order.
        DB::table('departments')->where('department_code', Department::HRDO)->update(['department_code' => 'legacy_hrdo']);
        Department::forgetCodeCache();
        $department = Department::create(['department_name' => 'Renamed HRDO', 'department_code' => Department::HRDO]);
        $division = Division::create(['department_id' => $department->id, 'division_name' => 'Synthetic HRDO division']);
        $head = User::factory()->create(['department_id' => $department->id, 'division_id' => $division->id, 'is_active' => true]);
        $head->assignRole('div_head');
        $issue = Issue::create(['department_id' => $department->id, 'division_id' => $division->id, 'issue_description' => 'Synthetic rated service']);
        \App\Models\Survey::create(['user_id' => $head->id, 'issue_id' => $issue->id,
            'timeliness_rating' => 'Satisfied', 'handling_rating' => 'Satisfied',
            'quality_rating' => 'Satisfied', 'overall_rating' => 'Satisfied']);

        $report = \App\Services\SurveyReportQueries::summary();

        $this->assertNotSame(1, $department->id);
        $this->assertSame(1, $report['summaryCards']['total_surveys']);
        $this->assertSame([$head->id], $report['surveyRatings']->pluck('id')->all());
    }

    public function test_backfill_preserves_ids_references_labels_and_individual_archive_flags(): void
    {
        [$department, $division] = $this->offices();
        $priority = Priority::where('priority_code', Priority::HIGH)->firstOrFail();
        $issue = Issue::create(['department_id' => $department->id, 'division_id' => $division->id,
            'issue_description' => 'Backfill fixture issue', 'priority_id' => $priority->id]);
        $ticket = $this->ticket($department, $division);
        $issue->update(['is_active' => false]);
        $beforeTicket = $ticket->fresh()->getAttributes();
        $beforeIssue = $issue->fresh()->getAttributes();
        $before = [];
        foreach ([Department::class, Division::class, Status::class, Priority::class] as $model) {
            $column = $model::CODE_COLUMN;
            $before[$model] = $model::orderBy('id')->get()->map(fn ($record) => collect($record->getAttributes())->except($column)->all())->all();
        }
        $auditCount = DB::table('master_data_audits')->count();
        $migration = $this->migration();
        $migration->down();
        $migration->up();
        foreach ($before as $model => $records) {
            $this->assertSame($records, $model::orderBy('id')->get()->map(fn ($record) => collect($record->getAttributes())->except($model::CODE_COLUMN)->all())->all());
        }
        $this->assertSame('hrdo', Department::findOrFail(1)->department_code);
        $this->assertSame('legacy_' . $department->id, $department->fresh()->department_code);
        $this->assertSame('legacy_' . $division->id, $division->fresh()->division_code);
        $this->assertSame($beforeTicket, $ticket->fresh()->getAttributes());
        $this->assertSame($beforeIssue, $issue->fresh()->getAttributes());
        $this->assertSame($auditCount, DB::table('master_data_audits')->count());
    }

    #[DataProvider('invalidBackfills')]
    public function test_backfill_refuses_missing_or_ambiguous_identities_before_altering_schema(bool $duplicate): void
    {
        $this->migration()->down();
        if ($duplicate) {
            DB::table('statuses')->insert(['status_name' => 'Resolved', 'status_color' => '#123456']);
        } else {
            DB::table('statuses')->where('status_name', 'Resolved')->update(['status_name' => 'Unknown legacy label']);
        }
        try {
            $this->migration()->up();
            $this->fail('The migration must not guess a workflow identity.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString("Cannot assign permanent code 'resolved'", $e->getMessage());
        }
        foreach (['departments' => 'department_code', 'divisions' => 'division_code', 'statuses' => 'status_code', 'priorities' => 'priority_code'] as $table => $column) {
            $this->assertFalse(Schema::hasColumn($table, $column));
        }
    }

    public static function invalidBackfills(): array
    {
        return ['missing name' => [false], 'duplicate name' => [true]];
    }

    #[DataProvider('codedModels')]
    public function test_model_codes_are_immutable_even_when_events_are_suppressed(string $model): void
    {
        $record = $model::firstOrFail();
        $code = $record->getAttribute($model::CODE_COLUMN);
        try {
            $record->forceFill([$model::CODE_COLUMN => 'replacement_code'])->saveQuietly();
            $this->fail('Permanent identity must not be editable.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($model::CODE_COLUMN, $e->errors());
        }
        $this->assertSame($code, $record->fresh()->getAttribute($model::CODE_COLUMN));
    }

    public static function codedModels(): array
    {
        return [[Department::class], [Division::class], [Status::class], [Priority::class]];
    }

    public function test_code_format_and_database_uniqueness_are_enforced(): void
    {
        $this->postJson(route('department.store'), ['department_name' => 'Duplicate code fixture', 'department_code' => Department::HRDO])
            ->assertUnprocessable()->assertJsonValidationErrors('department_code');
        $this->postJson(route('department.store'), ['department_name' => 'Invalid code fixture', 'department_code' => 'Invalid Code'])
            ->assertUnprocessable()->assertJsonValidationErrors('department_code');
        try {
            DB::table('priorities')->insert(['priority_name' => 'Raw duplicate fixture', 'priority_color' => '#123456', 'priority_code' => Priority::HIGH]);
            $this->fail('The database must enforce unique permanent codes.');
        } catch (QueryException $e) {
            $this->assertStringContainsString('UNIQUE', $e->getMessage());
        }
    }

    public function test_status_display_renames_preserve_creation_assignment_resolution_chat_and_reopening(): void
    {
        foreach (Status::all() as $status) {
            $status->update(['status_name' => 'Display ' . $status->status_code]);
        }
        [$department, $division] = $this->offices();
        $ticket = $this->ticket($department, $division);
        $this->assertSame(Status::idByCode(Status::UNASSIGNED), $ticket->status_id);
        $staff = User::factory()->create(['department_id' => $department->id, 'division_id' => $division->id, 'is_active' => true]);
        $staff->assignRole('hr_staff');
        $ticket->update(['assigned_to' => $staff->id]);
        $this->assertSame(Status::idByCode(Status::PENDING), $ticket->fresh()->status_id);
        $this->putJson(route('ticket.update', $ticket->id), ['_ticket_action' => 'resolve'])->assertRedirect();
        $this->assertSame(Status::idByCode(Status::RESOLVED), $ticket->fresh()->status_id);
        $this->assertNotNull($ticket->fresh()->resolved_at);
        $this->assertSame(backpack_user()->id, $ticket->fresh()->resolved_by);
        Livewire::test(TicketChat::class, ['ticketId' => $ticket->id])->set('comment', 'Must remain locked')
            ->call('sendComment')->assertHasErrors('comment');
        $this->assertSame(0, $ticket->comments()->count());
        $this->putJson(route('ticket.update', $ticket->id), ['_ticket_action' => 'reopen'])->assertRedirect();
        $this->assertSame(Status::idByCode(Status::REOPENED), $ticket->fresh()->status_id);
        $this->assertNull($ticket->fresh()->resolved_at);
        $this->assertNotNull($ticket->fresh()->reopened_at);
    }

    public function test_renamed_core_statuses_still_cannot_be_archived_deleted_or_reidentified(): void
    {
        $status = Status::where('status_code', Status::RESOLVED)->firstOrFail();
        $this->putJson(route('status.update', $status->id), ['id' => $status->id, 'status_name' => 'Completed',
            'status_color' => '#123456', 'status_code' => Status::RESOLVED])->assertRedirect();
        $this->postJson(route('status.archive', $status->id))->assertUnprocessable();
        $this->deleteJson(route('status.destroy', $status->id))->assertOk()->assertSee('Core workflow statuses');
        $this->putJson(route('status.update', $status->id), ['id' => $status->id, 'status_name' => 'Completed',
            'status_color' => '#123456', 'status_code' => 'different_identity'])->assertUnprocessable()->assertJsonValidationErrors('status_code');
        $this->assertSame($status->id, Status::idByCode(Status::RESOLVED));
        $this->assertSame('Completed', $status->fresh()->status_name);
    }

    public function test_report_counts_and_funnel_labels_follow_codes_after_renaming(): void
    {
        [$department, $division] = $this->offices();
        $ticket = $this->ticket($department, $division);
        $ticket->update(['status_id' => Status::idByCode(Status::RESOLVED)]);
        $resolved = Status::where('status_code', Status::RESOLVED)->firstOrFail();
        $resolved->update(['status_name' => 'Completed']);
        $widgets = TicketReportQueries::widgets(Ticket::query(), now()->subDay(), now()->addDay());
        $this->assertSame(1, $widgets['kpis']['resolved']);
        $this->assertSame('Completed', $widgets['funnel']['labels'][2]);
        $this->assertSame(1, $widgets['funnel']['counts'][2]);
        $this->assertSame($widgets['funnel'], TicketReportWidgets::buildStatusFunnelWidget(Ticket::all()));
        $this->get(route('page.reports.index', ['period' => 'all']))->assertOk()
            ->assertViewHas('reportStatusFunnel', fn ($funnel) => $funnel['labels'][2] === 'Completed' && $funnel['counts'][2] === 1);
    }

    public function test_office_renames_preserve_survey_exclusions_styles_and_hrdo_protection(): void
    {
        $department = Department::where('department_code', Department::HRDO)->firstOrFail();
        $department->update(['department_name' => 'Renamed HR office']);
        $head = Division::where('division_code', Division::DEPARTMENT_HEAD)->firstOrFail();
        $head->update(['division_name' => 'Renamed executive grouping']);
        $records = Division::where('division_code', Division::RECORDS)->firstOrFail();
        $records->update(['division_name' => 'Renamed records service']);
        $this->get(route('survey.form'))->assertOk()->assertDontSee('RENAMED EXECUTIVE GROUPING')
            ->assertSee('RENAMED RECORDS SERVICE')->assertSee('btn-records', false);
        $this->postJson(route('department.archive', $department->id))->assertUnprocessable()->assertSee('required by the survey');
        $this->deleteJson(route('department.destroy', $department->id))->assertOk()->assertSee('required by the survey');
        $widgets = TicketReportQueries::widgets(Ticket::query(), now()->subDay(), now()->addDay());
        $this->assertContains('Renamed records service', $widgets['reassignment']['division_labels']);
        $this->get(backpack_url('dashboard'))->assertOk()
            ->assertViewHas('divisionLabels', fn ($labels) => !in_array('Renamed executive grouping', $labels, true));
    }

    public function test_survey_department_identity_does_not_depend_on_numeric_id_one(): void
    {
        $oldDepartment = Department::where('department_code', Department::HRDO)->firstOrFail();
        $newDepartment = Department::create(['department_name' => 'Coded survey department']);
        // Simulate an installation whose HRDO has another existing ID, as a migration would preserve.
        DB::table('departments')->where('id', $oldDepartment->id)->update(['department_code' => 'former_hrdo']);
        DB::table('departments')->where('id', $newDepartment->id)->update(['department_code' => Department::HRDO]);
        Department::forgetCodeCache();
        $division = Division::create(['department_id' => $newDepartment->id, 'division_name' => 'Coded survey division']);
        $issue = Issue::create(['department_id' => $newDepartment->id, 'division_id' => $division->id, 'issue_description' => 'Coded survey service']);
        $staff = User::factory()->create(['department_id' => $newDepartment->id, 'division_id' => $division->id, 'is_active' => true]);
        $staff->assignRole('hr_staff');
        $this->assertNotSame(1, $newDepartment->id);
        $this->assertTrue(OfficeRetirementRules::isProtected($newDepartment->fresh()));
        $this->assertFalse(OfficeRetirementRules::isProtected($oldDepartment->fresh()));
        $this->postJson(route('survey.submit'), ['submission_id' => (string) Str::uuid(), 'user_id' => $staff->id,
            'issue_id' => $issue->id, 'timeliness_rating' => 'Satisfied', 'handling_rating' => 'Satisfied',
            'quality_rating' => 'Satisfied', 'overall_rating' => 'Satisfied'])->assertOk()->assertJsonPath('saved', true);
    }

    public function test_code_cache_is_reused_and_invalidated_after_save_and_delete(): void
    {
        Status::forgetCodeCache();
        DB::enableQueryLog();
        DB::flushQueryLog();
        $id = Status::idByCode(Status::RESOLVED);
        Status::idByCode(Status::PENDING);
        Status::idByCode(Status::RESOLVED);
        $this->assertCount(1, DB::getQueryLog());
        DB::disableQueryLog();
        $status = Status::findOrFail($id);
        $status->update(['status_name' => 'Completed']);
        $this->assertSame($id, Status::idByCode(Status::RESOLVED));
        $optional = Status::create(['status_code' => 'retired_optional', 'status_name' => 'Dormant workflow fixture',
            'status_color' => '#123456', 'is_active' => false]);
        $this->assertSame($optional->id, Status::idByCode('retired_optional'));
        $optional->delete();
        $this->assertNull(Status::idByCode('retired_optional'));
    }

    public function test_seeders_do_not_duplicate_or_revert_renamed_master_records(): void
    {
        $fixtures = [Department::class => 'department_name', Division::class => 'division_name',
            Status::class => 'status_name', Priority::class => 'priority_name'];
        $before = [];
        foreach ($fixtures as $model => $field) {
            $record = $model::firstOrFail();
            $record->update([$field => 'Updated ' . $record->getTable() . ' label']);
            $before[$model] = [$record->id, $record->getAttribute($field), $model::count()];
        }
        foreach ([\Database\Seeders\DepartmentTableSeeder::class, \Database\Seeders\DivisionTableSeeder::class,
            \Database\Seeders\StatusTableSeeder::class, \Database\Seeders\PriorityTableSeeder::class] as $seeder) {
            (new $seeder)->run();
        }
        foreach ($before as $model => [$id, $label, $count]) {
            $this->assertSame($label, $model::findOrFail($id)->getAttribute($fixtures[$model]));
            $this->assertSame($count, $model::count());
        }
    }

    public function test_automatic_assignment_and_priority_defaults_survive_label_changes(): void
    {
        foreach (Status::all() as $status) $status->update(['status_name' => 'Display ' . $status->status_code]);
        $priority = Priority::where('priority_code', Priority::HIGH)->firstOrFail();
        $priority->update(['priority_name' => 'Urgent']);
        [$department, $division] = $this->offices();
        $staff = User::factory()->create(['department_id' => $department->id, 'division_id' => $division->id, 'is_active' => true]);
        $staff->assignRole('hr_staff');
        $issue = Issue::create(['department_id' => $department->id, 'division_id' => $division->id,
            'issue_description' => 'Assignment after rename', 'priority_id' => $priority->id]);
        $ticket = Ticket::create(['user_id' => backpack_user()->id, 'issue_id' => $issue->id,
            'is_custom_issue' => false, 'message' => 'Automatic assignment after label changes']);
        $this->assertSame($staff->id, $ticket->fresh()->assigned_to);
        $this->assertSame(Status::idByCode(Status::PENDING), $ticket->fresh()->status_id);
        $this->assertSame($priority->id, $ticket->fresh()->priority_id);
    }

    public function test_renamed_resolved_status_still_allows_office_retirement_and_protects_reopening(): void
    {
        [$department, $division] = $this->offices();
        $ticket = $this->ticket($department, $division);
        $ticket->update(['status_id' => Status::idByCode(Status::RESOLVED)]);
        Status::findOrFail($ticket->status_id)->update(['status_name' => 'Completed']);
        $this->postJson(route('division.archive', $division->id))->assertOk();
        $this->postJson(route('department.archive', $department->id))->assertOk();
        $this->putJson(route('ticket.update', $ticket->id), ['_ticket_action' => 'reopen'])->assertUnprocessable();
        $this->assertSame(Status::idByCode(Status::RESOLVED), $ticket->fresh()->status_id);
        $this->assertSame($division->id, $ticket->fresh()->division_id);
    }

    public function test_workflow_does_not_depend_on_the_core_status_numeric_ids(): void
    {
        $originalIds = Status::pluck('id')->all();
        foreach (Status::all() as $status) {
            // Represent another installation where unrelated rows were inserted before the core workflow.
            DB::table('statuses')->where('id', $status->id)->update([
                'status_code' => 'legacy_status_' . $status->id, 'status_name' => 'Legacy ' . $status->status_name,
            ]);
            Status::create(['status_code' => $status->status_code, 'status_name' => 'Display ' . $status->status_code,
                'status_color' => $status->status_color]);
        }
        Status::forgetCodeCache();
        [$department, $division] = $this->offices();
        $ticket = $this->ticket($department, $division);
        $this->assertNotContains($ticket->status_id, $originalIds);
        $this->assertSame(Status::idByCode(Status::UNASSIGNED), $ticket->status_id);
        $this->putJson(route('ticket.update', $ticket->id), ['_ticket_action' => 'resolve'])->assertRedirect();
        $this->assertNotContains($ticket->fresh()->status_id, $originalIds);
        $this->assertSame(Status::idByCode(Status::RESOLVED), $ticket->fresh()->status_id);
        $this->assertNotNull($ticket->fresh()->resolved_at);
    }
}
