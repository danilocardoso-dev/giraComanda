<?php

namespace App\Http\Controllers;

use App\Actions\CloseCashSession;
use App\Http\Requests\CloseCashSessionRequest;
use App\Models\CashSession;
use Illuminate\Http\RedirectResponse;

class ClosedCashSessionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(CloseCashSessionRequest $request, CashSession $cashSession, CloseCashSession $closeCashSession): RedirectResponse
    {
        $countedAmountCents = (int) round($request->float('counted_amount') * 100);

        $closedSession = $closeCashSession->handle(
            $cashSession,
            $request->user(),
            $countedAmountCents,
            $request->string('notes')->toString(),
        );

        $message = $closedSession->difference_cents === 0
            ? 'Caixa fechado sem diferença.'
            : 'Caixa fechado com diferença de R$ '.number_format(abs($closedSession->difference_cents) / 100, 2, ',', '.').'.';

        return redirect()->route('cash.index')->with('success', $message);
    }
}
