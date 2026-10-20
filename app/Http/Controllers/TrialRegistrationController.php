<?php

namespace App\Http\Controllers;

use App\Actions\CreateCompany;
use App\Http\Requests\StoreTrialRegistrationRequest;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

class TrialRegistrationController extends Controller
{
    public function create(): View
    {
        return view('auth.trial-register');
    }

    public function store(StoreTrialRegistrationRequest $request, CreateCompany $createCompany): RedirectResponse
    {
        $validated = $request->validated();

        $createCompany->handle([
            ...$validated,
            'branch_name' => 'Matriz',
        ]);

        $owner = User::query()->where('email', $validated['owner_email'])->sole();
        Auth::login($owner);
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Seu teste de '.config('giracomanda.subscription.trial_days').' dias começou. Bem-vindo ao GiraComanda!');
    }
}
