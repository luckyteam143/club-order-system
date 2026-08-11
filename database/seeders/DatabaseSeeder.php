<?php

namespace Database\Seeders;

use App\Models\Club;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::create([
            'name'     => 'Admin',
            'email'    => 'admin@example.com',
            'password' => Hash::make('password'),
            'role'     => 'admin',
        ]);

        $this->call(WorkbookSeeder::class);

        // One login per seeded club, for testing the club-facing side.
        Club::each(function (Club $club) {
            User::firstOrCreate(
                ['email' => $club->email],
                [
                    'name'     => $club->name.' Manager',
                    'password' => Hash::make('password'),
                    'role'     => 'club',
                    'club_id'  => $club->id,
                ],
            );
        });
    }
}
