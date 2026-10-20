<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory;
    use Notifiable;

    protected $table = 'hotel_login';
    protected $attributes = ['is_active' => true];

    // The attributes that are mass assignable.
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    // The attributes that should be hidden for serialization.
    protected $hidden = [
        'password',
        'remember_token',
    ];

    // Get the attributes that should be cast.
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'role_id' => 'integer',
        ];
    }

    public function remarks()
    {
        return $this->hasMany(HotelRemark::class, 'added_by');
    }

    public function role(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function hasPermission(string $permission): bool
    {
        $this->loadMissing('role.permissions');

        return $this->is_active && $this->role && ($this->role->isSuperAdmin() || $this->role->permissions->contains('name', $permission));
    }
}
