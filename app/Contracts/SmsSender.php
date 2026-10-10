<?php

namespace App\Contracts;

interface SmsSender
{
    /**
     * Send a text message to the given phone number.
     */
    public function send(string $phone, string $message): void;
}
