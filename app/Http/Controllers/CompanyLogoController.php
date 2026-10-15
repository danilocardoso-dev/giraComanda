<?php

namespace App\Http\Controllers;

use App\Models\EstablishmentSetting;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyLogoController extends Controller
{
    public function __invoke(): StreamedResponse
    {
        $logoPath = EstablishmentSetting::query()->value('logo_path');

        abort_if($logoPath === null || ! Storage::disk('public')->exists($logoPath), 404);

        return Storage::disk('public')->response($logoPath, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
