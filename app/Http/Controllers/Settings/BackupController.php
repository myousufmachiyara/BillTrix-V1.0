<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\BackupService;
use App\Support\Audit;
use App\Support\Tenant;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class BackupController extends Controller
{
    public function index(BackupService $backups): View
    {
        $this->allow('backups.download');

        return view('settings.backup', ['server' => array_slice($backups->list(), 0, 10)]);
    }

    /** Everything this business owns, as a zip of JSON files. */
    public function download(BackupService $backups): BinaryFileResponse
    {
        $this->allow('backups.download');
        $file = $backups->exportBusiness();
        Audit::event('exported', null, ['report' => 'full data export']);

        return response()->download($file, Str::slug(Tenant::business()->name).'-data-'.now()->format('Y-m-d').'.zip')->deleteFileAfterSend();
    }
}
