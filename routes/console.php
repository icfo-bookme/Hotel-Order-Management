<?php

\Illuminate\Support\Facades\Artisan::command('access:setup {email : Existing user email}', function () {
    $user = \App\Models\User::where('email', $this->argument('email'))->first();
    if (!$user) {
        $this->error('Existing user not found.');
        return 1;
    }
    $this->call('db:seed', ['--class' => \Database\Seeders\RolePermissionSeeder::class]);
    $role = \App\Models\Role::where('name', \App\Models\Role::SUPER_ADMIN)->firstOrFail();
    $user->forceFill(['role_id' => $role->id,'is_active' => true])->save();
    $this->info('Super Admin assigned to '.$user->email.'.');
    return 0;
})->purpose('Initialize access permissions and assign an existing Super Admin');
