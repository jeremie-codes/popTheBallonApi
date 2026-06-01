<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $data = $request->validate([
                'identifier' => ['required', 'string'],
                'password' => ['required', 'string'],
            ]);

            $identifier = Str::lower($data['identifier']);
            $user = User::query()
                ->whereRaw('LOWER(email) = ?', [$identifier])
                ->orWhereRaw('LOWER(username) = ?', [$identifier])
                ->orWhere('phone', $data['identifier'])
                ->first();

            if (! $user || ! Hash::check($data['password'], $user->password)) {
                return response()->json(['message' => 'Identifiant ou mot de passe incorrect'], 422);
            }

            return response()->json($this->authResponse($user));
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }

    public function register(Request $request)
    {
        try {
            $data = $request->validate([
                'first_name' => ['required', 'string', 'max:255'],
                'last_name' => ['required', 'string', 'max:255'],
                'username' => 'required|string|max:255|unique:users,username',
                'phone' => ['required', 'string', 'max:30', 'unique:users,phone'],
                'email' => ['nullable', 'email', 'max:255', 'unique:users,email'],
                'password' => ['required', Password::min(8)],
                'birth_date' => ['nullable', 'date'],
                'gender' => ['nullable', 'string', 'max:50'],
                'city' => ['nullable', 'string', 'max:120'],
                'country' => ['nullable', 'string', 'max:120'],
                'intention' => ['nullable', 'string', 'max:255'],
                'bio' => ['nullable', 'string'],
                'interests' => ['nullable', 'array'],
                'interests.*' => ['string', 'max:80'],
            ]);

            $user = DB::transaction(function () use ($data) {
                $user = User::query()->create([
                    'first_name' => $data['first_name'],
                    'last_name' => $data['last_name'],
                    'username' => Str::lower($data['username']),
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? Str::lower($data['username']).'@poptheballon.local',
                    'password' => $data['password'],
                    'birth_date' => $data['birth_date'] ?? null,
                    'gender' => $data['gender'] ?? null,
                    'city' => $data['city'] ?? null,
                    'country' => $data['country'] ?? null,
                    'intention' => $data['intention'] ?? null,
                    'bio' => $data['bio'] ?? null,
                    'last_seen_at' => now(),
                ]);

                foreach ($data['interests'] ?? [] as $interest) {
                    $user->interests()->create(['name' => $interest]);
                }

                return $user->load(['interests', 'photos']);
            });

            return response()->json($this->authResponse($user), 201);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }

    public function checkUsername(Request $request)
    {
        $data = $request->validate(['username' => ['required', 'string', 'max:50']]);
        $username = Str::lower($data['username']);
        $available = ! User::query()->where('username', $username)->exists();

        return response()->json([
            'available' => $available,
            'message' => $available ? 'Nom d utilisateur disponible' : 'Ce nom d utilisateur est deja pris',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        try {
            $data = $request->validate(['identifier' => ['required', 'string']]);
            $user = User::query()
                ->where('email', $data['identifier'])
                ->orWhere('username', $data['identifier'])
                ->orWhere('phone', $data['identifier'])
                ->first();

            if ($user) {
                DB::table('password_reset_tokens')->updateOrInsert(
                    ['email' => $user->email],
                    ['token' => Str::random(64), 'created_at' => now()]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Un lien de recuperation a ete envoye si le compte existe.',
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }

    public function resetPassword(Request $request)
    {
        try {
            $data = $request->validate([
                'token' => ['required', 'string'],
                'password' => ['required', Password::min(8)],
            ]);

            $reset = DB::table('password_reset_tokens')->where('token', $data['token'])->first();

            if (! $reset) {
                return response()->json(['message' => 'Token invalide.'], 422);
            }

            User::query()->where('email', $reset->email)->update(['password' => Hash::make($data['password'])]);
            DB::table('password_reset_tokens')->where('email', $reset->email)->delete();

            return response()->json(['success' => true, 'message' => 'Mot de passe mis a jour.']);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }

    private function authResponse(User $user): array
    {
        $token = $user->createToken('mobile', ['*'], now()->addDays(30));

        return [
            'code' => 'auth-ok',
            'token' => $token->plainTextToken,
            'expire_in' => 60 * 60 * 24 * 30,
            'merchant' => '',
            'shop' => '',
            'is_merchant' => false,
            'is_super_merchant' => false,
            'user' => [
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
                'avatar' => optional($user->photos->first())->path,
                'pictures' => $user->photos->map(fn ($photo) => [
                    'id' => (string) $photo->id,
                    'name' => $photo->path,
                    'isPrimary' => (bool) $photo->is_primary,
                ])->values(),
                'age' => $user->age(),
                'interests' => $user->interests->pluck('name')->values(),
            ],
        ];
    }
}
