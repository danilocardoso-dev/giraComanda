<?php

namespace App\Http\Controllers;

use App\CashMovementType;
use App\CashSessionStatus;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Payment;
use App\PaymentMethod;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class CashController extends Controller
{
    public function __invoke(Request $request): View
    {
        $date = $request->string('date')->toString();

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $date = today()->toDateString();
        }

        $selectedDate = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        $paymentsForDate = Payment::query()->whereDate('paid_at', $selectedDate);
        $totalsByMethod = (clone $paymentsForDate)
            ->selectRaw('method, SUM(amount_cents) as total_cents')
            ->groupBy('method')
            ->pluck('total_cents', 'method');
        $openCashSession = CashSession::query()
            ->where('status', CashSessionStatus::Open)
            ->with(['opener', 'movements.recorder'])
            ->latest('opened_at')
            ->first();
        $cashPaymentsCents = 0;
        $suppliesCents = 0;
        $withdrawalsCents = 0;

        if ($openCashSession !== null) {
            $cashPaymentsCents = Payment::query()
                ->whereBelongsTo($openCashSession)
                ->where('method', PaymentMethod::Cash)
                ->sum('amount_cents');
            $suppliesCents = CashMovement::query()
                ->whereBelongsTo($openCashSession)
                ->where('type', CashMovementType::Supply)
                ->sum('amount_cents');
            $withdrawalsCents = CashMovement::query()
                ->whereBelongsTo($openCashSession)
                ->where('type', CashMovementType::Withdrawal)
                ->sum('amount_cents');
        }

        return view('cash.index', [
            'date' => $date,
            'selectedDate' => $selectedDate,
            'paymentMethods' => PaymentMethod::immediateCases(),
            'totalsByMethod' => $totalsByMethod,
            'totalCents' => (clone $paymentsForDate)->sum('amount_cents'),
            'openCashSession' => $openCashSession,
            'cashPaymentsCents' => $cashPaymentsCents,
            'suppliesCents' => $suppliesCents,
            'withdrawalsCents' => $withdrawalsCents,
            'expectedCashCents' => ($openCashSession?->opening_amount_cents ?? 0) + $cashPaymentsCents + $suppliesCents - $withdrawalsCents,
            'movementTypes' => CashMovementType::cases(),
            'recentCashSessions' => CashSession::query()
                ->with(['opener', 'closer'])
                ->orderByDesc('opened_at')
                ->orderByDesc('id')
                ->limit(10)
                ->get(),
            'payments' => (clone $paymentsForDate)
                ->with(['tab.card', 'tab.customer', 'customer', 'recorder'])
                ->orderByDesc('paid_at')
                ->orderByDesc('id')
                ->paginate(20)
                ->withQueryString(),
        ]);
    }
}
