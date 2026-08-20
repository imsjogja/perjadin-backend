<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RoleController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Role::query()->withCount('users')->orderBy('name')->get(),
            'meta' => [
                'permission_options' => $this->permissionOptions(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateRole($request);

        $role = Role::query()->create([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['name']),
        ]);

        return response()->json([
            'data' => $role->loadCount('users'),
        ], 201);
    }

    public function update(Role $role, Request $request): JsonResponse
    {
        $validated = $this->validateRole($request, $role);

        if (
            $request->user()?->role_id === $role->id
            && ! $this->canManageAdministration($validated['permissions'])
        ) {
            return response()->json([
                'message' => 'Role aktif Anda harus tetap memiliki hak manajemen pengguna dan role.',
            ], 422);
        }

        $role->update([
            ...$validated,
            'slug' => $this->uniqueSlug($validated['name'], $role),
        ]);

        return response()->json([
            'data' => $role->fresh()->loadCount('users'),
        ]);
    }

    public function destroy(Role $role): JsonResponse
    {
        if ($role->users()->exists()) {
            return response()->json([
                'message' => 'Role tidak dapat dihapus selama masih dipakai oleh pengguna.',
            ], 409);
        }

        $role->delete();

        return response()->json([], 204);
    }

    /**
     * @return array{name: string, description: ?string, permissions: array<int, string>}
     */
    private function validateRole(Request $request, ?Role $role = null): array
    {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('roles', 'name')->ignore($role?->id),
            ],
            'description' => ['nullable', 'string', 'max:500'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', 'string', Rule::in(Role::availablePermissions())],
        ]);
    }

    private function uniqueSlug(string $name, ?Role $except = null): string
    {
        $base = str($name)->slug()->value() ?: 'role';
        $slug = $base;
        $number = 2;

        while (
            Role::query()
                ->where('slug', $slug)
                ->when($except, fn ($query) => $query->whereKeyNot($except->id))
                ->exists()
        ) {
            $slug = "{$base}-{$number}";
            $number++;
        }

        return $slug;
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function permissionOptions(): array
    {
        return [
            ['value' => Role::PERMISSION_PERJADIN_ACCESS, 'label' => 'Operasional perjalanan dinas'],
            ['value' => Role::PERMISSION_USERS_MANAGE, 'label' => 'Manajemen pengguna'],
            ['value' => Role::PERMISSION_ROLES_MANAGE, 'label' => 'Manajemen role'],
            ['value' => Role::PERMISSION_SETTINGS_MANAGE, 'label' => 'Pengaturan format nomor dokumen'],
        ];
    }

    /**
     * @param  array<int, string>  $permissions
     */
    private function canManageAdministration(array $permissions): bool
    {
        return in_array('*', $permissions, true)
            || (
                in_array(Role::PERMISSION_USERS_MANAGE, $permissions, true)
                && in_array(Role::PERMISSION_ROLES_MANAGE, $permissions, true)
            );
    }
}
