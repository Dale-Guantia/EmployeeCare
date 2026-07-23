<?php

namespace Tests\Feature;

use App\Http\Requests\DepartmentRequest;
use App\Http\Requests\PriorityRequest;
use App\Http\Requests\StatusRequest;
use App\Models\Department;
use App\Models\Priority;
use App\Models\Status;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Regression test for a real bug: DepartmentRequest/PriorityRequest/StatusRequest's
 * `unique` rules were built with $this->route('department')/('priority')/('status'),
 * but Backpack's update route parameter is literally named {id} (confirmed via
 * `php artisan route:list`) — so the "ignore the current row" id was always null,
 * and saving an update with an unchanged name (e.g. editing only the color) was
 * rejected as a duplicate. Fixed by using $this->route('id') instead.
 *
 * Tests the FormRequest's rules() directly with a stub route resolver, rather
 * than a full HTTP PUT — Backpack's CRUD update route reliably 404s inside this
 * app's PHPUnit test client for every entity (verified independent of this fix,
 * reproduces even for pre-existing seeded records), a pre-existing test-harness
 * limitation unrelated to this bug or its fix.
 */
class CrudRequestUniqueIgnoreSelfTest extends TestCase
{
    use DatabaseTransactions;

    private function rulesWithRouteId(string $requestClass, $id): array
    {
        $request = new $requestClass();
        $request->setRouteResolver(function () use ($id) {
            return new class($id) {
                private $id;
                public function __construct($id) { $this->id = $id; }
                public function parameter($name, $default = null) { return $name === 'id' ? $this->id : $default; }
            };
        });

        return $request->rules();
    }

    public function test_status_request_ignores_its_own_id_on_update()
    {
        $status = Status::create(['status_name' => 'IgnoreSelfTest_' . uniqid(), 'status_color' => '#111111']);

        $rules = $this->rulesWithRouteId(StatusRequest::class, $status->id);

        $validator = Validator::make([
            'status_name' => $status->status_name,
            'status_color' => '#ffcc00',
        ], $rules);

        $this->assertFalse($validator->fails(), 'Unchanged status_name should not fail uniqueness when updating the same row: ' . json_encode($validator->errors()->toArray()));
    }

    public function test_priority_request_ignores_its_own_id_on_update()
    {
        $priority = Priority::create(['priority_name' => 'IgnoreSelfPriorityTest_' . uniqid(), 'priority_color' => '#111111']);

        $rules = $this->rulesWithRouteId(PriorityRequest::class, $priority->id);

        $validator = Validator::make([
            'priority_name' => $priority->priority_name,
            'priority_color' => '#ffcc00',
        ], $rules);

        $this->assertFalse($validator->fails(), 'Unchanged priority_name should not fail uniqueness when updating the same row: ' . json_encode($validator->errors()->toArray()));
    }

    public function test_department_request_ignores_its_own_id_on_update()
    {
        $department = Department::create(['department_name' => 'IgnoreSelfDeptTest_' . uniqid()]);

        $rules = $this->rulesWithRouteId(DepartmentRequest::class, $department->id);

        $validator = Validator::make([
            'department_name' => $department->department_name,
        ], $rules);

        $this->assertFalse($validator->fails(), 'Unchanged department_name should not fail uniqueness when updating the same row: ' . json_encode($validator->errors()->toArray()));
    }

    public function test_status_request_still_rejects_a_real_duplicate_name()
    {
        $statusA = Status::create(['status_name' => 'DupCheckA_' . uniqid(), 'status_color' => '#111111']);
        $statusB = Status::create(['status_name' => 'DupCheckB_' . uniqid(), 'status_color' => '#222222']);

        $rules = $this->rulesWithRouteId(StatusRequest::class, $statusB->id);

        $validator = Validator::make([
            'status_name' => $statusA->status_name,
            'status_color' => '#ffcc00',
        ], $rules);

        $this->assertTrue($validator->fails(), 'Renaming status B to status A\'s existing name must still fail uniqueness.');
    }
}
