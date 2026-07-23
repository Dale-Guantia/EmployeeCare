<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class CrudAuthorizationGatesTest extends TestCase
{
    use DatabaseTransactions;

    private function makeUser(string $role, ?int $departmentId = null, ?int $divisionId = null): User
    {
        static $seq = 0;
        $seq++;

        $user = User::create([
            'name' => ucfirst($role) . "GateUser{$seq}",
            'username' => "{$role}_gate_user_{$seq}_" . uniqid(),
            'email' => "{$role}_gate_user_{$seq}_" . uniqid() . '@example.test',
            'password' => bcrypt('password'),
            'department_id' => $departmentId,
            'division_id' => $divisionId,
            'is_active' => true,
        ]);
        $user->assignRole($role);

        return $user;
    }

    public function test_dept_head_can_view_departments_index()
    {
        $hrDept = Department::find(1);
        $deptHead = $this->makeUser('dept_head', $hrDept->id);
        $this->actingAs($deptHead, 'web');

        $this->get(route('department.index'))->assertStatus(200);
    }

    public function test_dept_head_cannot_create_departments()
    {
        $hrDept = Department::find(1);
        $deptHead = $this->makeUser('dept_head', $hrDept->id);
        $this->actingAs($deptHead, 'web');

        $this->get(route('department.create'))->assertStatus(403);
    }

    public function test_dept_head_cannot_store_departments()
    {
        $hrDept = Department::find(1);
        $deptHead = $this->makeUser('dept_head', $hrDept->id);
        $this->actingAs($deptHead, 'web');

        $this->post(route('department.store'), ['department_name' => 'Nope'])->assertStatus(403);
    }

    public function test_dept_head_can_store_issues_but_not_delete()
    {
        // Note: issue.create (GET, the form view) 500s for every role, including
        // admin, due to a pre-existing, unrelated Backpack field-config bug
        // ("Cannot find 'relationship' field view") in IssueCrudController's
        // department_id/division_id fields. That is not one of the 37 approved
        // audit findings, so it is left untouched here — this test exercises
        // the store/destroy authorization gates directly instead of the
        // broken create view.
        $hrDept = Department::find(1);
        $division = Division::where('department_id', $hrDept->id)->first();
        $priority = Priority::firstOrCreate(['priority_name' => 'Normal'], ['priority_color' => '#ccc']);
        $deptHead = $this->makeUser('dept_head', $hrDept->id);
        $this->actingAs($deptHead, 'web');

        $this->post(route('issue.store'), [
            'department_id' => $hrDept->id,
            'division_id' => $division->id,
            'priority_id' => $priority->id,
            'issue_description' => 'Gate Test Issue ' . uniqid(),
        ])->assertStatus(302);

        $issue = Issue::latest('id')->first();

        $this->delete(route('issue.destroy', $issue->id))->assertStatus(403);
    }

    public function test_admin_retains_full_department_access()
    {
        $admin = User::role('admin')->first();
        $this->assertNotNull($admin);
        $this->actingAs($admin, 'web');

        $this->get(route('department.create'))->assertStatus(200);
    }

    public function test_div_head_still_blocked_from_departments_entirely()
    {
        $hrDept = Department::find(1);
        $division = Division::where('department_id', $hrDept->id)->first();
        $divHead = $this->makeUser('div_head', $hrDept->id, $division->id);
        $this->actingAs($divHead, 'web');

        $this->get(route('department.index'))->assertStatus(403);
    }
}
