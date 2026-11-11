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
}
