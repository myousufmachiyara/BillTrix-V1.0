<?php

namespace App\Support;

use App\Models\Business;
use RuntimeException;

/**
 * Holds the business (tenant) the current request or job is working for.
 * Every tenant-owned model is automatically filtered and stamped with it.
 */
class Tenant
{
    private static ?int $id = null;

    private static ?Business $business = null;

    public static function set(Business|int|null $business): void
    {
        self::$business = $business instanceof Business ? $business : null;
        self::$id = $business instanceof Business ? $business->id : $business;

        if (self::$id && function_exists('setPermissionsTeamId')) {
            setPermissionsTeamId(self::$id);
        }
    }

    public static function id(): ?int
    {
        // hasUser() never queries, so resolving the signed-in user can't recurse back into this scope.
        if (self::$id === null && auth()->hasUser()) {
            self::set(auth()->user()->business_id);
        }

        return self::$id;
    }

    public static function idOrFail(): int
    {
        return self::id() ?? throw new RuntimeException('No business selected.');
    }

    public static function business(): Business
    {
        if (! self::$business || self::$business->id !== self::idOrFail()) {
            self::$business = Business::findOrFail(self::idOrFail());
        }

        return self::$business;
    }

    public static function forget(): void
    {
        self::$id = null;
        self::$business = null;
    }

    /** Run a callback as a given business, restoring the previous one afterwards. */
    public static function run(Business|int $business, callable $callback): mixed
    {
        [$prevId, $prevBusiness] = [self::$id, self::$business];
        self::set($business);

        try {
            return $callback();
        } finally {
            self::$id = $prevId;
            self::$business = $prevBusiness;
            if ($prevId && function_exists('setPermissionsTeamId')) {
                setPermissionsTeamId($prevId);
            }
        }
    }
}
