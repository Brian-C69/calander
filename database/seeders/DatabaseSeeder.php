<?php

namespace Database\Seeders;

use App\Models\Calendar;
use App\Models\CalendarMember;
use App\Models\Household;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $household = Household::factory()->create([
            'name' => 'Family',
        ]);

        $admin = User::factory()->create([
            'name' => 'Family Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'household_id' => $household->id,
            'role' => 'admin',
            'avatar_color' => '#14b8a6',
        ]);

        $calendar = Calendar::factory()->create([
            'household_id' => $household->id,
            'name' => 'Family Calendar',
            'color' => '#14b8a6',
            'visibility_scope' => 'household',
            'owner_id' => $admin->id,
            'is_default' => true,
        ]);

        CalendarMember::factory()->create([
            'calendar_id' => $calendar->id,
            'user_id' => $admin->id,
            'role' => 'owner',
        ]);
    }
}
