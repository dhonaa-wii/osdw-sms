<?php

namespace Database\Seeders;

use App\Enums\AllocationMode;
use App\Enums\CollegeScope;
use App\Enums\FundingSource;
use App\Enums\ScholarshipCategory;
use App\Enums\Semester;
use App\Enums\UserRole;
use App\Models\AcademicTerm;
use App\Models\Campus;
use App\Models\College;
use App\Models\Provider;
use App\Models\ScholarshipProgram;
use App\Models\User;
use App\Services\OfferingService;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory()->create([
            'name' => 'System Administrator',
            'email' => 'admin@osdw.test',
            'role' => UserRole::Admin,
        ]);

        User::factory()->create([
            'name' => 'OSDW Staff',
            'email' => 'staff@osdw.test',
            'role' => UserRole::OsdwStaff,
        ]);

        $campus = Campus::create(['name' => 'Aparri']);

        $colleges = collect([
            ['code' => 'CBEA', 'name' => 'College of Business, Entrepreneurship, and Accountancy'],
            ['code' => 'CCJE', 'name' => 'College of Criminal Justice Education'],
            ['code' => 'CFAS', 'name' => 'College of Fisheries and Aquatic Sciences'],
            ['code' => 'CHM', 'name' => 'College of Hospitality Management'],
            ['code' => 'CIT', 'name' => 'College of Industrial Technology'],
            ['code' => 'CICS', 'name' => 'College of Information and Computing Sciences'],
            ['code' => 'CTE', 'name' => 'College of Teacher Education'],
        ])->map(fn ($attributes) => College::create($attributes));

        $collegesByCode = $colleges->keyBy('code');

        $providers = collect([
            ['name' => 'DOST-SEI', 'funding_source' => FundingSource::NationalGovernment, 'contact_person' => 'Engr. Liza Ramos', 'contact_email' => 'sei@dost.gov.ph'],
            ['name' => 'ACEF-GIAHEP', 'funding_source' => FundingSource::LocalNonGovernment],
            ['name' => 'CSUA Alumni Association', 'funding_source' => FundingSource::InHouse, 'contact_person' => 'Mr. Ronaldo Cruz'],
            ['name' => 'Open Heart Foundation', 'funding_source' => FundingSource::LocalNonGovernment],
        ])->map(fn ($attributes) => Provider::create($attributes));

        $providersByName = $providers->keyBy('name');

        AcademicTerm::create([
            'school_year' => '2025-2026',
            'semester' => Semester::Second,
            'start_date' => '2026-01-12',
            'end_date' => '2026-05-29',
        ]);

        $currentTerm = AcademicTerm::create([
            'school_year' => '2026-2027',
            'semester' => Semester::First,
            'start_date' => '2026-08-03',
            'end_date' => '2026-12-18',
            'is_current' => true,
        ]);

        $scholarshipPrograms = collect([
            'DOST-SEI-UAF' => [
                'name' => 'DOST-SEI Undergraduate Academic Fellowship',
                'provider' => 'DOST-SEI',
                'category' => ScholarshipCategory::Academic,
                'description' => 'Full academic scholarship for priority STEM courses.',
            ],
            'ACEF-GIAHEP' => [
                'name' => 'ACEF-GIAHEP Grant-in-Aid',
                'provider' => 'ACEF-GIAHEP',
                'category' => ScholarshipCategory::Academic,
                'description' => 'Partial grant for incoming freshmen with high entrance exam scores.',
            ],
            'CSUA-ALUMNI' => [
                'name' => 'CSUA Alumni Association Scholarship',
                'provider' => 'CSUA Alumni Association',
                'category' => ScholarshipCategory::Academic,
                'description' => 'Alumni-funded grant prioritizing continuing scholars with strong academic standing.',
            ],
            'OHF-CARES' => [
                'name' => 'Open Heart Foundation CARES Grant',
                'provider' => 'Open Heart Foundation',
                'category' => ScholarshipCategory::NonAcademic,
                'description' => 'Need-based assistance for students with documented financial hardship.',
                'is_active' => false,
            ],
        ])->map(fn ($attributes) => ScholarshipProgram::create([
            'name' => $attributes['name'],
            'provider_id' => $providersByName[$attributes['provider']]->id,
            'category' => $attributes['category'],
            'description' => $attributes['description'],
            'is_active' => $attributes['is_active'] ?? true,
        ]));

        $scholarshipProgramsByKey = $scholarshipPrograms;

        // The program template. Every offering opened below starts from a copy
        // of this, and can then be edited for its own term.
        $dostProgram = $scholarshipProgramsByKey['DOST-SEI-UAF'];

        $dostProgram->formFields()->createMany([
            [
                'field_type' => 'file',
                'label' => 'Certificate of Registration',
                'is_required' => true,
                'accepted_formats' => 'pdf,jpg,png',
                'max_size_mb' => 5,
                'sort_order' => 1,
            ],
            [
                'field_type' => 'file',
                'label' => 'Barangay Certificate of Indigency',
                'is_required' => false,
                'accepted_formats' => 'pdf,jpg,png',
                'max_size_mb' => 5,
                'sort_order' => 2,
            ],
        ]);

        $dostProgram->eligibilityRules()->create([
            'field' => 'gwa',
            'operator' => 'gte',
            'value' => '80.00',
            'message' => 'GWA must be 80.00 or better.',
        ]);

        $csuaProgram = $scholarshipProgramsByKey['CSUA-ALUMNI'];

        $offeringService = app(OfferingService::class);

        $offeringService->openOffering($dostProgram, [
            'academic_term_id' => $currentTerm->id,
            'name' => 'Batch 1',
            'campus_id' => $campus->id,
            'opens_at' => '2026-08-10 08:00:00',
            'closes_at' => '2026-09-10 17:00:00',
            'total_slots' => 20,
            'amount' => 15000,
        ]);

        $csuaOffering = $offeringService->openOffering($csuaProgram, [
            'academic_term_id' => $currentTerm->id,
            'name' => 'Batch 1',
            'campus_id' => $campus->id,
            'opens_at' => '2026-08-15 08:00:00',
            'closes_at' => '2026-09-15 17:00:00',
            'total_slots' => 15,
            'discount_rate' => 50,
            'college_scope' => CollegeScope::Restricted,
            'allocation_mode' => AllocationMode::Quota,
        ]);

        // The offering names its own eligible colleges and this term's split
        // between them.
        $csuaOffering->offeringColleges()->createMany([
            ['college_id' => $collegesByCode['CICS']->id, 'slots' => 8],
            ['college_id' => $collegesByCode['CTE']->id, 'slots' => 7],
        ]);
    }
}
