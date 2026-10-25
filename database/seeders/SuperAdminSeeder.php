<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class SuperAdminSeeder extends Seeder
{
    public function run(): void
    {
        $role = Role::where('name', Role::SUPER_ADMIN)->firstOrFail();
        $user = User::firstOrNew(['email' => 'fahim@gmail.com']);

        $user->forceFill([
            'name' => 'Fahim',
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        if (!$user->exists) {
            $user->password = 'Fahim411934##';
        }

        $user->save();
    }
}
