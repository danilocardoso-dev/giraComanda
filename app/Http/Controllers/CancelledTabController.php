<?php

namespace App\Http\Controllers;

use App\Actions\CancelTab;
use App\Http\Requests\CancelTabRequest;
use App\Models\Tab;
use Illuminate\Http\RedirectResponse;

class CancelledTabController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(CancelTabRequest $request, Tab $tab, CancelTab $cancelTab): RedirectResponse
    {
        $cancelTab->handle($tab, $request->user(), $request->string('reason')->trim()->toString());

        return redirect()->route('tabs.show', $tab)->with('success', 'Comanda cancelada e valores estornados.');
    }
}
