<?php

namespace App\Models;

use App\Enums\ApplicationStatus;
use App\Services\StudentNotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ApplicationStatusLog extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'application_id',
        'from_status',
        'to_status',
        'changed_by',
        'reason',
    ];

    /**
     * Every status change goes through a log row, so this is the one place
     * the student hears about it.
     */
    protected static function booted(): void
    {
        static::created(fn (self $log) => app(StudentNotificationService::class)->statusChanged($log));
    }

    protected function casts(): array
    {
        return [
            'from_status' => ApplicationStatus::class,
            'to_status' => ApplicationStatus::class,
        ];
    }

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
