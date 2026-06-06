<?php

namespace App\Http\Controllers\Auth;

use App\Events\UserPresenceChanged;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('chat.index');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // Set user online
            $user = Auth::user();
            $user->update(['is_online' => true, 'last_seen_at' => now()]);

            broadcast(new UserPresenceChanged($user, 'online'));

            return redirect()->intended(route('chat.index'));
        }

        return back()->withErrors([
            'email' => 'Email atau password salah.',
        ])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        $user = Auth::user();

        if ($user) {
            $user->update(['is_online' => false, 'last_seen_at' => now()]);
            broadcast(new UserPresenceChanged($user, 'offline'));
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
