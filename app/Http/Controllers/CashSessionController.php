<?php

namespace App\Http\Controllers;

use App\Actions\OpenCashSession;
use App\Http\Requests\OpenCashSessionRequest;
use Illuminate\Http\RedirectResponse;

class CashSessionController extends Controller
{
    public function store(OpenCashSessionRequest $request, OpenCashSession $openCashSession): RedirectResponse
    {
        $openingAmountCents = (int) round($request->float('opening_amount') * 100);

        $openCashSession->handle($request->user(), $openingAmountCents);

        return redirect()->route('cash.index')->with('success', 'Caixa aberto com sucesso.');
    }
}
