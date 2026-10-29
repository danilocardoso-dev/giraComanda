<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductPhoto;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProductPhotoController extends Controller
{
    public function __invoke(Product $product, ?ProductPhoto $photo = null): StreamedResponse
    {
        $path = $photo?->path ?? $product->photo_path;

        abort_if($path === null || ! Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->response($path, null, [
            'Cache-Control' => 'private, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
