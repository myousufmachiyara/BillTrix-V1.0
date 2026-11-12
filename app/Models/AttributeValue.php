<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/** One value of an attribute, e.g. "M" for Size. */
class AttributeValue extends Model
{
    protected $fillable = ['attribute_id', 'value', 'code', 'sort'];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(ProductAttribute::class, 'attribute_id');
    }

    public function variants(): BelongsToMany
    {
        return $this->belongsToMany(ProductVariant::class, 'attribute_value_product_variant');
    }

    /** Short code used in generated SKUs. */
    public function skuCode(): string
    {
        $code = $this->code ?: $this->value;

        return strtoupper(preg_replace('/[^A-Za-z0-9]+/', '', $code)) ?: (string) $this->id;
    }
}
