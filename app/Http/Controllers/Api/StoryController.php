<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Story;
use App\Models\StoryMedia;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class StoryController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            $this->activeStories()
                ->groupBy('user_id')
                ->map(fn (Collection $stories) => $this->storyPayload(
                    $stories,
                    $request->user('sanctum')?->id === $stories->first()->user_id,
                ))
                ->values()
        );
    }

    public function mine(Request $request)
    {
        return response()->json(
            $this->activeStories()
                ->where('user_id', $request->user()->id)
                ->groupBy('user_id')
                ->map(fn (Collection $stories) => $this->storyPayload($stories, true))
                ->values()
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
                'path' => 'storage/'.$path,
                'url' => Storage::disk('public')->url($path),
            ]);

            return response()->json(
                $this->storyPayload(collect([$story->load(['user.photos', 'media'])]), true),
                201,
            );
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }

    public function destroy(Request $request, StoryMedia $storyMedia)
    {
        if ($storyMedia->story->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Action non autorisee.'], 403);
        }

        try {
            if ($storyMedia->path) {
                $storagePath = preg_replace('#^storage/#', '', $storyMedia->path);
                Storage::disk('public')->delete($storagePath);
            }

            $story = $storyMedia->story;
            $storyMedia->delete();

            if ($story->media()->count() === 0) {
                $story->delete();
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Impossible de supprimer le media de story.', 'error' => $e->getMessage()], 500);
        }
    }

    private function activeStories(): Collection
    {
        return Story::query()
            ->with(['user.photos', 'media'])
            ->where(function ($query) {
                $query->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->latest()
            ->get();
    }

    private function storyPayload(Collection $stories, bool $isMine = false): array
    {
        /** @var Story $latestStory */
        $latestStory = $stories->first();
        $media = $stories->flatMap(fn (Story $story) => $story->media);
        $avatar = optional($latestStory->user->photos->first())->path ?? null;

        return [
            'id' => (string) $latestStory->user_id,
            'name' => $isMine ? 'Ta story' : $latestStory->user->displayName(),
            'avatar' => $avatar ?? optional($media->first())->path ?? '',
            'profileId' => (string) $latestStory->user_id,
            'images' => $media->map(fn (StoryMedia $storyMedia) => ['id' => (string) $storyMedia->id, 'name' => $storyMedia->path])->values(),
            'isMine' => $isMine,
        ];
    }
}
