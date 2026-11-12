<?php

namespace App\Services;

use App\Support\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use ZipArchive;

/**
 * Two kinds of backup:
 *  - server(): a full database dump for the operator (scheduled daily), kept for N days;
 *  - exportBusiness(): everything one business owns, as JSON in a zip, for the owner to download.
 */
class BackupService
{
    /** Child tables that hang off a business-owned parent: table => [foreign key, parent table]. */
    private const CHILDREN = [
        'payment_allocations' => ['journal_entry_id', 'journal_entries'],
        'product_units' => ['product_id', 'products'],
        'product_prices' => ['product_id', 'products'],
        'party_prices' => ['party_id', 'parties'],
        'invoice_items' => ['invoice_id', 'invoices'],
        'order_items' => ['order_id', 'orders'],
        'delivery_items' => ['delivery_id', 'deliveries'],
        'stock_adjustment_items' => ['stock_adjustment_id', 'stock_adjustments'],
        'stock_transfer_items' => ['stock_transfer_id', 'stock_transfers'],
        'batch_stocks' => ['batch_id', 'batches'],
        'attribute_values' => ['attribute_id', 'attributes'],
        'attribute_value_product_variant' => ['product_variant_id', 'product_variants'],
    ];

    private const HIDDEN = ['password', 'remember_token'];

    public function disk(): \Illuminate\Contracts\Filesystem\Filesystem
    {
        return Storage::disk('local');
    }

    /** Full database dump to storage/app/backups/*.sql.gz. Returns the stored path. */
    public function server(): string
    {
        $this->disk()->makeDirectory('backups');
        $name = 'backups/billtrix-'.now()->format('Ymd-His');
        $connection = DB::connection();
        $driver = $connection->getDriverName();

        if ($driver === 'sqlite' && is_file($connection->getDatabaseName())) {
            $path = $name.'.sqlite.gz';
            $this->disk()->put($path, gzencode(file_get_contents($connection->getDatabaseName()), 9));

            return $path;
        }

        $path = $name.'.sql.gz';
        $target = $this->disk()->path($path);

        // Prefer the database's own dump tool when it is installed.
        if (in_array($driver, ['mysql', 'mariadb'], true) && $this->dumpWithMysqldump($connection->getConfig(), $target)) {
            return $path;
        }

        $out = gzopen($target, 'wb9');
        try {
            $this->dumpSql($out);
        } finally {
            gzclose($out);
        }

        return $path;
    }

    /** Delete server backups older than $days. Returns how many were removed. */
    public function prune(int $days): int
    {
        $removed = 0;
        foreach ($this->disk()->files('backups') as $file) {
            if ($this->disk()->lastModified($file) < now()->subDays($days)->getTimestamp()) {
                $this->disk()->delete($file);
                $removed++;
            }
        }

        return $removed;
    }

    /** @return array<int, array{name:string, size:int, at:\Illuminate\Support\Carbon}> newest first */
    public function list(): array
    {
        return collect($this->disk()->files('backups'))
            ->map(fn ($f) => ['name' => basename($f), 'size' => $this->disk()->size($f), 'at' => \Illuminate\Support\Carbon::createFromTimestamp($this->disk()->lastModified($f))])
            ->sortByDesc('at')->values()->all();
    }

