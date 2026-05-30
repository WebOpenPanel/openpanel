<?php

namespace App\Services;

class ContactFormService
{
    public static function send(array $data): bool
    {
        $srvIp = $_SERVER['SERVER_ADDR'] ?? 'unknown';
        $subject = "OpenPanel [{$srvIp}] - " . ($data['subject'] ?? 'Feedback');
        $message = "Server IP: " . ($data['ipaddr'] ?? '') . "\nSSH Port: " . ($data['sshport'] ?? '') . "\n\n" . ($data['message'] ?? '');
        $message = wordwrap($message, 70);
        $from = $data['from'] ?? 'noreply@localhost';
        return mail('dadosoft@gmail.com', $subject, $message, "From: {$from}\r\n");
    }
}
