<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\MessageBundle;
use App\Models\MessageBundleRequest;
use App\Models\User;
use Illuminate\Http\Request;
use App\Services\ExpoNotificationService;
use App\Services\FlexpaieService;

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

    public function requestBundle(Request $request, ExpoNotificationService $expo)
    {
        try {
            $data = $request->validate([
                'requester_id' => ['nullable', 'exists:users,id'],
                'requested_user_id' => ['required', 'exists:users,id'],
            ]);

            $requester = $request->user();
            $requested = User::query()->findOrFail($data['requested_user_id']);

            $actor = $request->user('sanctum');
            $targetId = (int) $data['requested_user_id'];
            $target = User::query()->with('devices')->find($targetId);

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

            foreach ($target->devices as $device) {
                $expo->send(
                    $device->expo_token,
                    'PopTheBallon - Nouvelle notification',
                    $actor->displayName() . ' aime votre profil, cliquez pour voir.',
                    [
                        'type' => 'like',
                        'user_id' => $actor->id,
                    ]
                );

            }

            return response()->json(['success' => true, 'message' => 'Demande de forfait envoyee.'], 201);
        } catch (\Throwable $e) {
            logger()->error('MessageBundleController.requestBundle error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }

    public function purchaseBundle(MessageBundle $MessageBundle, Request $request, FlexpaieService $fxp)
    {

         try {
            $data = $request->validate([
                'profile_id' => ['nullable', 'exists:users,id'],
            ]);

            $user = $request->user('sanctum');

            if (!$MessageBundle) {
                return response()->json(['message' => 'Forfait introuvable'], 404);
            }

            //$fxp->purchase($user, $MessageBundle->price, $MessageBundle->currency);
            
            // Logique d'achat du forfait pour l'utilisateur ciblé (à implémenter selon votre logique métier)
            // si profile_id est fourni, c'est une demande d'achat pour un autre utilisateur, sinon c'est pour soi-même

            AppNotification::query()->create([
                'user_id' => $data['profile_id'] ?? $user->id,
                'title' => 'Demande de forfait',
                'message' => $data['profile_id'] ? $user->displayName().' vous a acheté un forfait messages '. $MessageBundle->title .' pour discuter avec lui.'
                    : 'Vous avez acheté un forfait messages '. $MessageBundle->title .' pour discuter avec vos matchs.',
                'kind' => 'bundle_purchase',
                'profile_id' => $user->id,
            ]);

            return response()->json(['success' => true, 'message' => 'Demande de forfait envoyee.'], 201);
        } catch (\Throwable $e) {
            logger()->error('MessageBundleController.purchaseBundle error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }


    private function currencySymbol(string $currency): string
    {
        return strtoupper($currency) === 'USD' ? '$' : $currency;
    }
}
