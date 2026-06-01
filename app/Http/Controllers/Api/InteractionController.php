<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Models\Conversation;
use App\Models\MatchModel;
use App\Models\ProfileAction;
use Illuminate\Http\Request;

class InteractionController extends Controller
{
    public function like(Request $request)
    {
        try {
            return $this->storeAction($request, 'like');
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }

    public function pop(Request $request)
    {
        try {
            return $this->storeAction($request, 'pop');
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }

    private function storeAction(Request $request, string $type)
    {
        $data = $request->validate(['profile_id' => ['required', 'exists:users,id']]);
        $actor = $request->user();
        $targetId = (int) $data['profile_id'];

        if ($actor->id === $targetId) {
            return response()->json(['message' => 'Action impossible sur votre propre profil.'], 422);
        }

        ProfileAction::query()->firstOrCreate([
            'actor_id' => $actor->id,
            'target_id' => $targetId,
            'type' => $type,
        ]);

        // Vérifier si la cible a déjà liké/popé l'acteur (demande de match antérieure)
        $targetHasLikedActor = ProfileAction::query()
            ->where('actor_id', $targetId)
            ->where('target_id', $actor->id)
            ->whereIn('type', ['like', 'pop'])
            ->exists();

        if ($type === 'like') {
            if ($targetHasLikedActor) {
                // C'est une acceptation d'une demande de match antérieure
                AppNotification::query()->create([
                    'user_id' => $targetId,
                    'title' => 'Match accepté',
                    'message' => $actor->displayName().' a accepté votre match.',
                    'kind' => 'match',
                    'profile_id' => $actor->id,
                ]);
            } else {
                // C'est une nouvelle demande de match
                AppNotification::query()->create([
                    'user_id' => $targetId,
                    'title' => 'Nouvelle demande de match',
                    'message' => $actor->displayName().' a interagi avec ton profil.',
                    'kind' => 'like',
                    'profile_id' => $actor->id,
                ]);
            }
        }

        $matched = ProfileAction::query()
            ->where('actor_id', $targetId)
            ->where('target_id', $actor->id)
            ->whereIn('type', ['like', 'pop'])
            ->exists();

        if ($matched) {
            [$one, $two] = collect([$actor->id, $targetId])->sort()->values()->all();
            $match = MatchModel::query()->firstOrCreate([
                'user_one_id' => $one,
                'user_two_id' => $two,
            ], ['matched_at' => now()]);

            Conversation::query()->firstOrCreate([
                'user_one_id' => $one,
                'user_two_id' => $two,
            ], ['match_id' => $match->id]);
        }

        return response()->json([
            'success' => true,
            'matched' => $matched,
            'message' => $matched ? ($targetHasLikedActor && $type === 'like' ? 'Match accepté.' : 'Match confirme.') : 'Action enregistree.',
        ]);
    }
}
