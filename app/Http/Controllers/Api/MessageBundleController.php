<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\MessageBundle;
use App\Models\MessageBundleRequest;
use App\Models\User;
use Illuminate\Http\Request;

class MessageBundleController extends Controller
{
    public function index()
    {
        return response()->json(
            MessageBundle::query()
                ->where('active', true)
                ->orderBy('price')
                ->get()
                ->map(fn (MessageBundle $bundle) => [
                    'id' => (string) $bundle->id,
                    'title' => $bundle->title,
                    'messages' => $bundle->messages,
                    'price' => rtrim(rtrim((string) $bundle->price, '0'), '.').' '.$this->currencySymbol($bundle->currency),
                    'popular' => (bool) $bundle->popular,
                    'description' => $bundle->description ?? '',
                ])
        );
    }

    public function requestBundle(Request $request)
    {
        try {
            $data = $request->validate([
                'requester_id' => ['nullable', 'exists:users,id'],
                'requested_user_id' => ['required', 'exists:users,id'],
            ]);

            $requester = $request->user();
            $requested = User::query()->findOrFail($data['requested_user_id']);

            MessageBundleRequest::query()->create([
                'requester_id' => $data['requester_id'] ?? $requester->id,
                'requested_user_id' => $requested->id,
            ]);

            AppNotification::query()->create([
                'user_id' => $requested->id,
                'title' => 'Demande de forfait',
                'message' => $requester->displayName().' vous demande de lui acheter un forfait messages pour discuter.',
                'kind' => 'bundle_request',
                'profile_id' => $requester->id,
            ]);

            return response()->json(['success' => true, 'message' => 'Demande de forfait envoyee.'], 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }

    private function currencySymbol(string $currency): string
    {
        return strtoupper($currency) === 'USD' ? '$' : $currency;
    }
}
