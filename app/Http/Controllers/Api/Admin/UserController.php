<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class UserController extends Controller
{
    /**
     * List all system users with filters.
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        if ($agency = $request->query('agency')) {
            $query->where('agency', $agency);
        }

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(20);

        return response()->json($users);
    }

    /**
     * Get a single user with statistics.
     */
    public function show(User $user): JsonResponse
    {
        $data = $user->toArray();
        
        // Add statistics based on role
        if ($user->role === 'applicant') {
            $data['applications_count'] = $user->applications()->count();
        } elseif (in_array($user->role, ['gis_officer', 'mfa_reviewer'])) {
            $data['assigned_cases_count'] = \App\Models\Application::where('assigned_officer_id', $user->id)->count();
        }

        return response()->json($data);
    }

    /**
     * Create a new system user (officer, reviewer, admin).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => ['required', Password::min(8)->mixedCase()->numbers()],
            'role'       => 'required|in:gis_officer,mfa_reviewer,admin',
            'agency'     => 'required|in:GIS,MFA,ADMIN',
            'phone'      => 'nullable|string|max:20',
            'locale'     => 'nullable|in:en,fr',
        ]);

        $user = User::create([
            'first_name' => $validated['first_name'],
            'last_name'  => $validated['last_name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
            'role'       => $validated['role'],
            'agency'     => $validated['agency'],
            'phone'      => $validated['phone'] ?? null,
            'locale'     => $validated['locale'] ?? 'en',
        ]);

        return response()->json([
            'message' => __('admin.user_created'),
            'user'    => $user,
        ], 201);
    }

    /**
     * Update a system user.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        $validated = $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name'  => 'sometimes|string|max:255',
            'email'      => "sometimes|email|unique:users,email,{$user->id}",
            'role'       => 'sometimes|in:applicant,gis_officer,mfa_reviewer,admin',
            'agency'     => 'sometimes|in:GIS,MFA,ADMIN',
            'is_active'  => 'sometimes|boolean',
            'locale'     => 'sometimes|in:en,fr',
        ]);

        $user->update($validated);

        return response()->json([
            'message' => __('admin.user_updated'),
            'user'    => $user->fresh(),
        ]);
    }

    /**
     * Deactivate a user (soft).
     */
    public function deactivate(User $user): JsonResponse
    {
        $user->update(['is_active' => false]);

        return response()->json([
            'message' => __('admin.user_deactivated'),
        ]);
    }

    /**
     * Reactivate a user.
     */
    public function activate(User $user): JsonResponse
    {
        $user->update(['is_active' => true]);

        return response()->json([
            'message' => __('admin.user_activated'),
        ]);
    }
}
