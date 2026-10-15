<?php

namespace App\Http\Middleware;

use App\CompanyRole;
use App\Models\Branch;
use App\Models\EstablishmentSetting;
use App\UserRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranchContext
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        abort_unless($user !== null, Response::HTTP_UNAUTHORIZED);

        $isPlatformAdmin = $user->platformAdmin()->exists();
        $supportMode = false;

        if ($isPlatformAdmin) {
            $branch = Branch::query()
                ->with('company')
                ->where('company_id', (int) $request->session()->get('support_company_id'))
                ->find((int) $request->session()->get('support_branch_id'));

            if ($branch === null) {
                $request->session()->forget(['support_company_id', 'support_branch_id', 'active_branch_id']);

                return redirect()->route('platform.dashboard');
            }

            $effectiveRole = UserRole::Admin;
            $availableBranchesCount = $branch->company->branches()->count();
            $isCompanyAdmin = true;
            $supportMode = true;
        } else {
            $accessibleBranches = $user->accessibleBranchesQuery()
                ->orderBy('company_id')
                ->orderBy('branches.name')
                ->get();

            abort_if($accessibleBranches->isEmpty(), Response::HTTP_FORBIDDEN, 'Usuário sem acesso a uma filial ativa.');

            $branch = $accessibleBranches->firstWhere('id', (int) $request->session()->get('active_branch_id'));

            if ($branch === null && $accessibleBranches->count() === 1) {
                $branch = $accessibleBranches->first();
                $request->session()->put('active_branch_id', $branch->id);
            }

            if ($branch === null) {
                $request->session()->forget('active_branch_id');

                return redirect()->route('branches.index');
            }

            $effectiveRole = $user->roleForBranch($branch);
            $availableBranchesCount = $accessibleBranches->count();
            $isCompanyAdmin = $user->companyMemberships()
                ->where('company_id', $branch->company_id)
                ->where('role', CompanyRole::CompanyAdmin)
                ->where('active', true)
                ->exists();
        }

        app()->instance('currentBranch', $branch);
        app()->instance('currentCompany', $branch->company);
        app()->instance('currentUserRole', $effectiveRole);
        $establishmentSettings = EstablishmentSetting::query()->first();
        View::share('currentBranch', $branch);
        View::share('currentCompany', $branch->company);
        View::share('establishmentSettings', $establishmentSettings);
        View::share('availableBranchesCount', $availableBranchesCount);
        View::share('isPlatformAdmin', $isPlatformAdmin);
        View::share('isCompanyAdmin', $isCompanyAdmin);
        View::share('supportMode', $supportMode);

        try {
            return $next($request);
        } finally {
            app()->forgetInstance('currentBranch');
            app()->forgetInstance('currentCompany');
            app()->forgetInstance('currentUserRole');
        }
    }
}
