<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MatchModel;
use App\Models\ProfileAction;
use App\Models\ProfilePhoto;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function discover(Request $request)
    {
        $user = $request->user('sanctum');

        if (! $user) {
            $profiles = User::query()
                ->with(['photos', 'interests'])
                ->latest()
                ->get()
                ->map(fn (User $profile) => $this->profilePayload($profile, $user));

            return response()->json($profiles);
        }

        $profiles = User::query()
            ->with(['photos', 'interests'])
            ->whereKeyNot($user->id)
            ->latest()
            ->get()
            ->map(fn (User $profile) => $this->profilePayload($profile, $user));

        return response()->json($profiles);
    }

    public function me(Request $request)
    {
        return response()->json($this->userPayload($request->user('sanctum')->load(['photos', 'interests'])));
    }

    public function update(Request $request)
    {
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
    }

    public function likedMe(Request $request)
    {
        $user = $request->user('sanctum');
        $ids = ProfileAction::query()
            ->where('target_id', $user->id)
            ->where('type', 'like')
            ->pluck('actor_id');

        return response()->json(
            User::query()->with(['photos', 'interests'])->whereIn('id', $ids)->get()
                ->map(fn (User $profile) => $this->profilePayload($profile, $user))
        );
    }

    public function show(Request $request, User $user)
    {
        return response()->json($this->profilePayload($user->load(['photos', 'interests']), $request->user('sanctum')));
    }

    public function uploadPhoto(Request $request)
    {
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
    }

    private function profilePayload(User $profile, ?User $viewer = null): array
    {

        $liked = $viewer
            ? ProfileAction::query()
                ->where('actor_id', $viewer->id)
                ->where('target_id', $profile->id)
                ->where('type', 'like')
                ->exists()
            : false;

        $likedYou = $viewer
            ? ProfileAction::query()
                ->where('actor_id', $profile->id)
                ->where('target_id', $viewer->id)
                ->where('type', 'like')
                ->exists()
            : false;

        $poped = $viewer
            ? ProfileAction::query()
                ->where('actor_id', $viewer->id ?? 0)
                ->where('target_id', $profile->id)
                ->where('type', 'pop')
                ->exists()
            : false;

        $matched = MatchModel::query()
            ->where(function ($query) use ($viewer, $profile) {
                $query->where('user_one_id', $viewer->id ?? 0)
                    ->where('user_two_id', $profile->id);
            })
            ->orWhere(function ($query) use ($viewer, $profile) {
                $query->where('user_one_id', $profile->id)
                    ->where('user_two_id', $viewer->id ?? 0);
            })
            ->exists() ?? $liked && $likedYou ?? false;

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
            'pictures' => $profile->photos->map(fn (ProfilePhoto $photo) => ['name' => $photo->path])->values(),
            'avatar' => optional($profile->photos->first())->path ?? null,
            'interests' => $profile->interests->pluck('name')->values(),
            'liked' => $liked,
            'likedYou' => $likedYou,
            'poped' => $poped,
            'matched' => $matched,
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
            'age' => $user->age(),
            'interests' => $user->interests->pluck('name')->values(),
        ];
    }
}
