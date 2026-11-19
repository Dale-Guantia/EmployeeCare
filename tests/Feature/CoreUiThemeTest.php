<?php

namespace Tests\Feature;

use App\Models\Issue;
use App\Models\Priority;
use App\Models\Status;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CoreUiThemeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Cache only test assets on the fake Basset disk; never fetch external services.
        Http::fake([
            'cdn.datatables.net/*' => Http::response('/* isolated table asset */'),
            'cdn.jsdelivr.net/*' => Http::response('/* isolated theme asset */'),
            'unpkg.com/*' => Http::response('/* isolated theme asset */'),
            'cdnjs.cloudflare.com/*' => Http::response('/* isolated theme asset */'),
        ]);
    }

    public function test_custom_admin_pages_render_bootstrap_five_controls(): void
    {
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        foreach (['dashboard', 'reports', 'survey-reports', 'arta-survey-reports', 'notifications', 'my-account', 'hr-assistant'] as $page) {
            \Backpack\Basset\Facades\Basset::clearLoadedAssets();
            $response = $this->get(backpack_url($page))->assertOk();
            $html = $response->getContent();
            $this->assertStringContainsString('coreui4.css', $html, $page);
            $this->assertDoesNotMatchRegularExpression('/\bdata-(?:toggle|target|dismiss)\s*=/', $html, $page);
            $this->assertStringNotContainsString('bootstrap4', $html, $page);
            $this->assertStringContainsString('data-bs-toggle="dropdown"', $html, $page);
        }
    }

    public function test_ticket_actions_and_livewire_survive_the_theme_change(): void
    {
        $admin = User::role('admin')->firstOrFail();
        $ticket = Ticket::withoutEvents(fn () => Ticket::create([
            'reference_id' => 'THEME-TEST', 'user_id' => $admin->id,
            'department_id' => $admin->department_id, 'division_id' => $admin->division_id,
            'status_id' => Status::idByCode(Status::UNASSIGNED), 'is_custom_issue' => true,
            'custom_issue' => 'Theme test', 'message' => 'Isolated theme fixture',
        ]));
        $response = $this->actingAs($admin, 'web')->get(backpack_url("ticket/{$ticket->id}/show"))->assertOk();
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/data-bs-target=[\'"]#resolveModal' . $ticket->id . '[\'"]/', $html);
        $this->assertDoesNotMatchRegularExpression('/\bdata-(?:toggle|target|dismiss)\s*=/', $html);
        $this->assertStringContainsString('wire:snapshot=', $html);
        $this->assertStringContainsString('data-update-uri=', $html);
        $this->assertStringContainsString('window.hrfInitTomSelect', $html);
    }

    public function test_icon_choices_render_and_the_selected_value_is_saved(): void
    {
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        $this->get(backpack_url('issue/create'))->assertOk()
            ->assertSee('data-icon-picker', false)
            ->assertSee('fas fa-balance-scale', false)
            ->assertSee('fab fa-github', false)
            ->assertDontSee('bootstrap-iconpicker', false);
        $source = Issue::firstOrFail();
        $this->post(backpack_url('issue'), [
            'department_id' => $source->department_id, 'division_id' => $source->division_id,
            'priority_id' => Priority::firstOrFail()->id, 'issue_description' => 'Theme icon persistence',
            'icon' => 'fas fa-balance-scale', '_save_action' => 'save_and_back',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseHas('issues', ['issue_description' => 'Theme icon persistence', 'icon' => 'fas fa-balance-scale']);
    }

    public function test_employee_menu_keeps_permission_boundaries(): void
    {
        $this->actingAs(User::role('employee')->firstOrFail(), 'web');
        $this->get(route('submit-ticket.show'))->assertOk()
            ->assertSee('coreui4.css', false)
            ->assertSee('Create Ticket')
            ->assertDontSee(backpack_url('user'), false)
            ->assertDontSee(backpack_url('permission'), false);
        $this->get(backpack_url('user'))->assertForbidden();
    }

    public function test_livewire_script_uses_the_registered_route_with_debug_disabled(): void
    {
        $this->app['config']->set('app.debug', false);
        app(\Livewire\Mechanisms\FrontendAssets\FrontendAssets::class)->setScriptRoute(
            fn ($handler) => \Illuminate\Support\Facades\Route::get('/livewire/livewire.min.js', $handler)
        );
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        $this->get(backpack_url('ticket'))->assertOk()->assertSee('src="/livewire/livewire.min.js?', false);
        $this->get('/livewire/livewire.min.js')->assertOk()->assertHeader('Content-Type', 'application/javascript; charset=utf-8');
    }
}
