<?php

namespace App\Services\Auth;

use App\Contracts\SmsSender;
use App\Models\LoginChallenge;
use App\Models\User;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * The second login step: a 6-digit code sent by SMS that must be confirmed before a token is issued.
 */
class LoginChallengeService
{
    public function __construct(private SmsSender $sms) {}

    /**
     * Create a challenge for the user and send its code by SMS.
     */
    public function start(User $user, string $deviceName, ?string $ip): LoginChallenge
    {
        $code = $this->generateCode();

        $challenge = $user->loginChallenges()->create([
            'code_hash' => Hash::make($code),
            'device_name' => $deviceName,
            'ip' => $ip,
            'last_sent_at' => now(),
            'expires_at' => now()->addSeconds(config('auth.two_factor.code_ttl')),
        ]);

        $this->sendCode($user, $code);

        return $challenge;
    }

    /**
     * Replace the challenge's code with a new one and send it, once the cooldown has passed.
     *
     * @throws ValidationException when the challenge can no longer be used
     * @throws ThrottleRequestsException when the cooldown has not passed yet
     */
    public function resend(LoginChallenge $challenge): LoginChallenge
    {
        if (! $challenge->isUsable() || ! $challenge->user->is_active) {
            throw $this->invalidChallenge();
        }

        $availableAt = $challenge->last_sent_at->addSeconds(config('auth.two_factor.resend_cooldown'));

        if ($availableAt->isFuture()) {
            $retryAfter = (int) ceil(now()->diffInSeconds($availableAt));

            throw new ThrottleRequestsException(
                "Puteți retrimite codul peste {$retryAfter} secunde.",
                headers: ['Retry-After' => $retryAfter],
            );
        }

        $code = $this->generateCode();

        $challenge->update([
            'code_hash' => Hash::make($code),
            'last_sent_at' => now(),
            'expires_at' => now()->addSeconds(config('auth.two_factor.code_ttl')),
        ]);

        $this->sendCode($challenge->user, $code);

        return $challenge;
    }

    /**
     * Check the code and consume the challenge. Each wrong code counts as an attempt; once the
     * maximum is reached the challenge is invalidated and the user must log in again.
     *
     * @throws ValidationException when the challenge is unusable or the code is wrong
     */
    public function verify(string $challengeId, string $code): LoginChallenge
    {
        /**
         * The consumed challenge, the attempts left after a wrong code, or null when the challenge is
         * unusable. Errors are thrown after the transaction so a failed attempt is still recorded.
         *
         * @var LoginChallenge|int|null $outcome
         */
        $outcome = DB::transaction(function () use ($challengeId, $code): LoginChallenge|int|null {
            $challenge = LoginChallenge::query()->whereKey($challengeId)->lockForUpdate()->first();

            if ($challenge === null || ! $challenge->isUsable() || ! $challenge->user->is_active) {
                return null;
            }

            if (! $this->codeMatches($challenge, $code)) {
                $challenge->increment('attempts');

                return config('auth.two_factor.max_attempts') - $challenge->attempts;
            }

            $challenge->update(['consumed_at' => now()]);

            return $challenge;
        });

        if ($outcome instanceof LoginChallenge) {
            return $outcome;
        }

        if ($outcome === null) {
            throw $this->invalidChallenge();
        }

        throw ValidationException::withMessages([
            'code' => $outcome > 0
                ? "Codul este incorect. Mai aveți {$outcome} încercări."
                : 'Prea multe încercări greșite. Autentificați-vă din nou.',
        ]);
    }

    private function codeMatches(LoginChallenge $challenge, string $code): bool
    {
        if (Hash::check($code, $challenge->code_hash)) {
            return true;
        }

        $demoCode = (string) config('auth.two_factor.demo_code');

        return app()->isLocal() && $demoCode !== '' && hash_equals($demoCode, $code);
    }

    private function generateCode(): string
    {
        return str_pad((string) random_int(0, 999_999), 6, '0', STR_PAD_LEFT);
    }

    private function sendCode(User $user, string $code): void
    {
        $minutes = intdiv(config('auth.two_factor.code_ttl'), 60);

        $this->sms->send($user->phone, "Codul de autentificare Befu Containere: {$code}. Expiră în {$minutes} minute.");
    }

    private function invalidChallenge(): ValidationException
    {
        return ValidationException::withMessages([
            'challenge_id' => 'Codul a expirat sau nu mai este valid. Autentificați-vă din nou.',
        ]);
    }
}
