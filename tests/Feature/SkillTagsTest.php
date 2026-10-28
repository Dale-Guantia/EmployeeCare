<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SkillTagsTest extends TestCase
{
    public function test_skill_values_round_trip_as_strings_and_blade_escapes_saved_inputs()
    {
        $user = User::role('hr_staff')->first();
        $skills = ['<img src=x onerror="window.skillInjected=1">', "it's [quoted] \"skill\"", '日本語 résumé'];
        $avatar = $user->avatar_url;
        $this->actingAs($user, 'web')->post('/employee-care/my-account/info', [
            'name' => $user->name, 'username' => $user->username,
            backpack_authentication_column() => $user->{backpack_authentication_column()}, 'skills' => $skills,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($skills, $user->fresh()->skills);
        $this->assertSame($avatar, $user->fresh()->avatar_url);
        $response = $this->get('/employee-care/my-account')->assertOk();
        foreach ($skills as $skill) { $response->assertSee('value="' . e($skill) . '"', false); }
        $response->assertDontSee('value="' . $skills[0] . '"', false);
        $this->post('/employee-care/my-account/info', ['name' => $user->name, 'username' => $user->username,
            backpack_authentication_column() => $user->{backpack_authentication_column()}, 'skills' => []])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame([], $user->fresh()->skills);
    }

    public function test_existing_skill_validation_rejects_non_strings_and_oversized_values()
    {
        $user = User::role('hr_staff')->first();
        $this->actingAs($user, 'web')->post('/employee-care/my-account/info', [
            'name' => $user->name, 'username' => $user->username,
            backpack_authentication_column() => $user->{backpack_authentication_column()}, 'skills' => [['bad'], str_repeat('x', 101)],
        ])->assertRedirect()->assertSessionHasErrors(['skills.0', 'skills.1']);
    }
}
