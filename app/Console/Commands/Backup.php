<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;

class Backup extends Command
{
    protected $signature = 'billtrix:backup {--keep=14 : Days of backups to keep}';

    protected $description = 'Back up the whole database to storage/app/backups and remove old backups';

    public function handle(BackupService $backups): int
    {
        $path = $backups->server();
        $removed = $backups->prune((int) $this->option('keep'));
        $this->info("Backup written to storage/app/{$path}".($removed ? "; removed {$removed} old backup(s)." : '.'));

        return self::SUCCESS;
    }
}
