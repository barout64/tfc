<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\Activity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt([...$credentials, 'is_active' => true], $request->boolean('remember'))) {
            throw ValidationException::withMessages(['email' => 'Wrong email or password.']);
        }

        $request->session()->regenerate();
        $request->user()->forceFill(['last_login_at' => now()])->save();
        Activity::record('signed_in');

        return redirect()->intended(route('landing'));
    }

    /**
     * Where a freshly signed-in person lands: their own portal if Kilimo gave them a
     * dealer/transporter role, otherwise the main TFC hub. Each later module (Meeting,
     * Protocol) extends this same match as it gets its own native, role-scoped landing page.
     */
    public function landing(Request $request): RedirectResponse
    {
        return match ($request->user()->role) {
            Role::AgroDealer => redirect()->route('dealer.home'),
            Role::Transporter => redirect()->route('transporter.home'),
            default => redirect()->route('home'),
        };
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function editPassword(): View
    {
        return view('auth.password');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $data['password']]);
        Activity::record('changed_password');

        return redirect()->route('home')->with('status', 'Your password has been changed.');
    }
}
