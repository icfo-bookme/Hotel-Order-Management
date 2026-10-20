<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('access.permissions') as $group => $permissions) {
            foreach ($permissions as $name => $label) {
                Permission::updateOrCreate(['name' => $name], ['group' => $group]);
            }
        }
        $super = Role::firstOrCreate(['name' => Role::SUPER_ADMIN]);
        $super->permissions()->sync(Permission::pluck('id'));
        foreach (['Admin', 'Sales', 'Accounts', 'Viewer'] as $name) {
            if (Role::where('name', $name)->exists()) {
                continue;
            }
            $role = Role::create(['name' => $name]);
            $names = match ($name) {
                'Admin' => Permission::pluck('name')->all(),
                'Sales' => ['dashboard.view','tickets.pending.view','tickets.feedback.view','bookings.pending-sold.view','bookings.sold.view','bookings.view','feedback.create','feedback.update','bookings.sell','bookings.reschedule','bookings.invoice','payments.create'],
                'Accounts' => ['dashboard.view','bookings.pending-sold.view','bookings.sold.view','bookings.view','bookings.invoice','payments.verify','payments.reject','payments.screenshot','hotel-accounts.view','hotel-accounts.create','hotel-accounts.update','payouts.pending.view','payouts.paid.view','payouts.create','payouts.advances','payouts.screenshot','reports.view','reports.export'],
                default => ['dashboard.view','bookings.sold.view','bookings.view','reports.view'],
            };
            $role->permissions()->sync(Permission::whereIn('name', $names)->pluck('id'));
        }
    }
}
