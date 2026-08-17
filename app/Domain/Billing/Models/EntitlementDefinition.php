<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;

class EntitlementDefinition extends Model
{
    protected $fillable = ['key', 'value_type', 'unit', 'name', 'description'];
}
