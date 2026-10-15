<?php

namespace App\Http\Controllers;

use App\Http\Requests\StartPlatformSupportRequest;
use App\Models\Branch;
use App\Models\Company;
use App\Models\PlatformActivityLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlatformSupportSessionController extends Controller
{
    public function store(StartPlatformSupportRequest $request, Company $company): RedirectResponse
    {
        $branch = Branch::query()
            ->where('company_id', $company->id)
            ->findOrFail((int) $request->validated('branch_id'));

        $request->session()->put([
            'support_company_id' => $company->id,
            'support_branch_id' => $branch->id,
            'active_branch_id' => $branch->id,
        ]);

        PlatformActivityLog::record(
            $request,
            'support_started',
            "Modo suporte iniciado na filial {$branch->name}.",
            $company,
        );

        return redirect()->route('dashboard')->with('success', "Modo suporte iniciado em {$company->name} · {$branch->name}.");
    }

    public function destroy(Request $request): RedirectResponse
    {
        $company = Company::query()->find($request->session()->get('support_company_id'));

        if ($company !== null) {
            PlatformActivityLog::record($request, 'support_ended', 'Modo suporte encerrado.', $company);
        }

        $request->session()->forget(['support_company_id', 'support_branch_id', 'active_branch_id']);

        return $company === null
            ? redirect()->route('platform.dashboard')
            : redirect()->route('platform.companies.show', $company)->with('success', 'Modo suporte encerrado.');
    }
}
