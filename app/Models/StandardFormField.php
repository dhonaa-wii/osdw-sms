<?php

namespace App\Models;

use App\Models\Base\FormField;

/**
 * A field of the standard application form. Copied into each offering that
 * uses the standard requirements; never answered directly.
 */
class StandardFormField extends FormField
{
    protected $fillable = self::SHARED_FILLABLE;
}
