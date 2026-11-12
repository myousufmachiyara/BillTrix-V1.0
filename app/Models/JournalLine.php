<?php

namespace App\Models;

use App\Models\Concerns\BelongsToBusiness;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalLine extends Model
{
    use BelongsToBusiness;

    public $timestamps = false;

    protected $fillable = ['journal_entry_id', 'business_id', 'account_id', 'party_id', 'branch_id', 'date', 'debit', 'credit', 'memo'];

    protected function casts(): array
    {
        return ['date' => 'date', 'debit' => 'decimal:2', 'credit' => 'decimal:2'];
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
