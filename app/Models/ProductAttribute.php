<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A variation attribute such as Size or Colour. */
class ProductAttribute extends Model
{
    use Auditable, BelongsToBusiness;

    protected $table = 'attributes';

    protected $fillable = ['name', 'sort'];

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class, 'attribute_id')->orderBy('sort')->orderBy('id');
    }
}
