<?php

namespace Tests\Feature;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class RoleManagementTest extends TestCase
{
    use RefreshDatabase;
    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create();
        $user->forceFill(['role_id' => Role::where('name', Role::SUPER_ADMIN)->value('id')])->save();
        $this->actingAs($user);
        return $user;
    }
    private function payload(Role $role): array
    {
        return ['name' => 'New Staff','email' => 'new@example.test','password' => 'password-123','password_confirmation' => 'password-123','role_id' => $role->id,'is_active' => true];
    }
    public function test_users_roles_and_permission_pages_and_crud_use_common_modals(): void
    {
        $this->admin();
        $this->get(route('role-management.users'))->assertOk()->assertSee('managed-user-modal');
        $this->get(route('role-management.roles'))->assertOk()->assertSee('role-modal')->assertSee('delete-role-modal');
        $this->get(route('role-management.permissions'))->assertOk()->assertSee('permissions-table');
        $permission = Permission::where('name', 'dashboard.view')->firstOrFail();
        $r = $this->postJson(route('role-management.roles.store'), ['name' => 'Custom Staff','permissions' => [$permission->id]])->assertCreated();
        $role = Role::findOrFail($r->json('data.id'));
        $response = $this->postJson(route('role-management.users.store'), $this->payload($role))->assertCreated()->assertJsonMissingPath('data.password')->assertJsonMissingPath('data.remember_token');
        $user = User::findOrFail($response->json('data.id'));
        $this->assertTrue(Hash::check('password-123', $user->password));
        $this->getJson(route('role-management.users.data'))->assertOk()->assertSee('new@example.test')->assertDontSee($user->password);
        $this->getJson(route('role-management.roles.data'))->assertOk()->assertSee('Custom Staff')->assertSee('users_count');
        $this->getJson(route('role-management.permissions.data'))->assertOk()->assertJsonPath('recordsTotal', Permission::count());
        $this->deleteJson(route('role-management.roles.destroy', $role))->assertUnprocessable();
        $data = $this->payload($role);
        $data['name'] = 'Updated Staff';
        $data['password'] = '';
        $data['password_confirmation'] = '';
        $this->putJson(route('role-management.users.update', $user), $data)->assertOk();
        $this->assertTrue(Hash::check('password-123', $user->fresh()->password));
        $this->putJson(route('role-management.roles.update', $role), ['name' => 'Renamed Staff','permissions' => []])->assertOk();
        $this->assertSame(0, $role->permissions()->count());
        $this->postJson(route('role-management.roles.store'), ['name' => 'Empty Role','permissions' => []])->assertCreated();
        $empty = Role::where('name', 'Empty Role')->firstOrFail();
        $this->deleteJson(route('role-management.roles.destroy', $empty))->assertOk();
    }
    public function test_permissions_are_enforced_on_routes_actions_exports_and_sidebar_and_change_immediately(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create(['name' => 'Restricted']);
        $user = User::factory()->create();
        $user->forceFill(['role_id' => $role->id])->save();
        $this->actingAs($user);
        $this->getJson(route('dashboard'))->assertForbidden();
        $this->getJson(route('role-management.users.data'))->assertForbidden();
        $this->getJson(route('hotel-options.index'))->assertForbidden();
        $this->getJson(route('reports.hotel-sales.export'))->assertForbidden();
        $role->permissions()->sync(Permission::whereIn('name', ['dashboard.view','tickets.pending.view','reports.view'])->pluck('id'));
        $this->get(route('dashboard'))->assertOk()->assertSee('Dashboard')->assertDontSee('Role Management')->assertDontSee('Hotel Payments');
        $this->get('/hotel/pending')->assertOk()->assertDontSee('data-edit-users');
        $this->get('/hotel/sold')->assertForbidden();
        $this->get(route('reports.hotel-sales'))->assertOk()->assertDontSee('id="report-export"', false);
        $this->postJson('/hotel/sales/9999/sell', [])->assertStatus(404);
        $this->postJson('/save-remarks', [])->assertForbidden();
        $role->permissions()->detach();
        $this->getJson(route('dashboard'))->assertForbidden();
    }
    public function test_payout_action_buttons_have_independent_permissions(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create(['name' => 'Payout Staff']);
        $user = User::factory()->create();
        $user->forceFill(['role_id' => $role->id])->save();
        $this->actingAs($user);
        $balance = ['reason' => null, 'available' => '100.00'];

        foreach ([[], ['payouts.create'], ['payouts.advances'], ['payouts.create', 'payouts.advances']] as $permissions) {
            $role->permissions()->sync(Permission::whereIn('name', $permissions)->pluck('id'));
            $user->unsetRelation('role');
            $html = view('hotel_payments.partials.payout-actions', compact('balance'))->render();
            $this->assertSame(in_array('payouts.create', $permissions, true), str_contains($html, 'data-payout'));
            $this->assertSame(in_array('payouts.advances', $permissions, true), str_contains($html, 'data-set-advances'));
        }
    }

    public function test_nested_payment_actions_require_their_own_permission(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $role = Role::create(['name' => 'Booking Staff']);
        $role->permissions()->sync(Permission::whereIn('name', ['tickets.pending.view', 'bookings.sell', 'bookings.reschedule'])->pluck('id'));
        $user = User::factory()->create();
        $user->forceFill(['role_id' => $role->id])->save();
        $this->actingAs($user);
        $this->get('/hotel/pending')->assertOk()->assertDontSee('id="add-sell-payment"', false);

        foreach ([\App\Http\Requests\SellHotelBookingRequest::class => 'payments', \App\Http\Requests\StoreHotelBookingRescheduleRequest::class => 'payment'] as $class => $field) {
            $request = $class::create('/', 'POST', [$field => ['amount' => 100]]);
            $request->setUserResolver(fn () => $user);
            $this->assertFalse($request->authorize());
            $request->replace([]);
            $this->assertTrue($request->authorize());
        }

        $role->permissions()->attach(Permission::where('name', 'payments.create')->value('id'));
        $user->unsetRelation('role');
        $request = \App\Http\Requests\StoreHotelBookingRescheduleRequest::create('/', 'POST', ['payment' => ['amount' => 100]]);
        $request->setUserResolver(fn () => $user);
        $this->assertTrue($request->authorize());
    }

    public function test_privilege_escalation_and_super_admin_changes_are_blocked(): void
    {
        $super = $this->admin();
        $superRole = $super->role;
        $permissions = Permission::whereIn('name', ['users.view','users.create','users.update','users.assign-role','roles.view','roles.create','roles.update','roles.delete'])->pluck('id');
        $role = Role::create(['name' => 'Access Manager']);
        $role->permissions()->sync($permissions);
        $manager = User::factory()->create();
        $manager->forceFill(['role_id' => $role->id])->save();
        $this->actingAs($manager);
        $this->postJson(route('role-management.users.store'), $this->payload($superRole))->assertForbidden();
        $data = $this->payload($superRole);
        $data['email'] = $super->email;
        $this->putJson(route('role-management.users.update', $super), $data)->assertForbidden();
        $this->postJson(route('role-management.roles.store'), ['name' => 'Escalated','permissions' => [Permission::where('name', 'payouts.create')->value('id')]])->assertForbidden();
        $this->putJson(route('role-management.roles.update', $superRole), ['name' => 'Changed','permissions' => []])->assertUnprocessable();
        $this->deleteJson(route('role-management.roles.destroy', $superRole))->assertUnprocessable();
        $this->assertDatabaseMissing('hotel_roles', ['name' => 'Escalated']);
    }
    public function test_last_super_admin_and_own_active_status_are_protected(): void
    {
        $super = $this->admin();
        $viewer = Role::where('name', 'Viewer')->firstOrFail();
        $data = $this->payload($super->role);
        $data['email'] = $super->email;
        $data['is_active'] = false;
        $this->putJson(route('role-management.users.update', $super), $data)->assertUnprocessable()->assertJsonValidationErrors('is_active');
        $data['role_id'] = $viewer->id;
        $data['is_active'] = true;
        $this->putJson(route('role-management.users.update', $super), $data)->assertUnprocessable()->assertJsonValidationErrors('role_id');
        $this->deleteJson('/profile', ['password' => 'password'])->assertUnprocessable();
        $other = User::factory()->create();
        $other->forceFill(['role_id' => $super->role_id])->save();
        $this->putJson(route('role-management.users.update', $super), $data)->assertOk();
        $this->assertSame($viewer->id, $super->fresh()->role_id);
    }
    public function test_inactive_users_cannot_login_and_existing_sessions_lose_access(): void
    {
        $super = $this->admin();
        $user = User::factory()->create();
        $user->forceFill(['role_id' => $super->role_id,'is_active' => false])->save();
        auth()->logout();
        $this->post('/login', ['email' => $user->email,'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $user->forceFill(['is_active' => true])->save();
        $this->actingAs($user);
        $this->get(route('dashboard'))->assertOk();
        $user->forceFill(['is_active' => false])->save();
        $this->getJson(route('dashboard'))->assertUnauthorized();
        $this->assertGuest();
    }
    public function test_registration_is_closed_and_role_assignment_needs_separate_permission(): void
    {
        $this->admin();
        $plain = Role::create(['name' => 'Plain']);
        $editorRole = Role::create(['name' => 'User Editor']);
        $editorRole->permissions()->sync(Permission::where('name', 'users.update')->pluck('id'));
        $editor = User::factory()->create();
        $editor->forceFill(['role_id' => $editorRole->id])->save();
        $target = User::factory()->create();
        $target->forceFill(['role_id' => $plain->id])->save();
        $this->actingAs($editor);
        $data = $this->payload($plain);
        $data['email'] = $target->email;
        $data['password'] = '';
        $data['password_confirmation'] = '';
        $this->putJson(route('role-management.users.update', $target), $data)->assertOk();
        $data['role_id'] = $editorRole->id;
        $this->putJson(route('role-management.users.update', $target), $data)->assertForbidden();
        auth()->logout();
        $this->get('/register')->assertNotFound();
        $this->post('/register', [])->assertNotFound();
        $this->getJson(route('role-management.roles.data'))->assertUnauthorized();
    }
}
