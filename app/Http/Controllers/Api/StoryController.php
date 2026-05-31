<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\StoryMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    public function index()
    {
        return response()->json(
            Story::query()
                ->with(['user.photos', 'media'])
                ->where(function ($query) {
                    $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
                })
                ->latest()
                ->get()
                ->map(fn (Story $story) => $this->storyPayload($story))
        );
    }

    public function mine(Request $request)
    {
        return response()->json(
            Story::query()
                ->with(['user.photos', 'media'])
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get()
                ->map(fn (Story $story) => $this->storyPayload($story, true))
        );
    }

    public function store(Request $request)
    {
        try {
            $data = $request->validate(['story' => ['required', 'image', 'max:5120']]);
            $path = $data['story']->store('stories', 'public');
            $story = Story::query()->create([
                'user_id' => $request->user()->id,
                'expires_at' => now()->addDay(),
            ]);
            StoryMedia::query()->create([
                'story_id' => $story->id,
                'path' => $path,
                'url' => Storage::disk('public')->url($path),
            ]);

            return response()->json($this->storyPayload($story->load(['user.photos', 'media']), true), 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }

    private function storyPayload(Story $story, bool $isMine = false): array
    {
        $avatar = optional($story->user->photos->first())->path ?? null;

        return [
            'id' => (string) $story->id,
            'name' => $isMine ? 'Ta story' : $story->user->displayName(),
            'avatar' => $avatar ?? optional($story->media->first())->path ?? '',
            'profileId' => (string) $story->user_id,
            'images' => $story->media->map(fn (StoryMedia $media) => ['name' => $media->path])->values(),
            'isMine' => $isMine,
        ];
    }
}
