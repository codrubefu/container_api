<?php

namespace App\Models\Erp;

use Illuminate\Database\Eloquent\Attributes\Connection;
use Illuminate\Database\Eloquent\Model;

/**
 * Base model for the pre-existing ERP tables, which are not prefixed with the app's DB_PREFIX.
 */
#[Connection('erp')]
abstract class ErpModel extends Model {}
