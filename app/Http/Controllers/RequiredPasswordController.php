<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateRequiredPasswordRequest;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class RequiredPasswordController extends Controller
{
    public function edit(): View
    {
        return view('auth.required-password');
    }

    public function update(UpdateRequiredPasswordRequest $request): RedirectResponse
    {
        $request->user()->update([
            'password' => $request->validated('password'),
            'must_change_password' => false,
        ]);
        $request->session()->regenerate();

        return redirect()->route(
            $request->user()->platformAdmin()->exists() ? 'platform.dashboard' : 'dashboard',
        )->with('success', 'Sua senha foi alterada com sucesso.');
    }
}
