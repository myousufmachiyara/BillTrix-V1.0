<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Arr;

class Business extends Model
{
    use Auditable;

    protected $attributes = ['is_active' => true, 'category' => 'retail', 'currency' => 'PKR'];

    protected $fillable = ['name', 'category', 'legal_name', 'ntn', 'strn', 'phone', 'email', 'address', 'province', 'logo_path', 'currency', 'settings', 'is_active'];

    protected function casts(): array
    {
        return ['settings' => 'array', 'is_active' => 'boolean'];
    }

    /** Effective setting: business override, else category preset, else global default. */
    public function setting(string $key, mixed $default = null): mixed
    {
        return Arr::get($this->allSettings(), $key, $default);
    }

    public function feature(string $key): bool
    {
        return (bool) $this->setting($key, false);
    }

    public function allSettings(): array
    {
        return array_replace(
            config('billtrix.settings', []),
            config("billtrix.presets.{$this->category}", []),
            $this->settings ?? [],
        );
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
