<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateEstablishmentSettingRequest;
use App\Models\EstablishmentSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class EstablishmentSettingController extends Controller
{
    public function edit(): View
    {
        $settings = EstablishmentSetting::query()->first() ?? new EstablishmentSetting([
            'name' => config('giracomanda.establishment_name'),
            'general_due_day' => config('giracomanda.general_due_day'),
            'currency' => 'BRL',
        ]);

        return view('settings.edit', compact('settings'));
    }

    public function update(UpdateEstablishmentSettingRequest $request): RedirectResponse
    {
        $settings = EstablishmentSetting::query()->first() ?? new EstablishmentSetting;
        $previousLogoPath = $settings->logo_path;
        $newLogoPath = null;

        try {
            if ($request->hasFile('logo')) {
                $newLogoPath = $request->file('logo')->store('company-logos', 'public');

                if ($newLogoPath === false) {
                    throw new RuntimeException('Não foi possível armazenar a logo.');
                }
            }

            $settings->fill([
                ...$request->safe()->except(['logo']),
                'logo_path' => $newLogoPath ?? $previousLogoPath,
                'currency' => 'BRL',
            ])->save();
        } catch (Throwable $exception) {
            if ($newLogoPath !== null) {
                Storage::disk('public')->delete($newLogoPath);
            }

            throw $exception;
        }

        if ($newLogoPath !== null && $previousLogoPath !== null) {
            Storage::disk('public')->delete($previousLogoPath);
        }

        return redirect()
            ->route('settings.edit')
            ->with('success', $newLogoPath === null
                ? 'Configurações atualizadas com sucesso.'
                : 'Configurações e identidade visual atualizadas com sucesso.');
    }
}
