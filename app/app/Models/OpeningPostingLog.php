<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpeningPostingLog extends TenantModel
{
    protected $fillable = [
        'opening_session_id',
        'tenant_id',
        'journal_entry_id',
        'stock_ledger_count',
        'inventory_layer_count',
        'payload',
        'posted_at',
        'created_by',
    ];

    protected $casts = [
        'payload' => 'array',
        'posted_at' => 'datetime',
    ];

    public function session(): BelongsTo
    {
        return $this->belongsTo(OpeningSession::class, 'opening_session_id');
    }

    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'journal_entry_id');
    }
}
