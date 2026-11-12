<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;

class TaxRate extends Model
{
    use Auditable, BelongsToBusiness;

    protected $attributes = ['is_active' => true];

    protected $fillable = ['name', 'rate', 'is_active'];

    protected function casts(): array
    {
        return ['rate' => 'decimal:3', 'is_active' => 'boolean'];
    }
}
