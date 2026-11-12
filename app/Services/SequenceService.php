<?php

namespace App\Services;

use App\Models\DocumentSequence;

class SequenceService
{
    /** Atomically reserve the next document number (call inside a DB transaction). */
    public function next(string $type): string
    {
        $seq = DocumentSequence::where('type', $type)->lockForUpdate()->first();

        if (! $seq) {
            $seq = DocumentSequence::create([
                'type' => $type,
                'prefix' => config("billtrix.sequences.{$type}", strtoupper(substr($type, 0, 2))),
                'next_number' => 1,
            ]);
            $seq = DocumentSequence::whereKey($seq->id)->lockForUpdate()->first();
        }

        $number = $seq->prefix.'-'.str_pad((string) $seq->next_number, $seq->padding, '0', STR_PAD_LEFT);
        $seq->increment('next_number');

        return $number;
    }

    /** Next raw counter value for a sequence (no prefix/padding). */
    public function nextNumber(string $type): int
    {
        $seq = DocumentSequence::where('type', $type)->lockForUpdate()->first()
            ?? DocumentSequence::create(['type' => $type, 'prefix' => '', 'next_number' => 1]);
        $n = (int) $seq->next_number;
        $seq->increment('next_number');

        return $n;
    }

    /**
     * A store barcode in EAN-13 format: "2" (in-store range) + business number
     * (2 digits) + 9-digit counter + check digit. Scans on any EAN scanner.
     */
    public function barcode(int $businessId): string
    {
        $body = '2'.str_pad((string) ($businessId % 100), 2, '0', STR_PAD_LEFT).str_pad((string) $this->nextNumber('barcode'), 9, '0', STR_PAD_LEFT);

        return $body.self::ean13Check($body);
    }

    public static function ean13Check(string $twelve): int
    {
        $sum = 0;
        foreach (str_split($twelve) as $i => $d) {
            $sum += (int) $d * ($i % 2 ? 3 : 1);
        }

        return (10 - $sum % 10) % 10;
    }
}
