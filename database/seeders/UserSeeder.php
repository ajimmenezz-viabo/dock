<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Viabo',
            'email' => 'viabo@viabo.com',
            'password' => Hash::make('l8On@Ims^zWJ'),
            'profile' => 'admin_account'
        ]);
    }
}
