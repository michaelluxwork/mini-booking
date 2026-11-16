<?php

namespace Database\Seeders;

use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SlotSeeder extends Seeder
{
    public function run(): void
    {
        $today = CarbonImmutable::now(config('app.timezone'))->startOfDay();
        $rows = [];
        for ($day = 0; $day < 7; $day++) {
            for ($hour = 9; $hour < 18; $hour++) {
                $start = $today->addDays($day)->setTime($hour, 0);
                $rows[] = [
                    'starts_at' => $start->timestamp,
                    'ends_at' => $start->addHour()->timestamp,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
        // Only missing slots are inserted; bookings and older slots are untouched.
        DB::table('slots')->insertOrIgnore($rows);
    }
}
