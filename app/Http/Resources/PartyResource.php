<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\Party */
class PartyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'code' => $this->code,
            'name' => $this->name,
            'contact_person' => $this->contact_person,
            'phone' => $this->phone,
            'email' => $this->email,
            'address' => $this->address,
            'city' => $this->city,
            'province' => $this->province,
            'ntn' => $this->ntn,
            'cnic' => $this->cnic,
            'is_filer' => $this->is_filer,
            'credit_limit' => (float) $this->credit_limit,
            'credit_days' => $this->credit_days,
            'price_level_id' => $this->price_level_id,
            'is_walk_in' => $this->is_walk_in,
            'balance' => $this->when(isset($this->balance), fn () => round((float) $this->balance, 2)),
            'is_active' => $this->is_active,
        ];
    }
}
