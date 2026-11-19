<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class SanctumUpgradeTest extends TestCase
{
    public function test_personal_access_token_can_be_created_and_authenticate_the_existing_api(): void
    {
        $user = User::role('admin')->firstOrFail();
        $token = $user->createToken('Upgrade regression', ['*'], now()->addHour());
        $this->assertNotNull($token->accessToken->expires_at);
        $this->withToken($token->plainTextToken)->getJson('/api/user')->assertOk()->assertJsonPath('id', $user->id);
    }

    public function test_expired_personal_access_token_cannot_authenticate(): void
    {
        $user = User::role('admin')->firstOrFail();
        $token = $user->createToken('Expired upgrade regression', ['*'], now()->subMinute());
        $this->withToken($token->plainTextToken)->getJson('/api/user')->assertUnauthorized();
    }
}
