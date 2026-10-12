<?php

namespace App\Models;

use App\Enums\BrandingImageKey;
use Illuminate\Database\Eloquent\Model;

class BrandingImage extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'key',
        'file_name',
        'mime_type',
        'data',
    ];

    // The raw image bytes never ride along when the model is serialized.
    protected $hidden = ['data'];

    protected function casts(): array
    {
        return [
            'key' => BrandingImageKey::class,
        ];
    }

    /**
     * The URL that serves this image from the database, or null when it has
     * not been stored yet. The timestamp busts the browser cache on replace.
     */
    public static function url(BrandingImageKey $key): ?string
    {
        $updatedAt = static::whereKey($key->value)->value('updated_at');

        if ($updatedAt === null) {
            return null;
        }

        return route('branding.show', ['key' => $key->value, 'v' => strtotime($updatedAt)]);
    }
}
