<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\WithdrawalWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class UserController extends Controller
{
    public function index() { return view('users.index', ['users' => User::orderBy('name')->paginate(15)]); }

    public function store(Request $request, WithdrawalWorkflow $workflow)
    {
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => 'required|email|max:255|unique:users,email', 'role' => 'required|in:staff,supervisor,admin', 'password' => 'required|string|min:8']);
        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);
        $workflow->audit(null, $request->user()->id, 'user_created', ['user_id' => $user->id, 'role' => $user->role]);
        return back()->with('success', 'Pengguna ditambahkan.');
    }

    public function update(Request $request, User $user, WithdrawalWorkflow $workflow)
    {
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'role' => 'required|in:staff,supervisor,admin',
            'password' => 'nullable|string|min:8',
        ]);
        if ((int) $user->id === (int) $request->user()->id && $data['role'] !== 'admin') {
            throw ValidationException::withMessages(['role' => 'Admin tidak dapat menurunkan role dirinya sendiri.']);
        }
        $before = $user->only(['name', 'email', 'role']);
        if (!empty($data['password'])) $data['password'] = Hash::make($data['password']);
        else unset($data['password']);
        $user->update($data);
        $workflow->audit(null, $request->user()->id, 'user_updated', ['user_id' => $user->id, 'before' => $before, 'after' => $user->only(['name', 'email', 'role']), 'password_changed' => isset($data['password'])]);
        return back()->with('success', 'Pengguna diperbarui.');
    }
}
