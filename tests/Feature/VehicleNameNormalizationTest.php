<?php

namespace Tests\Feature;

use App\Models\PilotCarJob;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VehicleNameNormalizationTest extends TestCase
{
    public static function nameProvider(): array
    {
        return [
            // digit padding (TASK-206)
            'bare number'        => ['Car 6', 'Car 006'],
            'two digits'         => ['Car 06', 'Car 006'],
            'three digits'       => ['Car 006', 'Car 006'],
            'ten'                => ['Car 10', 'Car 010'],
            // case normalization (TASK-328)
            'all caps'           => ['CAR 6', 'Car 006'],
            'lower case'         => ['car 06', 'Car 006'],
            'mixed with padding' => ['cAr 6', 'Car 006'],
            // no number: trimmed, returned as-is
            'no number'          => ['Lead Truck', 'Lead Truck'],
            'empty'              => ['', ''],
        ];
    }

    #[DataProvider('nameProvider')]
    public function test_vehicle_name_is_normalized(string $input, string $expected): void
    {
        $this->assertSame($expected, PilotCarJob::normalizeVehicleName($input));
    }

    public function test_mixed_case_variants_all_collapse_to_one_name(): void
    {
        $normalized = array_map(
            fn ($n) => PilotCarJob::normalizeVehicleName($n),
            ['Car 6', 'CAR 6', 'car 06', 'Car 006']
        );

        $this->assertCount(1, array_unique($normalized),
            'All casing/padding variants must normalize to a single vehicle name.');
    }
}
