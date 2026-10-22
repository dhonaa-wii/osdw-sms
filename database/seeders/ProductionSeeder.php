<?php

namespace Database\Seeders;

use App\Models\Campus;
use App\Models\College;
use Illuminate\Database\Seeder;

/**
 * Seeds only the reference data a live server needs: branding images, the
 * campus and its colleges. No demo accounts or programs — the first
 * administrator is made with `php artisan osdw:create-admin`.
 */
class ProductionSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(BrandingSeeder::class);

        Campus::firstOrCreate(['name' => 'Aparri']);

        $colleges = [
            'CBEA' => 'College of Business, Entrepreneurship, and Accountancy',
            'CCJE' => 'College of Criminal Justice Education',
            'CFAS' => 'College of Fisheries and Aquatic Sciences',
            'CHM' => 'College of Hospitality Management',
            'CIT' => 'College of Industrial Technology',
            'CICS' => 'College of Information and Computing Sciences',
            'CTE' => 'College of Teacher Education',
        ];

        foreach ($colleges as $code => $name) {
            College::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
