<?php

namespace App\Models\Concerns;

/** Document lines: "T-Shirt — Red / M" when the line is for a variation. */
trait HasVariantName
{
    public function itemName(): string
    {
        $name = $this->loadMissing('product')->product->name;

        return $this->variant_id ? $name.' — '.$this->loadMissing('variant')->variant->name : $name;
    }

    public function itemSku(): ?string
    {
        return $this->variant_id ? $this->loadMissing('variant')->variant->sku : $this->loadMissing('product')->product->sku;
    }
}
