<?php

namespace Tests\Feature;

use App\Models\DocumentReference;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\DocumentReferenceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DocumentReferenceApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_users_can_manage_document_references(): void
    {
        $manager = $this->settingsManager();
        Sanctum::actingAs($manager);

        $reference = $this->postJson('/api/v1/references/transportasi', [
            'value' => 'Pesawat',
        ])
            ->assertCreated()
            ->assertJsonPath('data.category', DocumentReference::CATEGORY_TRANSPORTATION)
            ->assertJsonPath('data.value', 'Pesawat')
            ->json('data');

        $this->getJson('/api/v1/references/transportasi')
            ->assertOk()
            ->assertJsonPath('meta.label', 'Transportasi')
            ->assertJsonPath('data.0.value', 'Pesawat');

        $this->patchJson("/api/v1/references/transportasi/{$reference['id']}", [
            'value' => 'Kapal',
        ])
            ->assertOk()
            ->assertJsonPath('data.value', 'Kapal');

        $this->deleteJson("/api/v1/references/transportasi/{$reference['id']}")
            ->assertNoContent();

        $this->assertDatabaseMissing('document_references', [
            'id' => $reference['id'],
        ]);
    }

    public function test_document_reference_management_requires_settings_permission(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/references/transportasi', [
            'value' => 'Pesawat',
        ])->assertForbidden();
    }

    public function test_document_reference_seeder_adds_default_options(): void
    {
        $this->seed(DocumentReferenceSeeder::class);

        $this->assertDatabaseCount('document_references', 11);
        $this->assertDatabaseHas('document_references', [
            'category' => DocumentReference::CATEGORY_TRANSPORTATION,
            'value' => 'Pesawat',
        ]);
        $this->assertDatabaseHas('document_references', [
            'category' => DocumentReference::CATEGORY_TRAVEL_LEVEL,
            'value' => 'F',
        ]);
        $this->assertDatabaseHas('document_references', [
            'category' => DocumentReference::CATEGORY_TRAVEL_TYPE,
            'value' => 'Dalam Kota',
        ]);
    }

    private function settingsManager(): User
    {
        $role = Role::query()->create([
            'name' => 'Pengelola Referensi',
            'slug' => 'pengelola-referensi',
            'permissions' => [Role::PERMISSION_SETTINGS_MANAGE],
        ]);

        return User::factory()->create([
            'role_id' => $role->id,
        ]);
    }
}
