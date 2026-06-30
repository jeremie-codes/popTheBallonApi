<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Conversation;
use App\Models\MatchModel;
use App\Models\ProfileAction;
use App\Models\User;
use App\Services\ExpoNotificationService;
use Illuminate\Http\Request;

class InteractionController extends Controller
{
    public function like(Request $request, ExpoNotificationService $expo)
    {
        try {
            return $this->storeAction($request, 'like', $expo);
        } catch (\Throwable $e) {
            logger()->error('Like match error ', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur, ' . $e->getMessage()], 500);
        }
    }

    public function pop(Request $request, ExpoNotificationService $expo)
    {
        try {
            return $this->storeAction($request, 'pop', $expo);
        } catch (\Throwable $e) {
            logger()->error('Pop match error ', ['error' => $e->getMessage()]);
            return response()->json(['message' => 'Erreur, ' . $e->getMessage()], 500);
        }
    }

    public function decline(Request $request)
    {
        try {
            $data = $request->validate([
                'profile_id' => ['required', 'exists:users,id'],
            ]);

            $actor = $request->user('sanctum');
            $targetId = (int) $data['profile_id'];

            // On supprime simplement le like reçu
            ProfileAction::query()
                ->where('actor_id', $targetId)
                ->where('target_id', $actor->id)
                ->where('type', 'like')
                ->delete();

            return response()->json([
                'success' => true,
                'message' => 'Demande refusée.',
            ]);
        } catch (\Throwable $e) {
            logger()->error('Decline match error', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'message' => 'Erreur interne.',
            ], 500);
        }
    }

    private function storeAction(Request $request, string $type, ExpoNotificationService $expo)
    {
        try {
            $data = $request->validate([
                'profile_id' => ['required', 'exists:users,id'],
            ]);

            $actor = $request->user('sanctum');
            $targetId = (int) $data['profile_id']; // A remettre pour la prod
            //$targetId = (int) $request->user('sanctum')->id; // A retirer pour la prod

            // Empêche les actions sur soi-même (utile pour les tests, à retirer en prod)
            if ($actor->id === $targetId) {
                return response()->json([
                    'message' => 'Action impossible sur votre propre profil.'
                ], 422);
            }

            $target = User::query()->with('devices')->find($targetId);

            // Enregistre ou met à jour l'action
            ProfileAction::query()->updateOrCreate(
                [
                    'actor_id' => $actor->id,
                    'target_id' => $targetId,
                ],
                [
                    'type' => $type,
                ]
            );

            // Vérifie si la cible a liké l'acteur
            $targetLikedActor = ProfileAction::query()
                ->where('actor_id', $targetId)
                ->where('target_id', $actor->id)
                ->where('type', 'like')
                ->exists();

            // Notification
            if ($type === 'like') {

                if ($targetLikedActor) {

                    AppNotification::query()->create([
                        'user_id' => $targetId,
                        'title' => '❤️ Demande de match confirmé !',
                        'message' => $actor->displayName() . ' a accepté votre demande de match.',
                        'kind' => 'match',
                        'profile_id' => $actor->id,
                    ]);

                    foreach ($target->devices as $device) {
                        $expo->send(
                            $device->expo_token,
                            'PopTheBallon - Nouvelle notification',
                            $actor->displayName() . ' a accepté votre demande de match.',
                            [
                                'type' => 'match',
                                'user_id' => $actor->id,
                            ]
                        );
                    }
                } else {

                    AppNotification::query()->create([
                        'user_id' => $targetId,
                        'title' => 'Nouvelle demande de match',
                        'message' => $actor->displayName() . ' aime votre profil, cliquez pour voir.',
                        'kind' => 'like',
                        'profile_id' => $actor->id,
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
                }
            }


            // Match uniquement si les deux ont liké
            $actorLikedTarget = ProfileAction::query()
                ->where('actor_id', $actor->id)
                ->where('target_id', $targetId)
                ->where('type', 'like')
                ->exists();

            $targetLikedActor = ProfileAction::query()
                ->where('actor_id', $targetId)
                ->where('target_id', $actor->id)
                ->where('type', 'like')
                ->exists();

            $matched = $actorLikedTarget && $targetLikedActor;

            if ($matched) {

                [$one, $two] = collect([
                    $actor->id,
                    $targetId
                ])->sort()->values()->all();

                $match = MatchModel::query()->firstOrCreate(
                    [
                        'user_one_id' => $one,
                        'user_two_id' => $two,
                    ],
                    [
                        'matched_at' => now(),
                    ]
                );

                Conversation::query()->firstOrCreate(
                    [
                        'user_one_id' => $one,
                        'user_two_id' => $two,
                    ],
                    [
                        'match_id' => $match->id,
                    ]
                );
            }

            return response()->json([
                'success' => true,
                'matched' => $matched,
                'message' => $matched
                    ? 'Match confirmé.'
                    : 'Action enregistrée.',
            ]);
        } catch (\Throwable $e) {
            logger()->error('storeAction failed', [
                'type' => $type,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
