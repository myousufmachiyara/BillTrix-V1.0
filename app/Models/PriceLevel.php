<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class PriceLevel extends Model
{
    use Auditable, BelongsToBusiness;

    protected $fillable = ['name', 'is_default'];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }
}
