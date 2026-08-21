<?php

namespace Database\Seeders;

use App\Models\DocumentReference;
use Illuminate\Database\Seeder;

class DocumentReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $references = [
            DocumentReference::CATEGORY_TRANSPORTATION => [
                'Pesawat',
                'Kapal',
                'Mobil',
            ],
            DocumentReference::CATEGORY_TRAVEL_LEVEL => [
                'A',
                'B',
                'C',
                'D',
                'E',
                'F',
            ],
            DocumentReference::CATEGORY_TRAVEL_TYPE => [
                'Luar Kota',
                'Dalam Kota',
            ],
        ];

        foreach ($references as $category => $values) {
            foreach ($values as $value) {
                DocumentReference::query()->updateOrCreate([
                    'category' => $category,
                    'value' => $value,
                ]);
            }
        }
    }
}
