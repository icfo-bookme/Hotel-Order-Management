<?php

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Yajra\DataTables\Facades\DataTables;

class RoleManagementService
{
    public function permissionGroups()
    {
        return Permission::orderBy('group')->orderBy('name')->get()->groupBy('group');
    }

    public function assignableRoles(User $actor)
    {
        return Role::with('permissions')->orderBy('name')->get()->filter(fn ($role) => $this->canManageRole($role, $actor))->values();
    }

    public function canManageRole(?Role $role, User $actor): bool
    {
        if ($actor->role?->isSuperAdmin()) {
            return true;
        }
        if (!$role) {
            return true;
        }
        if ($role->isSuperAdmin()) {
            return false;
        }
        return $role->permissions->every(fn ($permission) => $actor->can($permission->name));
    }

    public function usersTable(User $actor)
    {
        return DataTables::eloquent(User::with('role.permissions')->select('hotel_login.*'))
            ->addColumn('role_name', fn ($user) => $user->role?->name ?? 'Unassigned')
            ->filterColumn('role_name', fn ($query, $keyword) => $query->whereHas('role', fn ($role) => $role->where('name', 'like', '%'.$keyword.'%')))
            ->addColumn('action', fn ($user) => view('role_management.partials.actions', ['item' => $user,'section' => 'users','editable' => $actor->can('users.update') && $this->canManageRole($user->role, $actor),'deletable' => false])->render())
            ->editColumn('created_at', fn ($user) => $user->created_at?->timezone('Asia/Dhaka')->format('d M Y'))
            ->rawColumns(['action'])->escapeColumns(['name','email','role_name'])->toJson();
    }

    public function rolesTable(User $actor)
    {
        return DataTables::eloquent(Role::with('permissions')->select('hotel_roles.*')->withCount(['users','permissions']))
            ->addColumn('action', fn ($role) => view('role_management.partials.actions', ['item' => $role,'section' => 'roles','editable' => !$role->isSuperAdmin() && $actor->can('roles.update') && $this->canManageRole($role, $actor),'deletable' => !$role->isSuperAdmin() && !$role->users_count && $actor->can('roles.delete') && $this->canManageRole($role, $actor)])->render())
            ->rawColumns(['action'])->escapeColumns(['name'])->toJson();
    }

    public function permissionsTable()
    {
        return DataTables::eloquent(Permission::select('hotel_permissions.*')->withCount('roles'))
            ->addColumn('label', fn ($permission) => config('access.permissions')[$permission->group][$permission->name] ?? $permission->name)
            ->escapeColumns(['name','group','label'])->toJson();
    }

    public function saveUser(array $data, User $actor, ?User $target = null): User
    {
        return DB::transaction(function () use ($data, $actor, $target) {
            Role::orderBy('id')->lockForUpdate()->get();
            $role = Role::with('permissions')->findOrFail($data['role_id']);
            abort_unless($this->canManageRole($role, $actor), 403);
            if ($target) {
                $target = User::lockForUpdate()->findOrFail($target->id);
                abort_unless($this->canManageRole($target->role, $actor), 403);
                if ((int) $target->role_id !== $role->id) {
                    abort_unless($actor->can('users.assign-role'), 403);
                }
                if ($actor->id === $target->id && !$data['is_active']) {
                    throw ValidationException::withMessages(['is_active' => 'You cannot deactivate your own account.']);
                }
                if ($target->role?->isSuperAdmin() && (!$data['is_active'] || !$role->isSuperAdmin())) {
                    $this->ensureAnotherSuperAdmin($target);
                }
            } else {
                abort_unless($actor->can('users.assign-role'), 403);
                $target = new User();
            }
            $fields = ['name' => $data['name'],'email' => $data['email'],'role_id' => $role->id,'is_active' => (bool)$data['is_active']];
            if (!empty($data['password'])) {
                $fields['password'] = $data['password'];
                $fields['remember_token'] = Str::random(60);
            }
            $target->forceFill($fields)->save();
            return $target->load('role');
        });
    }

    public function ensureAnotherSuperAdmin(User $user): void
    {
        if (!User::where('is_active', true)->where('id', '!=', $user->id)->whereHas('role', fn ($role) => $role->where('name', Role::SUPER_ADMIN))->exists()) {
            throw ValidationException::withMessages(['role_id' => 'At least one active Super Admin must remain.']);
        }
    }

    public function saveRole(array $data, User $actor, ?Role $role = null): Role
    {
        return DB::transaction(function () use ($data, $actor, $role) {
            Role::orderBy('id')->lockForUpdate()->get();
            if ($role) {
                $role = Role::with('permissions')->findOrFail($role->id);
                if ($role->isSuperAdmin()) {
                    throw ValidationException::withMessages(['role' => 'The Super Admin role is protected.']);
                }
                abort_unless($this->canManageRole($role, $actor), 403);
            }
            foreach (Permission::whereIn('id', $data['permissions'])->get() as $permission) {
                abort_unless($actor->can($permission->name), 403, 'You cannot grant permissions you do not have.');
            }
            $role ??= new Role();
            $role->fill(['name' => $data['name']])->save();
            $role->permissions()->sync($data['permissions']);
            return $role->load('permissions');
        });
    }

    public function deleteRole(Role $role, User $actor): void
    {
        DB::transaction(function () use ($role, $actor) {
            Role::orderBy('id')->lockForUpdate()->get();
            $role = Role::with('permissions')->findOrFail($role->id);
            if ($role->isSuperAdmin() || $role->users()->exists()) {
                throw ValidationException::withMessages(['role' => 'Protected or assigned roles cannot be deleted.']);
            }
            abort_unless($this->canManageRole($role, $actor), 403);
            $role->delete();
        });
    }
}
