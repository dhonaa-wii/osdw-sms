<?php

namespace App\Models;

use App\Enums\EmailStatus;
use App\Enums\EmailType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    protected $fillable = [
        'application_id',
        'recipient',
        'subject',
        'type',
        'status',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => EmailType::class,
            'status' => EmailStatus::class,
            'sent_at' => 'datetime',
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }
}
