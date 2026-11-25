<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SalesEnquiry extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['name', 'email', 'business', 'message'];

    protected function casts(): array
    {
        return ['name' => 'encrypted', 'email' => 'encrypted', 'business' => 'encrypted', 'message' => 'encrypted', 'sent_at' => 'datetime'];
    }
}
