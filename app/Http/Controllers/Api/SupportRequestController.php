<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SupportRequest;
use Illuminate\Http\Request;

class SupportRequestController extends Controller
{
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'type' => ['required', 'in:help,complaint,review'],
                'subject' => ['required', 'string', 'max:255'],
                'message' => ['required', 'string'],
                'rating' => ['nullable', 'integer', 'min:1', 'max:5'],
            ]);

            SupportRequest::query()->create([
                'user_id' => $request->user()?->id,
                ...$data,
            ]);

            return response()->json(['success' => true, 'message' => 'Message envoye.'], 201);
        } catch (\Throwable $e) {
            logger()->error('storeAction failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }
}
