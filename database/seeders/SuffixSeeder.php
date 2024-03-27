<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Address\Suffix;

class SuffixSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Suffix::create(['Name' => 'Avenida', 'Suffix' => 'avenue']);
        Suffix::create(['Name' => 'Boulevard', 'Suffix' => 'boulevard']);
        Suffix::create(['Name' => 'Calle', 'Suffix' => 'street']);
        Suffix::create(['Name' => 'Camino', 'Suffix' => 'road']);
        Suffix::create(['Name' => 'Carretara', 'Suffix' => 'highway']);
        Suffix::create(['Name' => 'Cerrada', 'Suffix' => 'closed']);
        Suffix::create(['Name' => 'Circulo', 'Suffix' => 'circle']);
        Suffix::create(['Name' => 'Entrada', 'Suffix' => 'entrance']);
        Suffix::create(['Name' => 'Paseo', 'Suffix' => 'path']);
        Suffix::create(['Name' => 'Rancho', 'Suffix' => 'ranch']);
        Suffix::create(['Name' => 'Vereda', 'Suffix' => 'small path']);
        Suffix::create(['Name' => 'Villa', 'Suffix' => 'village']);
        Suffix::create(['Name' => 'Vista', 'Suffix' => 'view']);
        Suffix::create(['Name' => 'Zona', 'Suffix' => 'zone']);
    }
}
