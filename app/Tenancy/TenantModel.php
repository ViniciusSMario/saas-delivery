<?php

namespace App\Tenancy;

use Illuminate\Database\Eloquent\Model;

/**
 * Base de todo model que vive no schema do estabelecimento.
 */
abstract class TenantModel extends Model
{
    protected $connection = 'tenant';
}
