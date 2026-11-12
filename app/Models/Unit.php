<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use Auditable, BelongsToBusiness;

    protected $fillable = ['name', 'short_name', 'allow_decimal'];

    protected function casts(): array
    {
        return ['allow_decimal' => 'boolean'];
    }
}
