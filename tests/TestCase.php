<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function actingAsAdmin(\App\Models\User $user, $guard = null): static
    {
        if (!\App\Models\Role::where('name', \App\Models\Role::SUPER_ADMIN)->exists()) {
            $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        }
        $user->forceFill(['role_id' => \App\Models\Role::where('name', \App\Models\Role::SUPER_ADMIN)->value('id'), 'is_active' => true])->save();
        $user->unsetRelation('role');

        return $this->actingAs($user, $guard);
    }

    public function createApplication()
    {
        $app = require __DIR__.'/../bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
