<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class VarnishDomainService
{
    public const MODES = ['bypass', 'shield', 'cache'];
    public const STATIC_MODES = ['nginx_direct', 'varnish_cached'];

    public function settings(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $defaults = $this->defaultSettings($domain);

        if (!Schema::hasTable('varnish_domain_settings')) {
            return $defaults;
        }

        $row = DB::table('varnish_domain_settings')->where('domain', $domain)->first();
        if (!$row) {
            return $defaults;
        }

        return $this->normalizeSettings(array_merge($defaults, [
            'varnish_enabled' => (bool) $row->varnish_enabled,
            'varnish_mode' => $row->varnish_mode,
            'static_asset_mode' => $row->static_asset_mode,
            'html_ttl' => (int) $row->html_ttl,
            'static_ttl' => (int) $row->static_ttl,
            'grace_ttl' => (int) $row->grace_ttl,
            'purge_enabled' => (bool) $row->purge_enabled,
            'last_purged_at' => $row->last_purged_at,
        ]));
    }

    public function configure(string $domain, array $input): array
    {
        $settings = $this->saveSettings($domain, $input);
        $apply = $this->apply($domain);

        return array_merge($settings, $apply);
    }

    public function saveSettings(string $domain, array $input): array
    {
        $domain = $this->normalizeDomain($domain);
        $account = $this->findAccount($domain);
        $current = $this->settings($domain);

        $settings = $this->normalizeSettings(array_merge($current, array_filter([
            'varnish_enabled' => array_key_exists('varnish_enabled', $input) ? (bool) $input['varnish_enabled'] : null,
            'varnish_mode' => $input['varnish_mode'] ?? null,
            'static_asset_mode' => $input['static_asset_mode'] ?? null,
            'html_ttl' => array_key_exists('html_ttl', $input) ? (int) $input['html_ttl'] : null,
            'static_ttl' => array_key_exists('static_ttl', $input) ? (int) $input['static_ttl'] : null,
            'grace_ttl' => array_key_exists('grace_ttl', $input) ? (int) $input['grace_ttl'] : null,
            'purge_enabled' => array_key_exists('purge_enabled', $input) ? (bool) $input['purge_enabled'] : null,
        ], fn($value) => $value !== null)));

        if (!Schema::hasTable('varnish_domain_settings')) {
            throw new RuntimeException('varnish_domain_settings table is missing. Run migrations first.');
        }

        DB::table('varnish_domain_settings')->updateOrInsert(
            ['domain' => $domain],
            [
                'account_id' => $account->id,
                'varnish_enabled' => $settings['varnish_enabled'],
                'varnish_mode' => $settings['varnish_mode'],
                'static_asset_mode' => $settings['static_asset_mode'],
                'html_ttl' => $settings['html_ttl'],
                'static_ttl' => $settings['static_ttl'],
                'grace_ttl' => $settings['grace_ttl'],
                'purge_enabled' => $settings['purge_enabled'],
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );

        return $settings;
    }

    public function apply(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $stack = WebStackService::getActiveStack();

        if ($stack !== 'nginx_varnish_apache') {
            return [
                'active_stack' => $stack,
                'nginx_config_status' => 'skipped',
                'varnish_config_status' => 'skipped',
                'reload_status' => 'skipped',
                'message' => 'Varnish domain settings are stored; active stack does not use Varnish.',
            ];
        }

        $web = new WebStackService();
        $web->refreshVarnishDomain($domain);

        $ssl = new DomainSslService();
        if ($ssl->certificatePathsForDomain($domain)) {
            $ssl->installExistingCertificate($domain);
        }

        return [
            'active_stack' => $stack,
            'nginx_config_status' => $this->commandStatus('sudo nginx -t >/dev/null 2>&1'),
            'varnish_config_status' => $this->commandStatus('sudo varnishd -C -f /etc/varnish/default.vcl >/dev/null 2>&1'),
            'reload_status' => 'reloaded',
        ];
    }

    public function status(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $settings = $this->settings($domain);
        $stack = WebStackService::getActiveStack();

        return array_merge($settings, [
            'active_stack' => $stack,
            'effective_mode' => $this->effectiveMode($settings),
            'nginx_config_status' => $this->commandStatus('sudo nginx -t >/dev/null 2>&1'),
            'varnish_config_status' => $stack === 'nginx_varnish_apache'
                ? $this->commandStatus('sudo varnishd -C -f /etc/varnish/default.vcl >/dev/null 2>&1')
                : 'skipped',
            'vcl_installed' => $this->vclInstalled($domain),
            'nginx_static_direct' => $this->nginxStaticDirectInstalled($domain),
        ]);
    }

    public function purge(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $settings = $this->settings($domain);
        if (!$settings['purge_enabled']) {
            throw new RuntimeException('Purge is disabled for this domain.');
        }

        Process::run('varnishadm ' . escapeshellarg('ban req.http.host == "' . $domain . '"') . ' 2>&1');
        Process::run('varnishadm ' . escapeshellarg('ban req.http.host == "www.' . $domain . '"') . ' 2>&1');

        if (Schema::hasTable('varnish_domain_settings')) {
            DB::table('varnish_domain_settings')->where('domain', $domain)->update([
                'last_purged_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return [
            'success' => true,
            'domain' => $domain,
            'purged' => true,
            'last_purged_at' => now()->toIso8601String(),
        ];
    }

    public function test(string $domain): array
    {
        $domain = $this->normalizeDomain($domain);
        $this->purge($domain);

        $first = $this->curlHeaders($domain);
        $second = $this->curlHeaders($domain);

        return [
            'success' => true,
            'domain' => $domain,
            'settings' => $this->settings($domain),
            'first' => $first,
            'second' => $second,
            'html_hit' => strtoupper($second['x_cache'] ?? '') === 'HIT',
        ];
    }

    public function effectiveMode(array $settings): string
    {
        return ($settings['varnish_enabled'] ?? true) ? ($settings['varnish_mode'] ?? 'shield') : 'bypass';
    }

    protected function defaultSettings(string $domain): array
    {
        $site = Schema::hasTable('wordpress_sites')
            ? DB::table('wordpress_sites')->where('domain', $domain)->whereNull('deleted_at')->first()
            : null;

        $mode = 'shield';
        $enabled = true;
        $htmlTtl = 0;

        if ($site) {
            $profile = $site->performance_profile ?? 'safe_default';
            if ($profile === 'high_traffic' || (!empty($site->varnish_enabled) && $profile === 'safe_default')) {
                $mode = 'cache';
                $htmlTtl = 300;
            } elseif (in_array($profile, ['development', 'staging'], true)) {
                $mode = 'bypass';
                $enabled = false;
            }
        }

        return [
            'domain' => $domain,
            'varnish_enabled' => $enabled,
            'varnish_mode' => $mode,
            'static_asset_mode' => 'nginx_direct',
            'html_ttl' => $htmlTtl,
            'static_ttl' => 86400,
            'grace_ttl' => 3600,
            'purge_enabled' => true,
            'last_purged_at' => null,
        ];
    }

    protected function normalizeSettings(array $settings): array
    {
        if (!in_array($settings['varnish_mode'], self::MODES, true)) {
            throw new RuntimeException('Invalid varnish_mode.');
        }
        if (!in_array($settings['static_asset_mode'], self::STATIC_MODES, true)) {
            throw new RuntimeException('Invalid static_asset_mode.');
        }

        $settings['html_ttl'] = max(0, min(86400, (int) $settings['html_ttl']));
        $settings['static_ttl'] = max(60, min(31536000, (int) $settings['static_ttl']));
        $settings['grace_ttl'] = max(0, min(86400, (int) $settings['grace_ttl']));

        return $settings;
    }

    protected function findAccount(string $domain): object
    {
        $account = DB::connection('mysql')->table('accounts')->where('domain', $domain)->first();
        if (!$account && str_starts_with($domain, 'www.')) {
            $account = DB::connection('mysql')->table('accounts')->where('domain', substr($domain, 4))->first();
        }
        if (!$account) {
            throw new RuntimeException("No hosting account found for {$domain}.");
        }

        return $account;
    }

    protected function normalizeDomain(string $domain): string
    {
        $domain = strtolower(trim(preg_replace('#^https?://#', '', $domain)));
        $domain = trim($domain, "/ \t\n\r\0\x0B.");

        if (!preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/', $domain)) {
            throw new RuntimeException('Invalid domain name.');
        }

        return $domain;
    }

    protected function commandStatus(string $command): string
    {
        return Process::run($command)->successful() ? 'valid' : 'invalid';
    }

    protected function curlHeaders(string $domain): array
    {
        $result = Process::timeout(20)->run(
            'curl -skI -H ' . escapeshellarg("Host: {$domain}") . ' http://127.0.0.1/ 2>/dev/null'
        );
        $headers = $this->parseHeaders($result->output());

        return [
            'http_status' => $headers['status'] ?? null,
            'x_cache' => $headers['x-cache'] ?? null,
            'age' => $headers['age'] ?? null,
            'via' => $headers['via'] ?? null,
            'cache_control' => $headers['cache-control'] ?? null,
        ];
    }

    protected function parseHeaders(string $raw): array
    {
        $headers = [];
        foreach (preg_split('/\r?\n/', trim($raw)) as $line) {
            if (preg_match('/^HTTP\/\S+\s+(\d+)/', $line, $m)) {
                $headers['status'] = (int) $m[1];
                continue;
            }
            if (str_contains($line, ':')) {
                [$name, $value] = explode(':', $line, 2);
                $headers[strtolower(trim($name))] = trim($value);
            }
        }

        return $headers;
    }

    protected function vclInstalled(string $domain): bool
    {
        $account = $this->findAccount($domain);
        $path = "/etc/varnish/conf.d/users/{$account->username}.vcl";

        return is_file($path);
    }

    protected function nginxStaticDirectInstalled(string $domain): bool
    {
        $account = $this->findAccount($domain);
        $path = "/etc/nginx/conf.d/users/{$account->username}.conf";

        return is_file($path) && str_contains((string) @file_get_contents($path), '@openpanel_backend');
    }
}
