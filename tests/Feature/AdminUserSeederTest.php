<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminUserSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_single_configured_administrator(): void
    {
        config([
            'perjadin.admin.name' => 'Perjadin Administrator',
            'perjadin.admin.email' => 'admin@example.test',
            'perjadin.admin.password' => 'admin-password',
        ]);

        $this->seed(AdminUserSeeder::class);
        $this->seed(AdminUserSeeder::class);

        $admin = User::query()->where('email', 'admin@example.test')->sole();

        $this->assertSame('Perjadin Administrator', $admin->name);
        $this->assertTrue(Hash::check('admin-password', $admin->password));
        $this->assertDatabaseCount('users', 1);
    }
}
