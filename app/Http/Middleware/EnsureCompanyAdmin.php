<?php

namespace App\Http\Middleware;

use App\CompanyRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCompanyAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isPlatformSupport = $request->user()?->platformAdmin()->exists()
            && (int) $request->session()->get('support_company_id') === app('currentCompany')->id;

        abort_unless(
            $isPlatformSupport || $request->user()?->companyMemberships()
                ->where('company_id', app('currentCompany')->id)
                ->where('role', CompanyRole::CompanyAdmin)
                ->where('active', true)
                ->exists(),
            Response::HTTP_FORBIDDEN,
        );

        return $next($request);
    }
}
