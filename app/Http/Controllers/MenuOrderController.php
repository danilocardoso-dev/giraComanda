<?php

namespace App\Http\Controllers;

use App\Actions\AcceptMenuOrder;
use App\Http\Requests\RejectMenuOrderRequest;
use App\MenuOrderStatus;
use App\Models\MenuOrder;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class MenuOrderController extends Controller
{
    public function index(): View
    {
        return view('menu-orders.index', [
            'pendingOrders' => MenuOrder::query()
                ->with(['card', 'items'])
                ->where('status', MenuOrderStatus::Pending)
                ->oldest('id')
                ->get(),
            'reviewedOrders' => MenuOrder::query()
                ->with(['card', 'items', 'reviewer', 'tab'])
                ->where('status', '!=', MenuOrderStatus::Pending)
                ->whereDate('reviewed_at', today())
                ->latest('reviewed_at')
                ->limit(20)
                ->get(),
        ]);
    }

    public function pending(): JsonResponse
    {
        $pendingOrders = MenuOrder::query()->where('status', MenuOrderStatus::Pending);

        return response()->json([
            'count' => (clone $pendingOrders)->count(),
            'latest_id' => (int) $pendingOrders->max('id'),
        ]);
    }

    public function accept(Request $request, MenuOrder $menuOrder, AcceptMenuOrder $acceptMenuOrder): RedirectResponse
    {
        $tab = $acceptMenuOrder->handle($menuOrder, $request->user());

        return redirect()
            ->route('menu-orders.index')
            ->with('success', "Pedido #{$menuOrder->id} lançado na comanda do cartão {$tab->card->code}.");
    }

    public function reject(RejectMenuOrderRequest $request, MenuOrder $menuOrder): RedirectResponse
    {
        $rejected = MenuOrder::query()
            ->whereKey($menuOrder->getKey())
            ->where('status', MenuOrderStatus::Pending)
            ->update([
                'status' => MenuOrderStatus::Rejected,
                'rejection_reason' => $request->validated('rejection_reason'),
                'reviewed_by' => $request->user()->getKey(),
                'reviewed_at' => now(),
                'updated_at' => now(),
            ]);

        if ($rejected === 0) {
            throw ValidationException::withMessages(['menu_order' => 'Este pedido já foi analisado.']);
        }

        return redirect()->route('menu-orders.index')->with('success', "Pedido #{$menuOrder->id} recusado.");
    }
}
