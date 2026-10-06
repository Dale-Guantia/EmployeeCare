<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Survey;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class SurveyPwaTest extends TestCase
{
    private $staff;
    private $service;
    private $division;
    private $otherDivision;
    private $otherDepartment;

    protected function setUp(): void
    {
        parent::setUp();

        // These tests never migrate, seed, or write the application's configured database.
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null,
            'cache.default' => 'array',
            'session.driver' => 'array',
        ]);
        if (! extension_loaded('pdo_sqlite')) {
            $this->markTestSkipped('Run with php -d extension=pdo_sqlite to use the isolated in-memory database.');
        }
        DB::purge('sqlite');

        $migrations = [
            '2026_02_24_022453_create_departments_table.php' => 'CreateDepartmentsTable',
            '2026_02_24_022509_create_divisions_table.php' => 'CreateDivisionsTable',
            '2026_02_24_022107_create_priorities_table.php' => 'CreatePrioritiesTable',
            '2026_02_24_022528_create_issues_table.php' => 'CreateIssuesTable',
            '2026_02_24_022530_create_users_table.php' => 'CreateUsersTable',
            '2026_02_23_070500_create_permission_tables.php' => 'CreatePermissionTables',
        ];
        foreach ($migrations as $file => $class) {
            require_once database_path('migrations/' . $file);
            (new $class)->up();
        }
        (require database_path('migrations/2026_03_05_074049_surveys_table.php'))->up();

        $department = Department::create(['department_name' => 'City Human Resource Development Office']);
        $this->assertSame(1, $department->id);
        $this->otherDepartment = Department::create(['department_name' => 'Other department']);
        // Deliberately use different IDs from the old hard-coded buttons.
        $this->division = Division::create(['department_id' => 1, 'division_name' => 'Records']);
        $this->otherDivision = Division::create(['department_id' => 1, 'division_name' => 'Payroll']);
        Role::findOrCreate('hr_staff', 'web');
        Role::findOrCreate('div_head', 'web');
        Role::findOrCreate('employee', 'web');
        $this->staff = $this->makeStaff(1, $this->division->id);
        $this->service = $this->makeService(1, $this->division->id);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function makeStaff($departmentId, $divisionId, $role = 'hr_staff')
    {
        $user = User::create([
            'name' => 'Survey staff',
            'username' => 'survey_' . uniqid(),
            'email' => uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'department_id' => $departmentId,
            'division_id' => $divisionId,
        ]);
        $user->assignRole($role);
        return $user;
    }

    private function makeService($departmentId, $divisionId)
    {
        return Issue::create([
            'department_id' => $departmentId,
            'division_id' => $divisionId,
            'issue_description' => 'Survey service',
            'icon' => 'fas fa-file',
        ]);
    }

    private function payload()
    {
        return [
            'user_id' => $this->staff->id,
            'issue_id' => $this->service->id,
            'timeliness_rating' => 'Very Satisfied',
            'handling_rating' => 'Satisfied',
            'quality_rating' => 'Satisfied',
            'overall_rating' => 'Very Satisfied',
        ];
    }

    /** @dataProvider invalidCombinations */
    public function test_crafted_submissions_are_rejected($scenario, $errorKey)
    {
        $payload = $this->payload();
        switch ($scenario) {
            case 'different_division':
                $payload['issue_id'] = $this->makeService(1, $this->otherDivision->id)->id;
                break;
            case 'other_department_staff':
                $payload['user_id'] = $this->makeStaff($this->otherDepartment->id, $this->division->id)->id;
                break;
            case 'other_department_service':
                $payload['issue_id'] = $this->makeService($this->otherDepartment->id, $this->division->id)->id;
                break;
            case 'non_staff_role':
                $payload['user_id'] = $this->makeStaff(1, $this->division->id, 'employee')->id;
                break;
            case 'invalid_division_department':
                $foreignDivision = Division::create([
                    'department_id' => $this->otherDepartment->id, 'division_name' => 'Foreign division',
                ]);
                $payload['user_id'] = $this->makeStaff(1, $foreignDivision->id)->id;
                $payload['issue_id'] = $this->makeService(1, $foreignDivision->id)->id;
                break;
            case 'missing_staff_division':
                $payload['user_id'] = $this->makeStaff(1, null)->id;
                break;
            case 'nonexistent_staff':
                $payload['user_id'] = 999999;
                break;
            case 'nonexistent_service':
                $payload['issue_id'] = 999999;
                break;
            case 'array_staff':
                $payload['user_id'] = [$this->staff->id];
                break;
            case 'invalid_rating':
                $payload[$errorKey] = 'Not a rating';
                break;
        }

        $this->post(route('survey.submit'), $payload)
            ->assertRedirect(route('survey.form'))->assertSessionHasErrors($errorKey);
        $this->assertSame(0, Survey::count());
        $this->postJson(route('survey.submit'), $payload)
            ->assertStatus(422)->assertJsonValidationErrors($errorKey)->assertJson(['saved' => false]);
        $this->assertSame(0, Survey::count());
    }

    public function invalidCombinations()
    {
        return [
            ['different_division', 'issue_id'],
            ['other_department_staff', 'user_id'],
            ['other_department_service', 'issue_id'],
            ['non_staff_role', 'user_id'],
            ['invalid_division_department', 'user_id'],
            ['missing_staff_division', 'user_id'],
            ['nonexistent_staff', 'user_id'],
            ['nonexistent_service', 'issue_id'],
            ['array_staff', 'user_id'],
            ['invalid_rating', 'timeliness_rating'],
            ['invalid_rating', 'handling_rating'],
            ['invalid_rating', 'quality_rating'],
            ['invalid_rating', 'overall_rating'],
        ];
    }

    public function test_valid_submission_and_duplicate_window_are_preserved()
    {
        Carbon::setTestNow(Carbon::parse('2026-10-05 12:00:00'));
        $this->post(route('survey.submit'), $this->payload())
            ->assertRedirect(route('survey.form') . '?thank_you=1')->assertSessionHas('success');
        $this->assertSame(1, Survey::count());
        $this->assertSame($this->staff->id, (int) Survey::first()->user_id);

        Carbon::setTestNow(now()->addSeconds(5));
        $this->post(route('survey.submit'), $this->payload())->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Survey::count());

        Carbon::setTestNow(now()->addSeconds(6));
        $this->post(route('survey.submit'), $this->payload())->assertSessionDoesntHaveErrors();
        $this->assertSame(2, Survey::count());
    }

    public function test_public_form_uses_database_divisions_and_only_marks_normal_pages_cacheable()
    {
        $response = $this->get(route('survey.form'));
        $response->assertOk()->assertHeader('X-Survey-Cacheable', '1');
        $response->assertSee('data-division-id="' . $this->division->id . '"', false);
        $response->assertSee('RECORDS')->assertSee('PAYROLL');
        $this->assertLessThan(strpos($response->getContent(), '>PAYROLL</button>'), strpos($response->getContent(), '>RECORDS</button>'));

        $this->division->update(['division_name' => 'Renamed division']);
        $this->get(route('survey.form'))->assertSee('RENAMED DIVISION');
        $this->get(route('survey.form') . '?thank_you=1')->assertHeader('X-Survey-Cacheable', '0');
        $this->get(route('survey.form') . '?other_state=1')->assertHeader('X-Survey-Cacheable', '0');
        $this->withSession(['success' => 'Thank you for your feedback!'])
            ->get(route('survey.form'))->assertHeader('X-Survey-Cacheable', '0')->assertSee('success-message');
    }

    public function test_validation_error_pages_are_visible_and_not_cacheable()
    {
        $errors = new ViewErrorBag();
        $errors->put('default', new MessageBag(['issue_id' => 'Please select a valid service.']));
        $this->withSession(['errors' => $errors])->get(route('survey.form'))
            ->assertOk()->assertSee('Please select a valid service.')->assertHeader('X-Survey-Cacheable', '0');
    }

    public function test_token_refresh_uses_current_session_and_cannot_be_http_cached()
    {
        $response = $this->withSession(['_token' => 'current-session-token'])->getJson(route('survey.csrf'));
        $response->assertOk()->assertJson(['token' => 'current-session-token']);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_csrf_middleware_rejects_old_token_and_accepts_refreshed_token()
    {
        // Laravel normally skips CSRF inside PHPUnit; enforce the production middleware here.
        $this->app['env'] = 'local';
        $this->withSession(['_token' => 'current-session-token'])
            ->post(route('survey.submit'), array_merge($this->payload(), ['_token' => 'old-token']))
            ->assertStatus(419);
        $this->assertSame(0, Survey::count());

        $token = $this->getJson(route('survey.csrf'))->assertOk()->json('token');
        $this->post(route('survey.submit'), array_merge($this->payload(), ['_token' => $token]))
            ->assertRedirect(route('survey.form') . '?thank_you=1');
        $this->assertSame(1, Survey::count());
    }

    public function test_division_heads_remain_eligible_for_valid_submissions()
    {
        $head = $this->makeStaff(1, $this->division->id, 'div_head');
        $this->post(route('survey.submit'), array_merge($this->payload(), ['user_id' => $head->id]))
            ->assertSessionDoesntHaveErrors();
        $this->assertSame(1, Survey::count());
    }

    public function test_json_submission_confirms_save_without_redirect_or_success_flash()
    {
        $this->postJson(route('survey.submit'), $this->payload())
            ->assertOk()->assertJson(['saved' => true, 'message' => 'Thank you for your feedback!'])
            ->assertSessionMissing('success');
        $this->assertSame(1, Survey::count());
        $this->postJson(route('survey.submit'), $this->payload())->assertOk()->assertJson(['saved' => true]);
        $this->assertSame(1, Survey::count());
        $this->get(route('survey.form'))->assertHeader('X-Survey-Cacheable', '1');
    }

    public function test_json_submission_still_requires_current_csrf_token()
    {
        $this->app['env'] = 'local';
        $this->withSession(['_token' => 'current-session-token'])
            ->postJson(route('survey.submit'), array_merge($this->payload(), ['_token' => 'old-token']))
            ->assertStatus(419);
        $this->assertSame(0, Survey::count());
        $token = $this->getJson(route('survey.csrf'))->assertOk()->json('token');
        $response = $this->postJson(route('survey.submit'), array_merge($this->payload(), ['_token' => $token]))
            ->assertOk()->assertJson(['saved' => true]);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->assertSame(1, Survey::count());
    }
}
