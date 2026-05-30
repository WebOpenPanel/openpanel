<?php

namespace App\Services;

use Illuminate\Support\Facades\File;

class IcecastService
{
    protected static string $configDir = '/usr/local/openpanel/.conf/apps_manager';
    protected static string $serversFile = '/usr/local/openpanel/.conf/apps_manager/icecast_servers.json';
    protected static string $optionsFile = '/usr/local/openpanel/.conf/apps_manager/icecast';
    protected static string $templatePath = '/usr/share/icecast/icecast.tpl';

    public static function isInstalled(): bool
    {
        return (bool)ShellService::exec('which icecast 2>/dev/null');
    }

    public static function install(): array
    {
        $output = ShellService::exec('dnf -y install icecast 2>&1');
        if (self::isInstalled()) {
            File::ensureDirectoryExists(self::$configDir);
            return ['success' => true, 'message' => 'Icecast installed.', 'output' => $output];
        }
        return ['success' => false, 'message' => 'Installation failed.', 'output' => $output];
    }

    public static function getOptions(): array
    {
        if (!file_exists(self::$optionsFile)) {
            $defaults = ['enabled' => 0, 'port_range_min' => 17000, 'port_range_max' => 18000];
            File::ensureDirectoryExists(dirname(self::$optionsFile));
            file_put_contents(self::$optionsFile, json_encode($defaults));
            return $defaults;
        }
        return json_decode(file_get_contents(self::$optionsFile), true) ?: [];
    }

    public static function saveOptions(array $options): array
    {
        File::ensureDirectoryExists(dirname(self::$optionsFile));
        file_put_contents(self::$optionsFile, json_encode($options, JSON_PRETTY_PRINT));
        return ['success' => true, 'message' => 'Icecast options saved.'];
    }

    public static function getServers(): array
    {
        if (!file_exists(self::$serversFile)) {
            return [];
        }
        return json_decode(file_get_contents(self::$serversFile), true) ?: [];
    }

    public static function saveServers(array $servers): void
    {
        File::ensureDirectoryExists(dirname(self::$serversFile));
        file_put_contents(self::$serversFile, json_encode($servers, JSON_PRETTY_PRINT));
    }

    public static function addServer(array $data): array
    {
        $servers = self::getServers();
        $options = self::getOptions();

        $port = $data['port'] ?? ($options['port_range_min'] ?? 17000);
        foreach ($servers as $s) {
            if (($s['port'] ?? 0) == $port) {
                return ['success' => false, 'message' => "Port {$port} is already in use."];
            }
        }

        $server = [
            'user' => $data['user'] ?? '',
            'port' => (int)$port,
            'ip' => $data['ip'] ?? 'ALL',
            'listens' => $data['listens'] ?? 100,
            'sources' => $data['sources'] ?? 5,
            'password' => $data['password'] ?? 'openpanel_' . bin2hex(random_bytes(8)),
            'admin_password' => $data['admin_password'] ?? 'admin_' . bin2hex(random_bytes(8)),
            'hostname' => $data['hostname'] ?? gethostname(),
            'created' => date('Y-m-d H:i:s'),
        ];

        $servers[] = $server;
        self::saveServers($servers);

        self::generateConfig($server);
        self::createService($server);

        return ['success' => true, 'message' => "Icecast server created on port {$port}."];
    }

    public static function removeServer(int $port): array
    {
        $servers = self::getServers();
        $servers = array_filter($servers, fn($s) => ($s['port'] ?? 0) !== $port);
        self::saveServers(array_values($servers));

        ShellService::exec("systemctl stop icecast_{$port} 2>/dev/null");
        ShellService::exec("systemctl disable icecast_{$port} 2>/dev/null");
        ShellService::exec("rm -f /etc/systemd/system/icecast_{$port}.service");
        ShellService::exec("rm -f /usr/share/icecast/*/{$port}.conf");

        return ['success' => true, 'message' => "Icecast server on port {$port} removed."];
    }

    public static function startServer(int $port): array
    {
        $output = ShellService::exec("systemctl start icecast_{$port} 2>&1");
        return ['success' => true, 'output' => $output];
    }

    public static function stopServer(int $port): array
    {
        $output = ShellService::exec("systemctl stop icecast_{$port} 2>&1");
        return ['success' => true, 'output' => $output];
    }

    public static function restartServer(int $port): array
    {
        $output = ShellService::exec("systemctl restart icecast_{$port} 2>&1");
        return ['success' => true, 'output' => $output];
    }

    public static function getServerStatus(int $port): array
    {
        $running = trim(ShellService::exec("systemctl is-active icecast_{$port} 2>/dev/null")) === 'active';
        return ['running' => $running, 'port' => $port];
    }

    protected static function generateConfig(array $server): void
    {
        $user = $server['user'];
        $port = $server['port'];
        $confDir = "/usr/share/icecast/{$user}";
        File::ensureDirectoryExists($confDir);

        $bindAddress = ($server['ip'] ?? 'ALL') !== 'ALL' ? "<bind-address>{$server['ip']}</bind-address>" : '';

        $xml = <<<XML
<icecast>
    <hostname>{$server['hostname']}</hostname>
    <listen-socket>
        <port>{$port}</port>
        {$bindAddress}
    </listen-socket>
    <clients>{$server['listens']}</clients>
    <sources>{$server['sources']}</sources>
    <authentication>
        <source-password>{$server['password']}</source-password>
        <relay-password>{$server['password']}</relay-password>
        <admin-user>admin</admin-user>
        <admin-password>{$server['admin_password']}</admin-password>
    </authentication>
    <hostname>{$server['hostname']}</hostname>
    <fileserve>1</fileserve>
    <paths>
        <basedir>/usr/share/icecast</basedir>
        <logdir>/var/log/icecast/{$port}</logdir>
        <webroot>/usr/share/icecast/web</webroot>
        <adminroot>/usr/share/icecast/admin</adminroot>
        <alias source="/" destination="/status.xsl"/>
    </paths>
    <logging>
        <accesslog>access.log</accesslog>
        <errorlog>error.log</errorlog>
        <loglevel>3</loglevel>
    </logging>
</icecast>
XML;

        file_put_contents("{$confDir}/{$port}.conf", $xml);
        ShellService::exec("chown icecast:icecast {$confDir}/{$port}.conf");

        $logDir = "/var/log/icecast/{$port}";
        File::ensureDirectoryExists($logDir);
        ShellService::exec("chown -R icecast:icecast {$logDir}");
    }

    protected static function createService(array $server): void
    {
        $port = $server['port'];
        $user = $server['user'];
        $serviceFile = "/etc/systemd/system/icecast_{$port}.service";

        $ini = <<<INI
[Unit]
Description=Icecast Streaming Server port {$port}
After=network.target

[Service]
Type=simple
ExecStart=/usr/bin/icecast -c /usr/share/icecast/{$user}/{$port}.conf
ExecReload=/bin/kill -HUP \$MAINPID
User=icecast
Group=icecast
WorkingDirectory=/home/icecast/

[Install]
WantedBy=multi-user.target
INI;

        file_put_contents($serviceFile, $ini);
        ShellService::exec("systemctl daemon-reload && systemctl enable icecast_{$port}");
    }
}
