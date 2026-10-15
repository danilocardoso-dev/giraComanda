<?php

namespace App\Http\Controllers;

use App\Actions\RecordCashMovement;
use App\CashMovementType;
use App\Http\Requests\StoreCashMovementRequest;
use App\Models\CashSession;
use Illuminate\Http\RedirectResponse;

class CashMovementController extends Controller
{
    public function store(StoreCashMovementRequest $request, CashSession $cashSession, RecordCashMovement $recordCashMovement): RedirectResponse
    {
        $type = CashMovementType::from($request->string('type')->toString());
        $amountCents = (int) round($request->float('amount') * 100);

        $recordCashMovement->handle(
            $cashSession,
            $request->user(),
            $type,
            $amountCents,
            $request->string('description')->toString(),
        );

        return redirect()->route('cash.index')->with('success', "{$type->label()} registrado com sucesso.");
    }
}