    /** Zip of one business's data (JSON per table). Returns an absolute temp file path. */
    public function exportBusiness(): string
    {
        $businessId = Tenant::idOrFail();
        $file = tempnam(sys_get_temp_dir(), 'bt-export-').'.zip';
        $zip = new ZipArchive;
        if ($zip->open($file, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the export file.');
        }

        $counts = [];
        $ids = [];
        foreach ($this->tables() as $table) {
            if ($table === 'businesses') {
                $rows = DB::table('businesses')->where('id', $businessId)->get();
            } elseif (Schema::hasColumn($table, 'business_id')) {
                $rows = DB::table($table)->where('business_id', $businessId)->orderBy('id')->get();
            } elseif (isset(self::CHILDREN[$table])) {
                [$fk, $parent] = self::CHILDREN[$table];
                $rows = DB::table($table)->whereIn($fk, $ids[$parent] ?? [])->orderBy(Schema::hasColumn($table, 'id') ? 'id' : $fk)->get();
            } else {
                continue;
            }

            $rows = $rows->map(function ($r) {
                $r = (array) $r;
                foreach (self::HIDDEN as $h) {
                    unset($r[$h]);
                }
                if (array_key_exists('settings', $r) && $r['settings']) {
                    $s = json_decode($r['settings'], true) ?: [];
                    unset($s['fbr_token']); // never export secrets
                    $r['settings'] = $s;
                }

                return $r;
            });
            $ids[$table] = $rows->pluck('id')->all();
            $counts[$table] = $rows->count();
            $zip->addFromString("data/{$table}.json", json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        $zip->addFromString('README.txt', implode("\n", [
            'BillTrix data export',
            'Business #'.$businessId.' — '.Tenant::business()->name,
            'Created '.now()->toDateTimeString(),
            '',
            'One JSON file per table in data/. Passwords and the FBR token are not included.',
            '',
            ...collect($counts)->map(fn ($n, $t) => str_pad($t, 28).$n.' rows')->values()->all(),
        ]));
        $zip->close();

        return $file;
    }

    /** Ordered so parents come before their child tables. */
    private function tables(): array
    {
        $order = ['businesses', 'branches', 'warehouses', 'users', 'document_sequences', 'tax_rates', 'price_levels', 'accounts', 'parties', 'party_prices',
            'units', 'categories', 'attributes', 'attribute_values', 'products', 'product_variants', 'attribute_value_product_variant', 'product_units', 'product_prices', 'batches', 'batch_stocks', 'stock_balances', 'stock_movements',
            'invoices', 'invoice_items', 'orders', 'order_items', 'deliveries', 'delivery_items', 'journal_entries', 'journal_lines', 'payment_allocations',
            'stock_adjustments', 'stock_adjustment_items', 'stock_transfers', 'stock_transfer_items', 'cash_sessions', 'audit_logs'];

        return array_values(array_filter($order, fn ($t) => Schema::hasTable($t)));
    }

    private function dumpWithMysqldump(array $c, string $target): bool
    {
        $bin = trim((string) @shell_exec('command -v mysqldump'));
        if ($bin === '') {
            return false;
        }
        $result = \Illuminate\Support\Facades\Process::env(['MYSQL_PWD' => (string) ($c['password'] ?? '')])->timeout(3600)->run(sprintf(
            '%s --single-transaction --quick --routines --host=%s --port=%s --user=%s %s | gzip -9 > %s',
            escapeshellarg($bin), escapeshellarg($c['host'] ?? '127.0.0.1'), escapeshellarg((string) ($c['port'] ?? 3306)),
            escapeshellarg($c['username'] ?? 'root'), escapeshellarg($c['database']), escapeshellarg($target),
        ));

        return $result->successful() && filesize($target) > 100;
    }

    /** Plain SQL dump (structure + data) written through a gzip handle; works without mysqldump. */
    private function dumpSql($out): void
    {
        $pdo = DB::connection()->getPdo();
        $driver = DB::connection()->getDriverName();
        gzwrite($out, '-- BillTrix backup '.now()->toDateTimeString()."\n");

        if ($driver === 'mysql' || $driver === 'mariadb') {
            gzwrite($out, "SET FOREIGN_KEY_CHECKS=0;\n");
        }

        foreach (Schema::getTableListing() as $table) {
            $table = str_contains($table, '.') ? substr($table, strrpos($table, '.') + 1) : $table;
            if ($driver === 'mysql' || $driver === 'mariadb') {
                $create = (array) DB::selectOne("SHOW CREATE TABLE `{$table}`");
                gzwrite($out, "\nDROP TABLE IF EXISTS `{$table}`;\n".array_values($create)[1].";\n");
            }
            foreach (DB::table($table)->cursor() as $row) {
                $row = (array) $row;
                $values = array_map(fn ($v) => $v === null ? 'NULL' : $pdo->quote((string) $v), array_values($row));
                gzwrite($out, 'INSERT INTO '.$table.' ('.implode(',', array_keys($row)).') VALUES ('.implode(',', $values).");\n");
            }
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            gzwrite($out, "SET FOREIGN_KEY_CHECKS=1;\n");
        }
    }
}
