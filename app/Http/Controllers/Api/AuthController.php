<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\ResendCodeRequest;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use App\Http\Requests\Auth\VerifyCodeRequest;
use App\Http\Resources\UserResource;
use App\Models\LoginChallenge;
use App\Models\User;
use App\Services\Auth\LoginChallengeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private LoginChallengeService $challenges) {}

    /**
     * Step 1: check the credentials and send an SMS code. No token is issued here.
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', $request->validated('email'))->first();

        $credentialsAreValid = $user !== null
            && Hash::check($request->validated('password'), $user->password)
            && $user->is_active
            && filled($user->phone);

        if (! $credentialsAreValid) {
            throw ValidationException::withMessages([
                'email' => 'Datele de autentificare sunt incorecte.',
            ]);
        }

        $challenge = $this->challenges->start($user, $request->deviceName(), $request->ip());

        return response()->json($this->challengePayload($challenge, $user));
    }

    /**
     * Step 2: confirm the SMS code and issue the API token.
     */
    public function verify(VerifyCodeRequest $request): JsonResponse
    {
        $challenge = $this->challenges->verify($request->validated('challenge_id'), $request->validated('code'));
        $user = $challenge->user;

        $expiresAt = now()->addMinutes(config('sanctum.expiration'));
        $token = $user->createToken($challenge->device_name, ['*'], $expiresAt);

        $user->forceFill(['last_login_at' => now()])->save();
        $request->setUserResolver(fn () => $user);

        return response()->json([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => new UserResource($user->load('roles.permissions', 'sections')),
        ]);
    }

    /**
     * Send a new code for an open challenge (subject to a cooldown).
     */
    public function resend(ResendCodeRequest $request): JsonResponse
    {
        $challenge = LoginChallenge::query()->find($request->validated('challenge_id'));

        if ($challenge === null) {
            throw ValidationException::withMessages([
                'challenge_id' => 'Codul a expirat sau nu mai este valid. Autentificați-vă din nou.',
            ]);
        }

        $challenge = $this->challenges->resend($challenge);

        return response()->json($this->challengePayload($challenge, $challenge->user));
    }

    /**
     * Revoke the token used for the current request.
     */
    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()->delete();

        return response()->noContent();
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user()->load('roles.permissions', 'sections'));
    }

    /**
     * Change the current user's password and revoke all their other tokens.
     */
    public function updatePassword(UpdatePasswordRequest $request): Response
    {
        $user = $request->user();

        $user->update(['password' => $request->validated('password')]);
        $user->tokens()->whereKeyNot($user->currentAccessToken()->getKey())->delete();

        return response()->noContent();
    }

    /**
     * @return array{challenge_id: string, phone_masked: string|null, expires_at: string, resend_available_at: string}
     */
    private function challengePayload(LoginChallenge $challenge, User $user): array
    {
        return [
            'challenge_id' => $challenge->id,
            'phone_masked' => $user->maskedPhone(),
            'expires_at' => $challenge->expires_at->toIso8601String(),
            'resend_available_at' => $challenge->last_sent_at
                ->addSeconds(config('auth.two_factor.resend_cooldown'))
                ->toIso8601String(),
        ];
    }
}
