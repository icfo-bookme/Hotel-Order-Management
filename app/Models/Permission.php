<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Permission extends Model
{
    protected $table = 'hotel_permissions';
    protected $fillable = ['name', 'group'];
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'hotel_role_permissions');
    }
}
