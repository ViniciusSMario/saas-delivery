<?php

namespace App\Tenancy\Resolvers;

use App\Tenancy\Models\Tenant;
use Illuminate\Http\Request;

interface TenantResolver
{
    /** Devolve o tenant da requisição ou null se este resolver não se aplica. */
    public function resolve(Request $request): ?Tenant;
}
