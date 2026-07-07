<?php

namespace Modules\Workspace\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Http\Resources\EmployeeResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = User::query();

        if ($request->has('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->has('role')) {
            $query->where('role', $request->query('role'));
        }

        return EmployeeResource::collection($query->get());
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'role' => 'required|string|in:admin,manager,employee',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'name' => 'required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
        ]);

        $validated['password'] = Hash::make($validated['password']);
        $validated['status'] = 'active';

        $employee = User::create($validated);

        return (new EmployeeResource($employee))
            ->response()
            ->setStatusCode(201);
    }
    public function show($employeeId)
    {
        $employee = User::find($employeeId);

        if (!$employee) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Employee profile not found.'], 404);
        }

        return new EmployeeResource($employee);
    }

    public function update(Request $request, $employeeId)
    {
        $employee = User::find($employeeId);

        if (!$employee) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Employee profile not found.'], 404);
        }

        $validated = $request->validate([
            'role' => 'sometimes|required|string|in:admin,manager,employee',
            'email' => 'sometimes|required|email|unique:users,email,' . $employeeId,
            'name' => 'sometimes|required|string|max:255',
            'phone_number' => 'nullable|string|max:20',
            'status' => 'sometimes|required|string|in:active,inactive',
        ]);

        $employee->update($validated);

        return new EmployeeResource($employee);
    }

    public function destroy($employeeId)
    {
        $employee = User::find($employeeId);

        if (!$employee) {
            return response()->json(['code' => 'NOT_FOUND', 'message' => 'Employee profile not found.'], 404);
        }

        $employee->update(['status' => 'inactive']);

        return response()->json(['message' => 'Employee successfully deactivated.'], 200);
    }
}
