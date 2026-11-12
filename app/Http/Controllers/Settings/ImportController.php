<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Services\ImportService;
use App\Services\SpreadsheetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ImportController extends Controller
{
    private const TITLES = ['products' => 'Import Products', 'parties' => 'Import Customers & Suppliers'];

    public function show(string $importType): View
    {
        $type = $importType;
        $this->allow("imports.{$type}");

        return view('settings.import', ['type' => $type, 'title' => self::TITLES[$type], 'template' => ImportService::TEMPLATES[$type]]);
    }

    public function template(string $importType, SpreadsheetService $sheets): StreamedResponse
    {
        $type = $importType;
        $this->allow("imports.{$type}");
        $t = ImportService::TEMPLATES[$type];

        return $sheets->download("billtrix-{$type}-template", $t['headings'], [$t['sample']]);
    }

    public function store(Request $request, string $importType, SpreadsheetService $sheets, ImportService $imports): RedirectResponse
    {
        $type = $importType;
        $this->allow("imports.{$type}");
        $request->validate(['file' => ['required', 'file', 'max:5120', 'mimes:xlsx,xls,csv,txt']]);

        $rows = $sheets->read($request->file('file'));
        if (! $rows) {
            return back()->with('error', __('The file has no data rows.'));
        }
        if (count($rows) > 5000) {
            return back()->with('error', __('Please import at most 5,000 rows at a time.'));
        }

        $result = $imports->{$type}($rows);
        if ($result['errors']) {
            return back()->with('import_errors', $result['errors'])->with('error', count($result['errors']).' row(s) need fixing. Nothing was imported.');
        }

        return back()->with('success', __('Imported: :n new, :m updated.', ['n' => $result['created'], 'm' => $result['updated']]));
    }
}
