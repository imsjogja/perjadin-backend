<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_obtain_sanctum_token_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@example.test',
            'password' => 'password',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $response->assertOk()
            ->assertJsonPath('data.id', $user->id)
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonStructure(['access_token']);

        $this->assertDatabaseCount('personal_access_tokens', 1);
    }

    public function test_user_can_keep_active_sessions_on_multiple_devices(): void
    {
        $user = User::factory()->create([
            'email' => 'operator@example.test',
            'password' => 'password',
        ]);

        $firstLogin = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();
        $secondLogin = $this->postJson('/api/v1/auth/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 2);

        $this->withToken((string) $firstLogin->json('access_token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
        $this->withToken((string) $secondLogin->json('access_token'))
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.id', $user->id);
    }
}
