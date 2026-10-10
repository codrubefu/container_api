<?php

namespace Tests\Support;

use App\Contracts\SmsSender;
use PHPUnit\Framework\Assert;

class FakeSmsSender implements SmsSender
{
    /**
     * @var list<array{phone: string, message: string}>
     */
    public array $sent = [];

    public function send(string $phone, string $message): void
    {
        $this->sent[] = ['phone' => $phone, 'message' => $message];
    }

    /**
     * The 6-digit code from the most recent message sent to the phone number.
     */
    public function lastCodeFor(string $phone): string
    {
        $messages = array_values(array_filter($this->sent, fn (array $sms) => $sms['phone'] === $phone));

        Assert::assertNotEmpty($messages, "No SMS was sent to {$phone}.");
        Assert::assertMatchesRegularExpression('/\b(\d{6})\b/', end($messages)['message']);

        preg_match('/\b(\d{6})\b/', end($messages)['message'], $matches);

        return $matches[1];
    }

    public function assertNothingSent(): void
    {
        Assert::assertSame([], $this->sent, 'An unexpected SMS was sent.');
    }

    public function assertSentCount(int $count): void
    {
        Assert::assertCount($count, $this->sent);
    }
}
