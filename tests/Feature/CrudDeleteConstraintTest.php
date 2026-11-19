<?php

namespace Tests\Feature;

use App\Exceptions\Handler;
use App\Http\Controllers\Admin\PriorityCrudController;
use App\Models\Department;
use App\Models\Division;
use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PDOException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrudDeleteConstraintTest extends TestCase
{
    private const MESSAGE = 'Cannot delete this record because other records still use it. Reassign or remove those references first, then try again.';

    private function priority(): Priority
    {
        return Priority::create(['priority_name' => 'Urgent fixture', 'priority_color' => '#ff0000']);
    }

    private function ticket(Priority $priority): Ticket
    {
        return Ticket::withoutEvents(fn () => Ticket::create([
            'reference_id' => 'DELETE-CONSTRAINT',
            'user_id' => User::role('employee')->firstOrFail()->id,
            'status_id' => Status::firstOrFail()->id,
            'priority_id' => $priority->id,
            'message' => 'Deletion fixture',
        ]));
    }

    public function test_deleting_a_referenced_priority_preserves_its_issue_reference(): void
    {
        $priority = $this->priority();
        $issue = Issue::firstOrFail();
        $issue->update(['priority_id' => $priority->id]);

        $this->actingAs(User::role('admin')->firstOrFail(), 'web')
            ->delete(route('priority.destroy', $priority->id), [], ['X-Requested-With' => 'XMLHttpRequest'])
            ->assertOk()->assertJsonStructure(['error']);

        $this->assertNotNull($priority->fresh());
        $this->assertSame($priority->id, $issue->fresh()->priority_id);
    }

    public function test_deleting_a_referenced_priority_preserves_its_ticket_reference(): void
    {
        $priority = $this->priority();
        $ticket = $this->ticket($priority);

        $this->actingAs(User::role('admin')->firstOrFail(), 'web')
            ->deleteJson(route('priority.destroy', $priority->id))
            ->assertOk()->assertJsonStructure(['error']);

        $this->assertNotNull($priority->fresh());
        $this->assertSame($priority->id, $ticket->fresh()->priority_id);
    }

    public function test_unused_priority_can_still_be_deleted(): void
    {
        $priority = $this->priority();
        $priority->update(['is_active' => false]);
        $this->actingAs(User::role('admin')->firstOrFail(), 'web')
            ->deleteJson(route('priority.destroy', $priority->id))->assertOk()->assertContent('1');
        $this->assertNull($priority->fresh());
    }

    public function test_other_referenced_crud_records_are_retained_with_the_same_notification(): void
    {
        $issue = Issue::firstOrFail();
        $ticket = $this->ticket($this->priority());
        $records = [
            'department' => Department::findOrFail($issue->department_id),
            'division' => Division::findOrFail($issue->division_id),
            'status' => Status::findOrFail($ticket->status_id),
            'user' => User::findOrFail($ticket->user_id),
        ];
        // Reference the issue from a ticket so its deletion is blocked too.
        $ticket->updateQuietly(['issue_id' => $issue->id]);
        $records['issue'] = $issue;
        $user = $records['user'];
        $user->givePermissionTo('ticket.view');
        $roles = $user->roles()->pluck('id')->all();
        $permissions = $user->permissions()->pluck('id')->all();
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');

        foreach ($records as $entity => $record) {
            $message = $entity === 'department' && (int) $record->id === 1
                ? 'This department is required by the survey and cannot be deleted.' : self::MESSAGE;
            $response = $this->deleteJson(route($entity . '.destroy', $record->id))->assertOk()->assertJsonStructure(['error']);
            if ($entity === 'user') $response->assertExactJson(['error' => [$message]]);
            $this->assertNotNull($record->fresh(), $entity . ' must remain intact.');
        }
        $this->assertNotNull($ticket->fresh());
        $this->assertSame($roles, $user->fresh()->roles()->pluck('id')->all());
        $this->assertSame($permissions, $user->fresh()->permissions()->pluck('id')->all());
    }

    public function test_delete_permission_is_still_required(): void
    {
        $priority = $this->priority();
        $this->actingAs(User::role('employee')->firstOrFail(), 'web')
            ->deleteJson(route('priority.destroy', $priority->id))->assertForbidden();
        $this->assertNotNull($priority->fresh());
    }

    public function test_unused_user_and_role_assignments_can_still_be_deleted(): void
    {
        $user = User::create([
            'name' => 'Unused user', 'username' => 'unused-delete-fixture',
            'email' => 'unused-delete@example.test', 'password' => bcrypt('fixture'), 'is_active' => true,
        ]);
        $user->assignRole('employee');
        $user->givePermissionTo('ticket.view');

        $this->actingAs(User::role('admin')->firstOrFail(), 'web')
            ->deleteJson(route('user.destroy', $user->id))->assertOk()->assertContent('1');

        $this->assertNull($user->fresh());
        $this->assertSame(0, $user->roles()->count());
        $this->assertSame(0, $user->permissions()->count());
    }

    #[DataProvider('databaseErrors')]
    public function test_only_foreign_key_delete_conflicts_use_the_notification(
        array $errorInfo, string $sql, string $method, bool $crudRoute, bool $handled
    ): void {
        config(['app.debug' => false]);
        $previous = new PDOException('Database error fixture');
        $previous->errorInfo = $errorInfo;
        $exception = new QueryException('fixture', $sql, [], $previous);
        $request = Request::create('/fixture/1', $method, [], [], [], ['HTTP_ACCEPT' => 'application/json']);
        $route = new Route([$method], 'fixture/{id}', [
            'uses' => PriorityCrudController::class . '@destroy',
            'operation' => $crudRoute ? 'delete' : 'update',
        ]);
        $request->setRouteResolver(fn () => $route);

        $response = app(Handler::class)->render($request, $exception);

        $this->assertSame($handled ? 200 : 500, $response->getStatusCode());
        $this->assertSame(
            $handled ? ['error' => [self::MESSAGE]] : ['message' => 'Server Error'],
            json_decode($response->getContent(), true)
        );
    }

    public static function databaseErrors(): array
    {
        $sqlServer = ['23000', 547, 'The DELETE statement conflicted with the REFERENCE constraint "issues_priority_id_foreign".'];
        return [
            'SQL Server reference' => [$sqlServer, 'delete from [priorities] where [id] = ?', 'DELETE', true, true],
            'SQL Server foreign key' => [['23000', 547, 'The DELETE statement conflicted with the FOREIGN KEY constraint.'], 'delete from [priorities]', 'DELETE', true, true],
            'MySQL reference' => [['23000', 1451, 'Cannot delete or update a parent row: a foreign key constraint fails'], 'delete from `priorities`', 'DELETE', true, true],
            'SQLite reference' => [['23000', 19, 'FOREIGN KEY constraint failed'], 'delete from "priorities"', 'DELETE', true, true],
            'SQL Server CHECK' => [['23000', 547, 'The DELETE statement conflicted with the CHECK constraint.'], 'delete from [priorities]', 'DELETE', true, false],
            'duplicate key' => [['23000', 2627, 'Violation of UNIQUE KEY constraint.'], 'delete from [priorities]', 'DELETE', true, false],
            'unrelated query failure' => [['08001', 0, 'Connection failed'], 'delete from [priorities]', 'DELETE', true, false],
            'insert failure during delete' => [$sqlServer, 'insert into [priorities] values (?)', 'DELETE', true, false],
            'update request' => [$sqlServer, 'delete from [priorities]', 'PUT', true, false],
            'non CRUD route' => [$sqlServer, 'delete from [priorities]', 'DELETE', false, false],
        ];
    }
}
