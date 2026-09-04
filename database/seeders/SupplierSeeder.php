<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    public function run(): void
    {
        Supplier::query()->updateOrCreate(
            ['code' => 'supplier-a'],
            ['name' => 'Supplier A'],
        );

        Supplier::query()->updateOrCreate(
            ['code' => 'supplier-b'],
            ['name' => 'Supplier B'],
        );
    }
}
