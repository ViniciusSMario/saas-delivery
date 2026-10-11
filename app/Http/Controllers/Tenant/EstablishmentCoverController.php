<?php

namespace App\Http\Controllers\Tenant;

use App\Modules\Establishment\Models\Establishment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Foto de capa do cardápio, servida do disco do tenant (o caminho nunca vem da URL). */
class EstablishmentCoverController
{
    public function __invoke(): Response
    {
        $path = Establishment::current()->cover_path;

        abort_unless($path && Storage::disk('tenant')->exists($path), 404);

        return Storage::disk('tenant')->response($path, headers: ['Cache-Control' => 'public, max-age=300']);
    }
}
