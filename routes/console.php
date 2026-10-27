<?php

\Illuminate\Support\Facades\Artisan::command('hotel-sheets:check', function () {
    try {
        $tab = app(\App\Services\GoogleSheetService::class)->checkConnection();
        $this->info('Google Sheet connection successful. Tab: '.$tab);
        return 0;
    } catch (\Google\Service\Exception $exception) {
        $this->error('Google Sheet access failed (HTTP '.$exception->getCode().'). Check API access, Editor sharing and the configured tab.');
        return 1;
    } catch (\Throwable $exception) {
        $this->error('Google Sheet check failed. Check configuration, matching headers, credentials and network access.');
        return 1;
    }
})->purpose('Check hotel Google Sheet access without writing data');

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
