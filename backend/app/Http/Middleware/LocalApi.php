<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class LocalApi
{
    public function handle(Request $request, Closure $next)
    {
        if (! in_array($request->getHost(), ['127.0.0.1', 'localhost'], true)) {
            return response()->json(['message' => 'Этот адрес сервера не разрешён.'], 403);
        }
        $origin = $request->header('Origin');
        if ($origin !== null && ! in_array($origin, config('cors.allowed_origins'), true)) {
            return response()->json(['message' => 'Этот источник запроса не разрешён.'], 403);
        }
        if (strlen($request->getContent()) > 8192) {
            return response()->json(['message' => 'Слишком большой запрос.'], 413);
        }
        return $next($request);
    }
}
