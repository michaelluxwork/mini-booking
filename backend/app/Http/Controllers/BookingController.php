<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Slot;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookingController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        if (! $request->isJson()) {
            return response()->json(['message' => 'Отправьте данные в формате JSON.'], 415);
        }
        $data = $request->validate([
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
            'customer_name' => ['required', 'string', 'max:100'],
            'customer_email' => ['required', 'string', 'email:rfc', 'max:254'],
        ], [
            'slot_id.required' => 'Выберите время.',
            'slot_id.integer' => 'Некорректный слот.',
            'slot_id.exists' => 'Такого слота нет.',
            'customer_name.required' => 'Укажите имя.',
            'customer_name.string' => 'Имя должно быть строкой.',
            'customer_name.max' => 'Имя — не более 100 символов.',
            'customer_email.required' => 'Укажите email.',
            'customer_email.email' => 'Проверьте адрес email.',
            'customer_email.string' => 'Email должен быть строкой.',
            'customer_email.max' => 'Email — не более 254 символов.',
        ]);

        try {
            $booking = DB::transaction(function () use ($data) {
                // SQLite IMMEDIATE acquires the writer lock before this time check.
                $slot = Slot::find($data['slot_id']);
                if (! $slot) {
                    throw ValidationException::withMessages(['slot_id' => ['Такого слота нет.']]);
                }
                abort_if($slot->starts_at <= now()->timestamp, 409, 'Это время уже прошло. Выберите другой слот.');

                return Booking::create($data);
            });
        } catch (UniqueConstraintViolationException) {
            return response()->json(['message' => 'Этот слот уже занят. Выберите другое время.'], 409);
        }

        return response()->json(['id' => $booking->id], 201);
    }

    public function destroy(string $id): Response
    {
        // Cancellation is intentionally public and idempotent in this demo.
        Booking::whereKey($id)->delete();

        return response()->noContent();
    }
}
