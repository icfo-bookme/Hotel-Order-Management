<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Role;
use App\Services\RoleManagementService;
use Illuminate\Http\Request;

class RoleManagementController extends Controller
{
    public function index(RoleManagementService $service)
    {
        return view('role_management.roles', ['groups' => $service->permissionGroups()]);
    }

    public function data(Request $request, RoleManagementService $service)
    {
        return $service->rolesTable($request->user());
    }

    public function show(Role $role)
    {
        return response()->json(['data' => $role->load('permissions')]);
    }

    public function store(StoreRoleRequest $request, RoleManagementService $service)
    {
        return response()->json(['message' => 'Role created.','data' => $service->saveRole($request->validated(), $request->user())], 201);
    }

    public function update(UpdateRoleRequest $request, Role $role, RoleManagementService $service)
    {
        return response()->json(['message' => 'Role updated.','data' => $service->saveRole($request->validated(), $request->user(), $role)]);
    }

    public function destroy(Request $request, Role $role, RoleManagementService $service)
    {
        $service->deleteRole($role, $request->user());
        return response()->json(['message' => 'Role deleted.']);
    }
}
