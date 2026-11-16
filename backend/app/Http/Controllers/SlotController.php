<?php

namespace App\Http\Controllers;

use App\Models\Slot;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;

class SlotController extends Controller
{
    public function index(): JsonResponse
    {
        $timezone = config('app.timezone');
        $now = CarbonImmutable::now($timezone);
        $slots = Slot::with('booking:id,slot_id')->orderBy('starts_at')->get();

        return response()->json([
            'data' => $slots->map(function (Slot $slot) use ($timezone, $now) {
                return [
                    'id' => $slot->id,
                    'starts_at' => CarbonImmutable::createFromTimestampUTC($slot->starts_at)->setTimezone($timezone)->toIso8601String(),
                    'ends_at' => CarbonImmutable::createFromTimestampUTC($slot->ends_at)->setTimezone($timezone)->toIso8601String(),
                    'status' => $slot->starts_at <= $now->timestamp ? 'past' : ($slot->booking ? 'booked' : 'available'),
                    'booking_id' => $slot->booking?->id,
                ];
            }),
            'timezone' => $timezone,
            'server_now' => $now->toIso8601String(),
        ]);
    }
}
