<?php

use App\Http\Controllers\BookingController;
use App\Http\Controllers\SlotController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/health', function () {
    DB::select('select 1');
    return response()->json(['status' => 'ok', 'database' => DB::connection()->getDriverName(), 'timezone' => config('app.timezone')]);
});
Route::get('/slots', [SlotController::class, 'index']);
Route::post('/bookings', [BookingController::class, 'store']);
Route::delete('/bookings/{id}', [BookingController::class, 'destroy'])->whereNumber('id');
