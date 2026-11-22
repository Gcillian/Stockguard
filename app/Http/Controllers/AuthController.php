<?php
namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function show() { return view('auth.login'); }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => 'required|string', 'password' => 'required']);
        $identifier = trim($data['email']);
        $email = filter_var($identifier, FILTER_VALIDATE_EMAIL)
            ? $identifier
            : User::where('name', $identifier)->value('email');
        if (!$email || !Auth::attempt(['email' => $email, 'password' => $data['password']], $request->boolean('remember'))) {
            return back()->withErrors(['email' => 'Email, nama pengguna, atau password salah.'])->onlyInput('email');
        }
        $request->session()->regenerate();
        return redirect()->intended(route('dashboard'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('login');
    }
}
