<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    use HasFactory;

    public const PERMISSION_PERJADIN_ACCESS = 'perjadin.access';
    public const PERMISSION_USERS_MANAGE = 'users.manage';
    public const PERMISSION_ROLES_MANAGE = 'roles.manage';
    public const PERMISSION_SETTINGS_MANAGE = 'settings.manage';

    protected $fillable = [
        'slug',
        'name',
        'description',
        'permissions',
    ];

    protected $casts = [
        'permissions' => 'array',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return array<int, string>
     */
    public static function availablePermissions(): array
    {
        return [
            self::PERMISSION_PERJADIN_ACCESS,
            self::PERMISSION_USERS_MANAGE,
            self::PERMISSION_ROLES_MANAGE,
            self::PERMISSION_SETTINGS_MANAGE,
        ];
    }

    public function hasPermission(string $permission): bool
    {
        return in_array('*', $this->permissions ?? [], true)
            || in_array($permission, $this->permissions ?? [], true);
    }
}
