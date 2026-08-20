<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => User::query()->with('role')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateUser($request);
        $user = User::query()->create([
            ...$validated,
            'password' => Hash::make($validated['password']),
        ]);

        return response()->json([
            'data' => $user->load('role'),
        ], 201);
    }

    public function update(User $user, Request $request): JsonResponse
    {
        $validated = $this->validateUser($request, $user);
        $newRole = Role::query()->findOrFail($validated['role_id']);

        if ($this->wouldRemoveLastUserManager($user, $newRole)) {
            return response()->json([
                'message' => 'Setidaknya satu pengguna harus tetap dapat mengelola pengguna.',
            ], 409);
        }

        $attributes = [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'role_id' => $newRole->id,
        ];
        if (filled($validated['password'] ?? null)) {
            $attributes['password'] = Hash::make($validated['password']);
        }

        $user->update($attributes);

        return response()->json([
            'data' => $user->fresh()->load('role'),
        ]);
    }

    public function destroy(User $user, Request $request): JsonResponse
    {
        if ($request->user()?->is($user)) {
            return response()->json([
                'message' => 'Pengguna yang sedang masuk tidak dapat dihapus.',
            ], 422);
        }

        if ($this->wouldRemoveLastUserManager($user, null)) {
            return response()->json([
                'message' => 'Setidaknya satu pengguna harus tetap dapat mengelola pengguna.',
            ], 409);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json([], 204);
    }

    /**
     * @return array{name: string, email: string, role_id: int, password?: string}
     */
    private function validateUser(Request $request, ?User $user = null): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email')->ignore($user?->id),
            ],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')],
        ];

        $rules['password'] = $user === null
            ? ['required', 'string', 'min:8']
            : ['nullable', 'string', 'min:8'];

        return $request->validate($rules);
    }

    private function wouldRemoveLastUserManager(User $user, ?Role $replacementRole): bool
    {
        $user->loadMissing('role');
        if (! $user->hasPermission(Role::PERMISSION_USERS_MANAGE)) {
            return false;
        }
        if ($replacementRole?->hasPermission(Role::PERMISSION_USERS_MANAGE)) {
            return false;
        }

        return ! User::query()
            ->whereKeyNot($user->id)
            ->with('role')
            ->get()
            ->contains(fn (User $candidate): bool => $candidate->hasPermission(Role::PERMISSION_USERS_MANAGE));
    }
}
