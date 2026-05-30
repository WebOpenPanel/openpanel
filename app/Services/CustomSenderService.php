<?php

namespace App\Services;

class CustomSenderService
{
    const SENDER_PATH = '/usr/local/openpanel/include/modules/custom_sender/';

    public static function isInstalled(): bool
    {
        return is_dir(self::SENDER_PATH);
    }

    public static function send(string $from, string $to, string $subject, string $message): bool
    {
        $headers = "From: {$from}\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        return mail($to, $subject, $message, $headers);
    }
}
