<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;

class PasswordResetLinkController extends Controller
{
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'email' => ['required', 'email'],
        ], [
            'email.required' => 'Informe o e-mail da conta.',
            'email.email' => 'Informe um e-mail válido.',
        ]);

        Password::sendResetLink($request->only('email'));

        return back()->with('success', 'Se o e-mail estiver cadastrado, enviaremos as instruções para redefinir a senha.');
    }
}
