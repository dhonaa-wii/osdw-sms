<?php

namespace Database\Seeders;

use App\Enums\BrandingImageKey;
use App\Models\BrandingImage;
use Illuminate\Database\Seeder;

/**
 * Loads the OSDW branding images into the database. The source files in
 * database/seeders/branding are only the seed copies — the system serves the
 * images from the `branding_images` table.
 */
class BrandingSeeder extends Seeder
{
    public function run(): void
    {
        $images = [
            BrandingImageKey::Logo->value => 'osdw-logo.jpg',
            BrandingImageKey::FooterBanner->value => 'osdw-footer.png',
        ];

        foreach ($images as $key => $fileName) {
            $path = database_path('seeders/branding/'.$fileName);

            BrandingImage::updateOrCreate(['key' => $key], [
                'file_name' => $fileName,
                'mime_type' => mime_content_type($path),
                'data' => file_get_contents($path),
            ]);
        }
    }
}
