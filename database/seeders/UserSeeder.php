<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::create([
            'name' => 'Kemitraan SRN',
            'email' => 'kemitraan.srn@gmail.com',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        User::create([
            'name' => 'Hilmi',
            'email' => 'hilmi.sinergi@gmail.com',
            'password' => 'password',
            'role' => 'admin',
            'status' => 'aktif',
        ]);

        User::create([
            'name' => 'Dita',
            'email' => 'dita@srn.local',
            'password' => 'password',
            'role' => 'kae',
            'kae_code' => 'B',
            'status' => 'aktif',
        ]);

        User::create([
            'name' => 'Nindya',
            'email' => 'nindya@srn.local',
            'password' => 'password',
            'role' => 'kae',
            'kae_code' => 'C',
            'status' => 'aktif',
        ]);
    }
}
