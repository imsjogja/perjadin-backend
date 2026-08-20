<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_administrator_can_manage_roles_and_users(): void
    {
        $administrator = Role::query()->create([
            'slug' => 'administrator',
            'name' => 'Administrator',
            'permissions' => ['*'],
        ]);
        $operator = Role::query()->create([
            'slug' => 'operator',
            'name' => 'Operator',
            'permissions' => [Role::PERMISSION_PERJADIN_ACCESS],
        ]);
        $admin = User::factory()->create(['role_id' => $administrator->id]);
        $user = User::factory()->create(['role_id' => $operator->id]);

        Sanctum::actingAs($user);
        $this->getJson('/api/v1/users')->assertForbidden();

        Sanctum::actingAs($admin);
        $this->getJson('/api/v1/roles')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Administrator');

        $customRole = $this->postJson('/api/v1/roles', [
            'name' => 'Verifikator',
            'description' => 'Memverifikasi dokumen perjalanan.',
            'permissions' => [Role::PERMISSION_PERJADIN_ACCESS],
        ])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'verifikator')
            ->json('data');

        $createdUser = $this->postJson('/api/v1/users', [
            'name' => 'Pengguna Baru',
            'email' => 'baru@example.test',
            'password' => 'password-baru',
            'role_id' => $customRole['id'],
        ])
            ->assertCreated()
            ->assertJsonPath('data.role.slug', 'verifikator')
            ->json('data');

        $this->patchJson("/api/v1/users/{$createdUser['id']}", [
            'name' => 'Pengguna Diperbarui',
            'email' => 'baru@example.test',
            'role_id' => $operator->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.name', 'Pengguna Diperbarui')
            ->assertJsonPath('data.role.slug', 'operator');

        $this->deleteJson("/api/v1/users/{$createdUser['id']}")->assertNoContent();
        $this->deleteJson("/api/v1/roles/{$customRole['id']}")->assertNoContent();
    }

    public function test_last_user_manager_cannot_be_downgraded_or_deleted(): void
    {
        $administrator = Role::query()->create([
            'slug' => 'administrator',
            'name' => 'Administrator',
            'permissions' => [Role::PERMISSION_USERS_MANAGE, Role::PERMISSION_ROLES_MANAGE],
        ]);
        $operator = Role::query()->create([
            'slug' => 'operator',
            'name' => 'Operator',
            'permissions' => [Role::PERMISSION_PERJADIN_ACCESS],
        ]);
        $admin = User::factory()->create(['role_id' => $administrator->id]);

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/users/{$admin->id}", [
            'name' => $admin->name,
            'email' => $admin->email,
            'role_id' => $operator->id,
        ])->assertStatus(409);

        $this->deleteJson("/api/v1/users/{$admin->id}")->assertStatus(422);
    }
}
