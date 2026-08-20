<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::query()->updateOrCreate(
            ['slug' => 'administrator'],
            [
                'name' => 'Administrator',
                'description' => 'Mengelola konfigurasi, role, pengguna, dan seluruh proses perjalanan dinas.',
                'permissions' => ['*'],
            ],
        );

        Role::query()->updateOrCreate(
            ['slug' => 'operator'],
            [
                'name' => 'Operator',
                'description' => 'Mengelola administrasi perjalanan dinas.',
                'permissions' => [Role::PERMISSION_PERJADIN_ACCESS],
            ],
        );
    }
}
