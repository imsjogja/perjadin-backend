<?php

namespace Tests\Feature;

use App\Models\ApplicationSetting;
use App\Models\Role;
use App\Models\User;
use App\Services\DocumentNumberService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentNumberFormatApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_users_with_settings_permission_can_manage_document_number_formats(): void
    {
        $operatorRole = Role::query()->create([
            'slug' => 'operator',
            'name' => 'Operator',
            'permissions' => [Role::PERMISSION_PERJADIN_ACCESS],
        ]);
        $settingsRole = Role::query()->create([
            'slug' => 'settings',
            'name' => 'Pengelola Pengaturan',
            'permissions' => [Role::PERMISSION_SETTINGS_MANAGE],
        ]);

        Sanctum::actingAs(User::factory()->create(['role_id' => $operatorRole->id]));
        $this->getJson('/api/v1/settings/document-number-formats')->assertForbidden();

        Sanctum::actingAs(User::factory()->create(['role_id' => $settingsRole->id]));
        $this->getJson('/api/v1/settings/document-number-formats')
            ->assertOk()
            ->assertJsonPath('data.spt.default_value', '823-{number}/BKD-{type}/{year}')
            ->assertJsonPath('data.spt.custom_value', null);
    }

    public function test_authorized_user_can_save_format_and_clear_it_to_use_environment_fallback(): void
    {
        $settingsRole = Role::query()->create([
            'slug' => 'settings',
            'name' => 'Pengelola Pengaturan',
            'permissions' => [Role::PERMISSION_SETTINGS_MANAGE],
        ]);
        Sanctum::actingAs(User::factory()->create(['role_id' => $settingsRole->id]));

        $this->putJson('/api/v1/settings/document-number-formats', [
            'spt_format' => 'SPT/{year}/{number}',
            'sppd_format' => 'SPPD-{type}-{number}',
        ])
            ->assertOk()
            ->assertJsonPath('data.spt.custom_value', 'SPT/{year}/{number}')
            ->assertJsonPath('data.spt.effective_value', 'SPT/{year}/{number}')
            ->assertJsonPath('data.spt.source', 'application');

        $this->assertDatabaseHas('application_settings', [
            'key' => ApplicationSetting::KEY_SPT_NUMBER_FORMAT,
            'value' => 'SPT/{year}/{number}',
        ]);
        $this->assertSame(
            'SPT/2026/00001',
            app(DocumentNumberService::class)->next('spt', Carbon::parse('2026-08-20'))['document_number'],
        );

        config(['perjadin.number_formats.spt' => 'ENV/{year}/{number}']);
        $this->putJson('/api/v1/settings/document-number-formats', [
            'spt_format' => '',
            'sppd_format' => '',
        ])
            ->assertOk()
            ->assertJsonPath('data.spt.custom_value', null)
            ->assertJsonPath('data.spt.effective_value', 'ENV/{year}/{number}')
            ->assertJsonPath('data.spt.source', 'environment');

        $this->assertDatabaseMissing('application_settings', [
            'key' => ApplicationSetting::KEY_SPT_NUMBER_FORMAT,
        ]);
        $this->assertSame(
            'ENV/2027/00001',
            app(DocumentNumberService::class)->next('spt', Carbon::parse('2027-01-01'))['document_number'],
        );
    }

    public function test_format_requires_number_token_and_rejects_unknown_tokens(): void
    {
        $role = Role::query()->create([
            'slug' => 'settings',
            'name' => 'Pengelola Pengaturan',
            'permissions' => [Role::PERMISSION_SETTINGS_MANAGE],
        ]);
        Sanctum::actingAs(User::factory()->create(['role_id' => $role->id]));

        $this->putJson('/api/v1/settings/document-number-formats', [
            'spt_format' => 'SPT/{year}',
            'sppd_format' => 'SPPD/{unknown}/{number}',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['spt_format', 'sppd_format']);
    }
}
