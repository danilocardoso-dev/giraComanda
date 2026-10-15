<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreBranchRequest;
use App\Http\Requests\UpdateBranchRequest;
use App\Models\Branch;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;

class CompanyBranchController extends Controller
{
    public function index(): View
    {
        $company = app('currentCompany');

        return view('company-branches.index', [
            'company' => $company,
            'branches' => Branch::query()
                ->where('company_id', $company->id)
                ->orderByDesc('active')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(StoreBranchRequest $request): RedirectResponse
    {
        $company = app('currentCompany');
        $name = $request->string('name')->toString();

        $branch = Branch::query()->create([
            'company_id' => $company->id,
            'name' => $name,
            'slug' => $this->uniqueSlug($company->id, $name),
            'active' => true,
        ]);

        return back()->with('success', "Filial {$branch->name} cadastrada com sucesso.");
    }

    public function update(UpdateBranchRequest $request, Branch $branch): RedirectResponse
    {
        $active = $request->boolean('active');

        if (! $active && $branch->id === app('currentBranch')->id) {
            return back()->withErrors(['active' => 'Troque de filial antes de desativar a filial atualmente em uso.']);
        }

        if (! $active && Branch::query()->where('company_id', $branch->company_id)->where('active', true)->count() === 1) {
            return back()->withErrors(['active' => 'A empresa precisa manter pelo menos uma filial ativa.']);
        }

        $branch->update([
            'name' => $request->string('name')->toString(),
            'active' => $active,
        ]);

        return back()->with('success', "Filial {$branch->name} atualizada com sucesso.");
    }

    private function uniqueSlug(int $companyId, string $name): string
    {
        $baseSlug = Str::slug($name) ?: 'filial';
        $slug = $baseSlug;
        $suffix = 2;

        while (Branch::query()->where('company_id', $companyId)->where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
