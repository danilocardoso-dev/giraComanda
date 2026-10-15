<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BranchContextController extends Controller
{
    public function index(Request $request): View|RedirectResponse
    {
        $branches = $request->user()->accessibleBranchesQuery()
            ->orderBy('company_id')
            ->orderBy('branches.name')
            ->get();

        abort_if($branches->isEmpty(), 403, 'Usuário sem acesso a uma filial ativa.');

        if ($branches->count() === 1) {
            $request->session()->put('active_branch_id', $branches->first()->id);

            return redirect()->route('dashboard');
        }

        return view('branches.index', ['branches' => $branches]);
    }

    public function update(Request $request, Branch $branch): RedirectResponse
    {
        $authorizedBranch = $request->user()->accessibleBranchesQuery()->findOrFail($branch->id);
        $request->session()->put('active_branch_id', $authorizedBranch->id);

        return redirect()
            ->route('dashboard')
            ->with('success', "Filial {$authorizedBranch->name} selecionada.");
    }
}
