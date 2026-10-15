<?php

namespace App\Http\Controllers;

use App\BranchRole;
use App\CompanyRole;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\BranchMembership;
use App\Models\CompanyMembership;
use App\Models\User;
use App\UserRole;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class UserController extends Controller
{
    public function index(): View
    {
        $company = app('currentCompany');

        return view('users.index', [
            'users' => User::query()
                ->whereHas('companyMemberships', fn ($query) => $query
                    ->where('company_id', $company->id)
                    ->where('active', true))
                ->orderBy('name')
                ->orderBy('id')
                ->paginate(15),
        ]);
    }

    public function create(): View
    {
        return view('users.create', [
            'roles' => UserRole::cases(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = User::query()->create($request->validated());
        $branch = app('currentBranch');

        CompanyMembership::query()->create([
            'company_id' => $branch->company_id,
            'user_id' => $user->id,
            'role' => $this->companyRole($user->role),
            'active' => $user->active,
        ]);
        BranchMembership::query()->create([
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'role' => $this->branchRole($user->role),
            'active' => $user->active,
        ]);

        return redirect()
            ->route('users.edit', $user)
            ->with('success', 'Usuário cadastrado com sucesso.');
    }

    public function edit(User $user): View
    {
        $this->ensureUserBelongsToCurrentCompany($user);

        return view('users.edit', [
            'user' => $user,
            'roles' => UserRole::cases(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $this->ensureUserBelongsToCurrentCompany($user);

        $validated = $request->validated();

        if ($request->user()->is($user) && ! $validated['active']) {
            return back()
                ->withErrors(['active' => 'Você não pode desativar o próprio usuário.'])
                ->withInput();
        }

        if ($validated['password'] === null) {
            unset($validated['password']);
        }

        $user->update($validated);
        $company = app('currentCompany');

        CompanyMembership::query()->updateOrCreate(
            ['company_id' => $company->id, 'user_id' => $user->id],
            ['role' => $this->companyRole($user->role), 'active' => $user->active],
        );
        BranchMembership::query()
            ->where('user_id', $user->id)
            ->whereHas('branch', fn ($query) => $query->where('company_id', $company->id))
            ->update(['role' => $this->branchRole($user->role), 'active' => $user->active]);

        return redirect()
            ->route('users.edit', $user)
            ->with('success', 'Usuário atualizado com sucesso.');
    }

    private function companyRole(UserRole $role): CompanyRole
    {
        return $role === UserRole::Admin ? CompanyRole::CompanyAdmin : CompanyRole::Member;
    }

    private function branchRole(UserRole $role): BranchRole
    {
        return match ($role) {
            UserRole::Admin => BranchRole::Manager,
            UserRole::Cashier => BranchRole::Cashier,
            UserRole::Attendant => BranchRole::Attendant,
        };
    }

    private function ensureUserBelongsToCurrentCompany(User $user): void
    {
        abort_unless(
            $user->companyMemberships()
                ->where('company_id', app('currentCompany')->id)
                ->where('active', true)
                ->exists(),
            404,
        );
    }
}
