<?php

namespace App\Http\Middleware;

use App\CardStatus;
use App\Models\Card;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveMenuCardContext
{
    /**
     * Resolve the tenant of a public menu from the card QR token, without authentication.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $card = Card::query()
            ->with('branch.company.subscription')
            ->where('qr_token', (string) $request->route('qrToken'))
            ->first();
        $branch = $card?->branch;
        $company = $branch?->company;
        $subscription = $company?->subscription;

        abort_if(
            $card === null
                || $card->status === CardStatus::Blocked
                || ! $branch->active
                || ! $company->active
                || ($subscription !== null && ! $subscription->allowsAccess()),
            Response::HTTP_NOT_FOUND,
        );

        app()->instance('currentBranch', $branch);
        app()->instance('currentCompany', $company);
        $request->attributes->set('menuCard', $card);

        try {
            return $next($request);
        } finally {
            app()->forgetInstance('currentBranch');
            app()->forgetInstance('currentCompany');
        }
    }
}
