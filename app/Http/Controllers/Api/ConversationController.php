<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Conversation;
use App\Models\MatchModel;
use App\Models\Message;
use App\Models\ProfilePhoto;
use App\Models\User;
use Illuminate\Http\Request;

class ConversationController extends Controller
{
    public function matches(Request $request)
    {
        $user = $request->user();
        $matches = MatchModel::query()
            ->with(['userOne.photos', 'userOne.interests', 'userTwo.photos', 'userTwo.interests'])
            ->where('user_one_id', $user->id)
            ->orWhere('user_two_id', $user->id)
            ->latest('matched_at')
            ->get()
            ->map(function (MatchModel $match) use ($user) {
                $profile = $match->user_one_id === $user->id ? $match->userTwo : $match->userOne;
                $conversation = Conversation::query()
                    ->where(function ($query) use ($user, $profile) {
                        $query
                            ->where('user_one_id', $user->id)
                            ->where('user_two_id', $profile->id);
                    })
                    ->orWhere(function ($query) use ($user, $profile) {
                        $query
                            ->where('user_one_id', $profile->id)
                            ->where('user_two_id', $user->id);
                    })
                    ->first();

                return $this->profilePayload($profile, $conversation);
            });

        return response()->json($matches);
    }

    public function index(Request $request)
    {
        $user = $request->user();

        return response()->json(
            Conversation::query()
                ->with(['userOne.photos', 'userTwo.photos', 'messages' => fn ($query) => $query->latest()->limit(1)])
                ->where('user_one_id', $user->id)
                ->orWhere('user_two_id', $user->id)
                ->latest('last_message_at')
                ->get()
                ->map(fn (Conversation $conversation) => $this->conversationPayload($conversation, $user))
        );
    }

    public function show(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        if (! in_array($user->id, [$conversation->user_one_id, $conversation->user_two_id], true)) {
            return response()->json(['message' => 'Conversation introuvable.'], 404);
        }

        $conversation->load(['userOne.photos', 'userTwo.photos', 'messages' => fn ($query) => $query->oldest()]);

        return response()->json($this->conversationPayload($conversation, $user, true));
    }

    public function storeMessage(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        if (! in_array($user->id, [$conversation->user_one_id, $conversation->user_two_id], true)) {
            return response()->json(['message' => 'Conversation introuvable.'], 404);
        }

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $message = $conversation->messages()->create([
            'sender_id' => $user->id,
            'body' => $data['body'],
        ]);

        $conversation->forceFill(['last_message_at' => now()])->save();

        return response()->json([
            'id' => (string) $message->id,
            'text' => $message->body,
            'time' => $message->created_at->format('H:i'),
            'mine' => true,
        ], 201);
    }

    public function markAsRead(Request $request, Conversation $conversation)
    {
        $user = $request->user();

        if (! in_array($user->id, [$conversation->user_one_id, $conversation->user_two_id], true)) {
            return response()->json(['message' => 'Conversation introuvable.'], 404);
        }

        $conversation->messages()
            ->where('sender_id', '<>', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['success' => true]);
    }

    private function conversationPayload(Conversation $conversation, User $viewer, bool $withMessages = false): array
    {
        $other = $conversation->user_one_id === $viewer->id ? $conversation->userTwo : $conversation->userOne;
        $last = $conversation->messages->sortByDesc('created_at')->first();
        $unread = $conversation->messages
            ->filter(fn (Message $message) => $message->sender_id !== $viewer->id && $message->read_at === null)
            ->count();

        $payload = [
            'id' => (string) $conversation->id,
            'profileId' => (string) $other->id,
            'name' => $other->displayName(),
            'avatar' => optional($other->photos->first())->path ?? '',
            'message' => $last?->body ?? '',
            'time' => optional($last?->created_at ?? $conversation->created_at)->diffForHumans(),
            'unread' => $unread,
            'matched' => true,
        ];

        if ($withMessages) {
            $payload['messages'] = $conversation->messages
                ->map(fn (Message $message) => [
                    'id' => (string) $message->id,
                    'text' => $message->body,
                    'time' => $message->created_at->format('H:i'),
                    'mine' => $message->sender_id === $viewer->id,
                ])
                ->values();
        }

        return $payload;
    }

    private function profilePayload(User $profile, ?Conversation $conversation = null): array
    {
        return [
            'id' => (string) $profile->id,
            'conversationId' => $conversation ? (string) $conversation->id : null,
            'name' => $profile->displayName(),
            'age' => $profile->age() ?? 18,
            'city' => $profile->city ?? '',
            'country' => $profile->country ?? '',
            'bio' => $profile->bio ?? '',
            'intention' => $profile->intention ?? '',
            'verified' => (bool) $profile->verified,
            'distance' => '0 km',
            'pictures' => $profile->photos->map(fn (ProfilePhoto $photo) => ['name' => $photo->path])->values(),
            'avatar' => optional($profile->photos->first())->path ?? null,
            'interests' => $profile->interests->pluck('name')->values(),
        ];
    }
}
