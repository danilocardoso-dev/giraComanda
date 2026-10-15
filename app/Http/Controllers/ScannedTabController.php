<?php

namespace App\Http\Controllers;

use App\Actions\OpenTab;
use App\CardStatus;
use App\Http\Requests\ScanTabRequest;
use App\Models\Card;
use App\Models\Tab;
use App\TabStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

class ScannedTabController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ScanTabRequest $request, OpenTab $openTab): RedirectResponse
    {
        $card = Card::query()
            ->where('qr_token', $request->string('qr_token')->toString())
            ->first();

        if ($card === null || $card->status === CardStatus::Blocked) {
            throw ValidationException::withMessages([
                'qr_token' => 'Este QR Code não pertence a um cartão ativo desta filial.',
            ]);
        }

        if ($card->status === CardStatus::InUse) {
            $tab = Tab::query()
                ->whereBelongsTo($card)
                ->where('status', TabStatus::Open)
                ->latest('id')
                ->first();

            if ($tab === null) {
                throw ValidationException::withMessages([
                    'qr_token' => 'O cartão consta como em uso, mas não possui uma comanda aberta. Solicite a correção ao administrador.',
                ]);
            }

            return redirect()->route('tabs.show', $tab)->with('success', "Comanda do cartão {$card->code} localizada.");
        }

        $tab = $openTab->handle($card, $request->user());

        return redirect()->route('tabs.show', $tab)->with('success', "Comanda do cartão {$card->code} aberta pela câmera.");
    }
}
