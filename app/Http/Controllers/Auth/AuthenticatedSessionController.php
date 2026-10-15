<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $authenticated = Auth::attempt(
            [
                'email' => $request->string('email')->toString(),
                'password' => $request->string('password')->toString(),
                'active' => true,
            ],
            $request->boolean('remember'),
        );

        if (! $authenticated) {
            return back()
                ->withErrors(['email' => 'E-mail, senha ou situação do usuário inválidos.'])
                ->onlyInput('email');
        }

        $request->session()->regenerate();

        if ($request->user()?->must_change_password) {
            return redirect()->route('password.required.edit');
        }

        if ($request->user()?->platformAdmin()->exists()) {
            return redirect()->intended(route('platform.dashboard'));
        }

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
