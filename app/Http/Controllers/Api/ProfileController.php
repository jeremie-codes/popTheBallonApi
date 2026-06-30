<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchModel;
use App\Models\ProfileAction;
use App\Models\ProfilePhoto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function discover(Request $request)
    {
        try {
            $user = $request->user('sanctum');

            if (! $user) {
                $profiles = User::query()
                    ->where('is_visible', true)
                    ->where('role', '!=', 'admin')
                    ->with(['photos', 'interests'])
                    ->latest()
                    ->get()
                    ->map(fn (User $profile) => $this->profilePayload($profile, $user));

                return response()->json($profiles);
            }

            $profiles = User::query()
                ->with(['photos', 'interests'])
                ->where('is_visible', true)
                ->where('role', '!=', 'admin')
                ->whereKeyNot($user->id)
                ->latest()
                ->get()
                ->map(fn (User $profile) => $this->profilePayload($profile, $user));

            return response()->json($profiles);
        } catch (\Throwable $e) {
            logger()->error('ProfileController.discover error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur lors de la récupération des profils.'], 500);
        }
    }

    public function me(Request $request)
    {
        try {
            return response()->json($this->userPayload($request->user('sanctum')->load(['photos', 'interests'])));
        } catch (\Throwable $e) {
            logger()->error('ProfileController.me error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur lors de la récupération du profil.'], 500);
        }
    }

    public function update(Request $request)
    {
        try {
            $data = $request->validate([
                'first_name' => ['sometimes', 'required', 'string', 'max:255'],
                'last_name' => ['sometimes', 'required', 'string', 'max:255'],
                'birth_date' => ['sometimes', 'nullable', 'date'],
                'gender' => ['sometimes', 'nullable', 'string', 'max:50'],
                'city' => ['sometimes', 'nullable', 'string', 'max:120'],
                'country' => ['sometimes', 'nullable', 'string', 'max:120'],
                'intention' => ['sometimes', 'nullable', 'string', 'max:255'],
                'bio' => ['sometimes', 'nullable', 'string'],
                'interests' => ['sometimes', 'array'],
                'interests.*' => ['string', 'max:80'],
            ]);

            $user = DB::transaction(function () use ($data, $request) {
                $user = $request->user('sanctum');
                $interests = $data['interests'] ?? null;
                unset($data['interests']);

                $user->forceFill($data)->save();

                if (is_array($interests)) {
                    $user->interests()->delete();

                    foreach ($interests as $interest) {
                        $user->interests()->create(['name' => $interest]);
                    }
                }

                return $user->load(['photos', 'interests']);
            });

            return response()->json($this->userPayload($user));
        } catch (\Throwable $e) {
            logger()->error('ProfileController.update error', [
                'user_id' => $request->user('sanctum')?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur lors de la mise à jour du profil.'], 500);
        }
    }

    public function likedMe(Request $request)
    {
        try {
            $user = $request->user('sanctum');

            $likedIds = ProfileAction::query()
                ->where('target_id', $user->id)
                ->where('type', 'like')
                ->pluck('actor_id');

            $handledIds = ProfileAction::query()
                ->where('actor_id', $user->id)
                ->whereIn('type', ['like', 'pop', 'decline'])
                ->pluck('target_id');

            $profiles = User::query()
                ->with(['photos', 'interests'])
                ->whereIn('id', $likedIds)
                ->whereNotIn('id', $handledIds)
                ->get();

            return response()->json(
                $profiles->map(
                    fn (User $profile) => $this->profilePayload($profile, $user)
                )
            );
        } catch (\Throwable $e) {
            logger()->error('ProfileController.likedMe error', [
                'user_id' => $request->user('sanctum')?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur lors de la récupération des likes.'], 500);
        }
    }

    public function show(Request $request, User $user)
    {
        try {
            return response()->json($this->profilePayload($user->load(['photos', 'interests']), $request->user('sanctum')));
        } catch (\Throwable $e) {
            logger()->error('ProfileController.show error', [
                'profile_id' => $user->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur lors de la récupération du profil.'], 500);
        }
    }

    public function uploadPhoto(Request $request)
    {
        try {
            $data = $request->validate([
                'photo' => ['required', 'image', 'max:5120'],
            ]);

            $path = $data['photo']->store('profile-photos', 'public');
            $photo = ProfilePhoto::query()->create([
                'user_id' => $request->user('sanctum')->id,
                'path' => 'storage/' . $path,
                'url' => asset($path),
                'position' => ProfilePhoto::query()->where('user_id', $request->user('sanctum')->id)->count(),
                'is_primary' => ! ProfilePhoto::query()->where('user_id', $request->user('sanctum')->id)->exists(),
            ]);

            return response()->json(['url' => $photo->path], 201);
        } catch (\Throwable $e) {
            logger()->error('ProfileController.uploadPhoto error', [
                'user_id' => $request->user('sanctum')?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur lors de l\'upload de la photo.'], 500);
        }
    }

    public function deletePhoto(Request $request, ProfilePhoto $photo)
    {
        try {
            $user = $request->user('sanctum');

            if ($photo->user_id !== $user->id) {
                return response()->json(['message' => 'Photo introuvable.'], 404);
            }

            $storagePath = str_replace('storage/', '', $photo->path);

            if (Storage::disk('public')->exists($storagePath)) {
                Storage::disk('public')->delete($storagePath);
            }

            $photo->delete();

            $remainingPhotos = $user->photos()->orderBy('position')->get();

            if ($remainingPhotos->count() && ! $remainingPhotos->where('is_primary', true)->count()) {
                $remainingPhotos->first()->update(['is_primary' => true]);
            }

            return response()->json(['success' => true]);
        } catch (\Throwable $e) {
            logger()->error('ProfileController.deletePhoto error', [
                'user_id' => $request->user('sanctum')?->id,
                'photo_id' => $photo->id ?? null,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json(['message' => 'Erreur lors de la suppression de la photo.'], 500);
        }
    }

    private function profilePayload(User $profile, ?User $viewer = null): array
    {

        $liked = $viewer
            ? ProfileAction::where('actor_id', $viewer->id)
                ->where('target_id', $profile->id)
                ->where('type', 'like')
                ->exists()
            : false;

        $likedMe = $viewer
            ? ProfileAction::where('actor_id', $profile->id)
                ->where('target_id', $viewer->id)
                ->where('type', 'like')
                ->exists()
            : false;

        $matched = $viewer
            ? MatchModel::query()
                ->where(function ($query) use ($viewer, $profile) {
                    $query->where('user_one_id', $viewer->id)
                        ->where('user_two_id', $profile->id);
                })
                ->orWhere(function ($query) use ($viewer, $profile) {
                    $query->where('user_one_id', $profile->id)
                        ->where('user_two_id', $viewer->id);
                })
                ->exists()
            : false;

        $poped = $viewer
            ? ProfileAction::where('actor_id', $viewer->id)
                ->where('target_id', $profile->id)
                ->where('type', 'pop')
                ->exists()
            : false;

        $popedMe = $viewer
            ? ProfileAction::where('actor_id', $profile->id)
                ->where('target_id', $viewer->id)
                ->where('type', 'pop')
                ->exists()
            : false;

        return [
            'id' => (string) $profile->id,
            'name' => $profile->displayName(),
            'age' => $profile->age() ?? 18,
            'city' => $profile->city ?? '',
            'country' => $profile->country ?? '',
            'bio' => $profile->bio ?? '',
            'intention' => $profile->intention ?? '',
            'verified' => (bool) $profile->verified,
            'distance' => '0 km',
            'pictures' => $profile->photos->map(fn (ProfilePhoto $photo) => [
                'id' => (string) $photo->id,
                'name' => $photo->path,
                'isPrimary' => (bool) $photo->is_primary,
            ])->values(),
            'avatar' => optional($profile->photos->first())->path ?? null,
            'interests' => $profile->interests->pluck('name')->values(),
            'liked' => $liked,
            'likedMe' => $likedMe,
            'matched' => $matched,
            'poped' => $poped,
            'popedMe' => $popedMe,
            'lastSeen' => optional($profile->last_seen_at)->diffForHumans(),
        ];
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'firstName' => $user->first_name,
            'lastName' => $user->last_name,
            'username' => $user->username,
            'phone' => $user->phone,
            'email' => $user->email,
            'birthDate' => optional($user->birth_date)->toDateString(),
            'gender' => $user->gender,
            'city' => $user->city,
            'country' => $user->country,
            'intention' => $user->intention,
            'bio' => $user->bio,
            'avatar' => optional($user->photos->first())->path ?? null,
            'pictures' => $user->photos->map(fn ($photo) => ['id' => (string) $photo->id, 'name' => $photo->path])->values(),
            'age' => $user->age(),
            'interests' => $user->interests->pluck('name')->values(),
        ];
    }
}
