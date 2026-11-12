<?php

namespace App\Support;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Throwable;

/** Writes the audit trail. Never lets a logging problem break the user's action. */
class Audit
{
    /** Fields that change on every save or are secret. */
    private const IGNORE = ['created_at', 'updated_at', 'remember_token', 'last_login_at', 'avg_cost', 'paid_amount', 'fulfilled_quantity', 'fbr_attempts'];

    private const SECRET = ['password', 'fbr_token'];

    public static function model(string $event, Model $model): void
    {
        $old = $event === 'created' ? [] : self::clean($event === 'deleted' ? $model->getOriginal() : array_intersect_key($model->getOriginal(), $model->getChanges()));
        $new = $event === 'deleted' ? [] : self::clean($event === 'created' ? $model->getAttributes() : $model->getChanges());

        if ($event === 'updated' && ! $new) {
            return;
        }

        self::write($event, $model, $old, $new);
    }

    public static function event(string $event, ?Model $subject = null, array $data = [], ?int $businessId = null, ?int $userId = null): void
    {
        self::write($event, $subject, [], $data, $businessId, $userId);
    }

    private static function write(string $event, ?Model $model, array $old, array $new, ?int $businessId = null, ?int $userId = null): void
    {
        try {
            $businessId ??= $model?->getAttribute('business_id') ?? Tenant::id();
            if (! $businessId) {
                return;
            }
            AuditLog::withoutGlobalScopes()->create([
                'business_id' => $businessId,
                'user_id' => $userId ?? auth()->id(),
                'event' => $event,
                'auditable_type' => $model?->getMorphClass(),
                'auditable_id' => $model?->getKey(),
                'label' => $model ? self::label($model) : null,
                'old_values' => $old ?: null,
                'new_values' => $new ?: null,
                'ip_address' => app()->runningInConsole() ? null : request()->ip(),
                'user_agent' => app()->runningInConsole() ? 'console' : mb_substr((string) request()->userAgent(), 0, 250),
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    private static function clean(array $values): array
    {
        $out = [];
        foreach ($values as $k => $v) {
            if (in_array($k, self::IGNORE, true)) {
                continue;
            }
            $out[$k] = in_array($k, self::SECRET, true) ? '••••••' : (is_string($v) && mb_strlen($v) > 500 ? mb_substr($v, 0, 500).'…' : $v);
        }

        return $out;
    }

    public static function label(Model $model): string
    {
        foreach (['number', 'name', 'code', 'email'] as $field) {
            if ($v = $model->getAttribute($field)) {
                return (string) $v;
            }
        }

        return class_basename($model).' #'.$model->getKey();
    }
}
