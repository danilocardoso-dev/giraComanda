<?php

namespace App\Http\Controllers;

use App\Actions\BuildManagementReport;
use App\CompanyRole;
use App\Http\Requests\ManagementReportRequest;
use App\Models\Branch;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;

class ManagementReportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ManagementReportRequest $request, BuildManagementReport $buildManagementReport): View
    {
        $periodStart = CarbonImmutable::createFromFormat('!Y-m-d', $request->string('start_date')->toString());
        $periodEnd = CarbonImmutable::createFromFormat('!Y-m-d', $request->string('end_date')->toString());
        $company = app('currentCompany');
        $branchSelection = $request->string('branch_id')->toString();
        $selectedBranch = $branchSelection === 'all'
            ? null
            : Branch::query()->whereBelongsTo($company)->findOrFail((int) $branchSelection);
        $isCompanyAdmin = $request->user()->companyMemberships()
            ->where('company_id', $company->id)
            ->where('role', CompanyRole::CompanyAdmin)
            ->where('active', true)
            ->exists();
        $reportBranches = $isCompanyAdmin
            ? $company->branches()->orderBy('name')->get()
            : Branch::query()->whereKey(app('currentBranch')->id)->get();

        return view('reports.index', [
            'startDate' => $periodStart->toDateString(),
            'endDate' => $periodEnd->toDateString(),
            'periodLabel' => $periodStart->format('d/m/Y').' a '.$periodEnd->format('d/m/Y'),
            'branchSelection' => $branchSelection,
            'selectedBranch' => $selectedBranch,
            'reportBranches' => $reportBranches,
            'canViewCompanyReport' => $isCompanyAdmin,
            'reportScopeLabel' => $selectedBranch?->name ?? 'Todas as filiais',
            ...$buildManagementReport->handle($periodStart, $periodEnd, $company, $selectedBranch),
        ]);
    }
}
