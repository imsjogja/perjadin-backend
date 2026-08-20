<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Create the bootstrap administrator once, without overwriting its password
     * on subsequent seed runs.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $administrator = Role::query()->where('slug', 'administrator')->sole();

        $user = User::query()->firstOrCreate(
            ['email' => config('perjadin.admin.email')],
            [
                'name' => config('perjadin.admin.name'),
                'email_verified_at' => now(),
                'password' => Hash::make(config('perjadin.admin.password')),
                'role_id' => $administrator->id,
            ],
        );

        if ($user->role_id !== $administrator->id) {
            $user->update(['role_id' => $administrator->id]);
        }
    }
}
