<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Menu\Models\Combo;
use App\Modules\Menu\Models\ProductImage;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Imagens para a gestão do cardápio (inclui produtos inativos). Exige menu.manage. */
class MenuImageController
{
    public function product(ProductImage $image): Response
    {
        return $this->file($image->path);
    }

    public function combo(Combo $combo): Response
    {
        abort_unless($combo->image_path, 404);

        return $this->file($combo->image_path);
    }

    private function file(string $path): Response
    {
        abort_unless(Storage::disk('tenant')->exists($path), 404);

        return Storage::disk('tenant')->response($path, headers: ['Cache-Control' => 'private, max-age=300']);
    }
}
