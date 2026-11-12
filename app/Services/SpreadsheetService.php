<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Reading and writing .xlsx / .csv with PhpSpreadsheet. */
class SpreadsheetService
{
    /**
     * @param  array<int, string>  $headings
     * @param  iterable<int, array>  $rows  numeric values stay numeric in Excel
     */
    public function download(string $filename, array $headings, iterable $rows, ?string $title = null): StreamedResponse
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setTitle(mb_substr(preg_replace('/[\\\\\/?*\[\]:]/', '', $title ?? 'Sheet1'), 0, 31) ?: 'Sheet1');

        $r = 1;
        if ($title) {
            $sheet->setCellValue('A1', $title);
            $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
            $r = 3;
        }
        $sheet->fromArray($headings, null, 'A'.$r);
        $sheet->getStyle('A'.$r.':'.$sheet->getHighestColumn().$r)->getFont()->setBold(true);
        $headerRow = $r;

        foreach ($rows as $row) {
            $sheet->fromArray(array_values(array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('Y-m-d') : $v, $row)), null, 'A'.(++$r), true);
        }

        $last = $sheet->getHighestColumn();
        foreach (range(1, \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($last)) as $i) {
            $col = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($i);
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        if ($r > $headerRow) {
            $sheet->getStyle('A'.($headerRow + 1).':'.$last.$r)->getNumberFormat()->setFormatCode(NumberFormat::FORMAT_GENERAL);
        }
        $sheet->freezePane('A'.($headerRow + 1));

        $name = preg_replace('/[^A-Za-z0-9\-_]+/', '-', $filename).'.xlsx';

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /**
     * Rows of the first sheet keyed by normalised heading ("Sale Price" → "sale_price").
     * Blank rows are skipped. Keys are 1-based spreadsheet row numbers for error messages.
     */
    public function read(UploadedFile|string $file): array
    {
        $path = $file instanceof UploadedFile ? $file->getRealPath() : $file;
        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, false, false);
        if (! $rows) {
            return [];
        }

        $headings = array_map(fn ($h) => trim(preg_replace('/[^a-z0-9]+/', '_', strtolower(preg_replace('/\*|\(.*?\)/', '', (string) $h))), '_'), array_shift($rows));
        $out = [];
        foreach ($rows as $i => $row) {
            if (count(array_filter($row, fn ($v) => $v !== null && trim((string) $v) !== '')) === 0) {
                continue;
            }
            $assoc = [];
            foreach ($headings as $c => $key) {
                if ($key !== '') {
                    $value = $row[$c] ?? null;
                    $assoc[$key] = is_string($value) ? trim($value) : $value;
                }
            }
            $out[$i + 2] = $assoc;
        }

        return $out;
    }
}
