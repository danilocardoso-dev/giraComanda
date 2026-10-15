<?php

namespace App\Http\Controllers;

use App\Actions\CreateCompany;
use App\Actions\SynchronizeSubscriptionInvoices;
use App\Http\Requests\StorePlatformCompanyRequest;
use App\Http\Requests\UpdatePlatformCompanyRequest;
use App\Models\Company;
use App\Models\PlatformActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

class PlatformCompanyController extends Controller
{
    public function index(SynchronizeSubscriptionInvoices $synchronizeSubscriptionInvoices): View
    {
        $synchronizeSubscriptionInvoices->handleAll();

        $companies = Company::query()
            ->withCount(['branches', 'memberships'])
            ->with([
                'branches' => fn ($query) => $query->orderBy('name'),
                'subscription',
                'memberships.user',
            ])
            ->orderByDesc('active')
            ->orderBy('name')
            ->paginate(15);

        return view('platform.companies.index', compact('companies'));
    }

    public function store(StorePlatformCompanyRequest $request, CreateCompany $createCompany): RedirectResponse
    {
        $company = $createCompany->handle($request->validated());
        PlatformActivityLog::record($request, 'company_created', "Cliente {$company->name} cadastrado.", $company);

        return redirect()
            ->route('platform.companies.index')
            ->with('success', "Estabelecimento {$company->name} criado com sucesso.");
    }

    public function update(UpdatePlatformCompanyRequest $request, Company $company): RedirectResponse
    {
        $company->update(['active' => $request->boolean('active')]);
        PlatformActivityLog::record(
            $request,
            $company->active ? 'company_activated' : 'company_deactivated',
            $company->active ? 'Acesso geral da empresa reativado.' : 'Acesso geral da empresa desativado.',
            $company,
        );

        return back()->with(
            'success',
            $company->active
                ? "Estabelecimento {$company->name} ativado."
                : "Estabelecimento {$company->name} desativado.",
        );
    }
}
