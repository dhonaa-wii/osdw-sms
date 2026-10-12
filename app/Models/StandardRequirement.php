<?php

namespace App\Models;

use App\Enums\SubmissionMode;
use Illuminate\Database\Eloquent\Model;

/**
 * A standard disbursement requirement — copied as an award-stage additional
 * requirement into each offering that uses the standard requirements.
 */
class StandardRequirement extends Model
{
    protected $fillable = [
        'label',
        'instructions',
        'submission_mode',
        'accepted_formats',
        'max_size_mb',
        'template_path',
        'template_name',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'submission_mode' => SubmissionMode::class,
            'max_size_mb' => 'integer',
        ];
    }
}
