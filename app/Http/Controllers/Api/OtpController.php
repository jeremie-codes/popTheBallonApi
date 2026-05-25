<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtpCode;
use App\Models\User;
use Illuminate\Http\Request;

class OtpController extends Controller
{
    public function generateLogin(Request $request)
    {
        try {
            $data = $request->validate(['phone_number' => ['required', 'string', 'max:30']]);
            $user = User::query()->where('phone', $data['phone_number'])->first();

            if (! $user) {
                return response()->json(['message' => 'Utilisateur introuvable.'], 404);
            }

            $code = (string) random_int(100000, 999999);
            OtpCode::query()->create([
                'user_id' => $user->id,
                'phone_number' => $data['phone_number'],
                'code' => $code,
                'purpose' => 'login',
                'expires_at' => now()->addMinutes(10),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Code OTP envoye.',
                'user_id' => $user->id,
                'debug_otp' => config('app.debug') ? $code : null,
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }

    public function login(Request $request)
    {
        try {
            $data = $request->validate([
                'user_id' => ['required', 'exists:users,id'],
                'otp' => ['required', 'string'],
            ]);

            $otp = OtpCode::query()
                ->where('user_id', $data['user_id'])
                ->where('code', $data['otp'])
                ->where('purpose', 'login')
                ->whereNull('used_at')
                ->where('expires_at', '>', now())
                ->latest()
                ->first();

            if (! $otp) {
                return response()->json(['message' => 'Code OTP invalide ou expire.'], 422);
            }

            $otp->forceFill(['used_at' => now()])->save();
            $user = User::query()->findOrFail($data['user_id']);
            $token = $user->createToken('mobile', ['*'], now()->addDays(30));

            return response()->json([
                'code' => 'auth-ok',
                'success' => true,
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
                    'avatar' => optional($user->photos->first())->url,
                    'age' => $user->age(),
                    'interests' => $user->interests->pluck('name')->values(),
                ],
            ]);
        } catch (\Throwable $e) {
            return response()->json(['message' => 'Erreur interne', 'error' => $e->getMessage()], 500);
        }
    }
}
