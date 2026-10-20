<?php

namespace App\Http\Controllers;

use App\Services\RoleManagementService;

class PermissionManagementController extends Controller
{
    public function index()
    {
        return view('role_management.permissions');
    }

    public function data(RoleManagementService $service)
    {
        return $service->permissionsTable();
    }
}
