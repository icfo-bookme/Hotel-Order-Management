<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreManagedUserRequest;
use App\Http\Requests\UpdateManagedUserRequest;
use App\Models\User;
use App\Services\RoleManagementService;
use Illuminate\Http\Request;

class UserManagementController extends Controller
{
    public function index(Request $request, RoleManagementService $service)
    {
        return view('role_management.users', ['roles' => $service->assignableRoles($request->user())]);
    }

    public function data(Request $request, RoleManagementService $service)
    {
        return $service->usersTable($request->user());
    }

    public function store(StoreManagedUserRequest $request, RoleManagementService $service)
    {
        return response()->json(['message' => 'User created.','data' => $service->saveUser($request->validated(), $request->user())], 201);
    }

    public function update(UpdateManagedUserRequest $request, User $user, RoleManagementService $service)
    {
        return response()->json(['message' => 'User updated.','data' => $service->saveUser($request->validated(), $request->user(), $user)]);
    }
}
