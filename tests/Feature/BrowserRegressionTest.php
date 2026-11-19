<?php

namespace Tests\Feature;

use App\Models\HrPolicyDocument;
use App\Models\User;
use Tests\TestCase;

class BrowserRegressionTest extends TestCase
{
    public function test_ticket_validation_names_the_issue_and_details_in_plain_language(): void
    {
        $this->actingAs(User::role('employee')->firstOrFail(), 'web');
        $this->postJson(backpack_url('ticket'), ['is_custom_issue' => 0])->assertUnprocessable()
            ->assertJsonPath('errors.issue_id.0', 'Please select an issue or problem, or choose to describe a custom issue.')
            ->assertJsonPath('errors.message.0', 'Please provide details so our team can help with your concern.');
    }

    public function test_policy_list_exposes_automatic_refresh_and_current_processing_counts(): void
    {
        HrPolicyDocument::create(['title' => 'Queued fixture', 'filename' => 'fixture.pdf', 'file_path' => 'policies/fixture.pdf',
            'category' => 'general', 'status' => 'pending', 'is_active' => false]);
        $this->actingAs(User::role('admin')->firstOrFail(), 'web');
        $this->get(backpack_url('hr-policy-documents'))->assertOk()->assertSee('policy-status.js', false)
            ->assertSee('data-pending="1"', false);
        $this->getJson(route('hr.policy.ingestion_status'))->assertOk()->assertJsonPath('pending', 1)->assertJsonPath('processing', 0)
            ->assertHeader('Cache-Control', 'no-store, private');
        HrPolicyDocument::query()->update(['status' => 'active']);
        $this->getJson(route('hr.policy.ingestion_status'))->assertOk()->assertJsonPath('pending', 0);
    }

    public function test_policy_processing_status_is_not_available_to_employees(): void
    {
        $this->actingAs(User::role('employee')->firstOrFail(), 'web');
        $this->getJson(route('hr.policy.ingestion_status'))->assertForbidden();
    }

    public function test_selected_attachment_preview_contains_a_working_removal_control(): void
    {
        $this->actingAs(User::role('employee')->firstOrFail(), 'web');
        $this->get(backpack_url('ticket/create'))->assertOk()
            ->assertSee('data-remove-new-file', false)
            ->assertDontSee("item.querySelector('.close')", false);
    }
}
