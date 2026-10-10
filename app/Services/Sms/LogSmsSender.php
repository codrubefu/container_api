<?php

namespace App\Services\Sms;

use App\Contracts\SmsSender;
use Illuminate\Support\Facades\Log;

/**
 * Writes messages to the application log instead of sending them. For local development and tests only.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS to {phone}: {message}', ['phone' => $phone, 'message' => $message]);
    }
}
