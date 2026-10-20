<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    public const SUPER_ADMIN = 'Super Admin';
    protected $table = 'hotel_roles';
    protected $fillable = ['name'];
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'hotel_role_permissions');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->name === self::SUPER_ADMIN;
    }
}
