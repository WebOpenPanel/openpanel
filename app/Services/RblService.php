<?php

namespace App\Services;

class RblService
{
    protected static array $blacklists = [
        'zen.spamhaus.org',
        'bl.spamcop.net',
        'b.barracudacentral.org',
        'dnsbl.sorbs.net',
        'spam.dnsbl.sorbs.net',
        'dul.dnsbl.sorbs.net',
        'dnsbl-1.uceprotect.net',
        'dnsbl-2.uceprotect.net',
        'dnsbl-3.uceprotect.net',
        'cbl.abuseat.org',
        'dyna.spamrats.com',
        'noptr.spamrats.com',
        'spam.spamrats.com',
        'bl.mailspike.net',
        'z.mailspike.net',
        'ix.dnsbl.manitu.net',
        'truncate.gbudb.net',
        'db.wpbl.info',
    ];

    public static function checkIp(string $ip): array
    {
        $reversed = implode('.', array_reverse(explode('.', $ip)));
        $results = [];

        foreach (self::$blacklists as $bl) {
            $lookup = $reversed . '.' . $bl;
            $output = ShellService::exec("dig +short {$lookup} 2>/dev/null");
            $listed = !empty(trim($output)) && trim($output) !== '0.0.0.0';
            $results[] = [
                'blacklist' => $bl,
                'listed' => $listed,
                'response' => trim($output),
            ];
        }

        $listedCount = count(array_filter($results, fn($r) => $r['listed']));

        return [
            'ip' => $ip,
            'clean' => $listedCount === 0,
            'listed_count' => $listedCount,
            'total_checked' => count(self::$blacklists),
            'results' => $results,
            'checked_at' => now()->toDateTimeString(),
        ];
    }

    public static function checkAllIps(): array
    {
        $ips = IpService::listIps();
        $results = [];
        foreach ($ips as $ip) {
            $ipAddr = $ip['ip'] ?? $ip;
            if ($ipAddr) {
                $results[] = self::checkIp($ipAddr);
            }
        }
        return $results;
    }

    public static function getBlacklists(): array
    {
        return self::$blacklists;
    }

    public static function getCustomBlacklists(): array
    {
        $path = '/usr/local/openpanel/.conf/custom_rbl.conf';
        if (!file_exists($path)) {
            return [];
        }
        $lines = array_filter(explode("\n", file_get_contents($path)));
        return array_map('trim', $lines);
    }

    public static function addBlacklist(string $domain): array
    {
        $path = '/usr/local/openpanel/.conf/custom_rbl.conf';
        $existing = self::getCustomBlacklists();
        if (in_array($domain, $existing)) {
            return ['success' => false, 'message' => 'Blacklist already exists.'];
        }
        $existing[] = $domain;
        file_put_contents($path, implode("\n", $existing) . "\n");
        return ['success' => true, 'message' => "Added {$domain} to blacklist check list."];
    }

    public static function removeBlacklist(string $domain): array
    {
        $path = '/usr/local/openpanel/.conf/custom_rbl.conf';
        $existing = self::getCustomBlacklists();
        $existing = array_filter($existing, fn($bl) => $bl !== $domain);
        file_put_contents($path, implode("\n", array_values($existing)) . "\n");
        return ['success' => true, 'message' => "Removed {$domain} from blacklist check list."];
    }
}
