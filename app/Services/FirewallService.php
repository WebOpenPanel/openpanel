<?php

namespace App\Services;

class FirewallService
{
    protected string $backend;

    public function __construct()
    {
        $this->backend = $this->detectBackend();
    }

    public function backend(): string
    {
        return $this->backend;
    }

    public function isInstalled(): bool
    {
        return $this->backend !== 'none';
    }

    public function isActive(): bool
    {
        return match ($this->backend) {
            'firewalld' => trim(ShellService::exec('firewall-cmd --state 2>&1')) === 'running',
            'nftables' => (int) trim(ShellService::exec('nft list ruleset 2>/dev/null | wc -l')) > 0,
            'csf' => str_contains(ShellService::exec('csf -l 2>/dev/null') ?? '', 'Chain'),
            default => false,
        };
    }

    public function getStatus(): array
    {
        return [
            'backend' => $this->backend,
            'active' => $this->isActive(),
            'version' => $this->getVersion(),
        ];
    }

    public function getVersion(): string
    {
        return match ($this->backend) {
            'firewalld' => trim(ShellService::exec('firewall-cmd --version 2>/dev/null') ?: 'unknown'),
            'nftables' => trim(ShellService::exec('nft --version 2>/dev/null | head -1') ?: 'unknown'),
            'csf' => trim(ShellService::exec('csf --version 2>/dev/null | head -1') ?: 'unknown'),
            default => 'N/A',
        };
    }

    public function install(): bool
    {
        if ($this->backend !== 'none') {
            return true;
        }

        $result = ShellService::exec('dnf install -y firewalld 2>&1');
        if ($result && !str_contains($result, 'Error')) {
            ShellService::exec('systemctl enable --now firewalld 2>&1');
            $this->backend = 'firewalld';
            $this->openDefaultPorts();
            return true;
        }

        return false;
    }

    public function openDefaultPorts(): void
    {
        $ports = [
            '22/tcp', '80/tcp', '443/tcp',
            '53/tcp', '53/udp',
            '25/tcp', '465/tcp', '587/tcp',
            '110/tcp', '143/tcp', '993/tcp', '995/tcp',
            '21/tcp', '30000-50000/tcp',
            '2082/tcp', '2083/tcp', '2086/tcp', '2087/tcp',
            '2095/tcp', '2096/tcp',
        ];

        foreach ($ports as $port) {
            $this->allowPort($port);
        }
    }

    public function allowPort(string $port): bool
    {
        $port = escapeshellarg($port);
        return match ($this->backend) {
            'firewalld' => (bool) ShellService::exec("firewall-cmd --permanent --add-port={$port} 2>&1 && firewall-cmd --reload 2>&1"),
            'nftables' => (bool) $this->nftAddPort($port, 'accept'),
            'csf' => (bool) ShellService::exec("csf -a {$port} 2>&1"),
            default => false,
        };
    }

    public function denyPort(string $port): bool
    {
        $port = escapeshellarg($port);
        return match ($this->backend) {
            'firewalld' => (bool) ShellService::exec("firewall-cmd --permanent --remove-port={$port} 2>&1 && firewall-cmd --reload 2>&1"),
            'nftables' => (bool) $this->nftAddPort($port, 'drop'),
            'csf' => (bool) ShellService::exec("csf -d {$port} 2>&1"),
            default => false,
        };
    }

    public function blockIp(string $ip): bool
    {
        $ip = escapeshellarg($ip);
        return match ($this->backend) {
            'firewalld' => (bool) ShellService::exec("firewall-cmd --permanent --add-rich-rule='rule family=ipv4 source address={$ip} reject' 2>&1 && firewall-cmd --reload 2>&1"),
            'nftables' => (bool) ShellService::exec("nft add element inet openpanel blacklist {{$ip}} 2>&1"),
            'csf' => (bool) ShellService::exec("csf -d {$ip} 2>&1"),
            default => false,
        };
    }

    public function unblockIp(string $ip): bool
    {
        $ip = escapeshellarg($ip);
        return match ($this->backend) {
            'firewalld' => (bool) ShellService::exec("firewall-cmd --permanent --remove-rich-rule='rule family=ipv4 source address={$ip} reject' 2>&1 && firewall-cmd --reload 2>&1"),
            'nftables' => (bool) ShellService::exec("nft delete element inet openpanel blacklist {{$ip}} 2>&1"),
            'csf' => (bool) ShellService::exec("csf -dr {$ip} 2>&1"),
            default => false,
        };
    }

    public function allowIp(string $ip): bool
    {
        $ip = escapeshellarg($ip);
        return match ($this->backend) {
            'firewalld' => (bool) ShellService::exec("firewall-cmd --permanent --add-rich-rule='rule family=ipv4 source address={$ip} accept' 2>&1 && firewall-cmd --reload 2>&1"),
            'nftables' => (bool) ShellService::exec("nft add element inet openpanel whitelist {{$ip}} 2>&1"),
            'csf' => (bool) ShellService::exec("csf -a {$ip} 2>&1"),
            default => false,
        };
    }

    public function removeAllowIp(string $ip): bool
    {
        $ip = escapeshellarg($ip);
        return match ($this->backend) {
            'firewalld' => (bool) ShellService::exec("firewall-cmd --permanent --remove-rich-rule='rule family=ipv4 source address={$ip} accept' 2>&1 && firewall-cmd --reload 2>&1"),
            'nftables' => (bool) ShellService::exec("nft delete element inet openpanel whitelist {{$ip}} 2>&1"),
            'csf' => (bool) ShellService::exec("csf -ar {$ip} 2>&1"),
            default => false,
        };
    }

    public function listOpenPorts(): array
    {
        return match ($this->backend) {
            'firewalld' => array_filter(explode("\n", trim(ShellService::exec('firewall-cmd --list-ports 2>/dev/null') ?: ''))),
            'nftables' => $this->parseNftPorts(),
            'csf' => $this->parseCsfPorts(),
            default => [],
        };
    }

    public function listBlockedIps(): array
    {
        return match ($this->backend) {
            'firewalld' => $this->parseFirewalldRichRules('reject'),
            'nftables' => array_filter(explode("\n", trim(ShellService::exec('nft list set inet openpanel blacklist 2>/dev/null') ?: ''))),
            'csf' => array_filter(explode("\n", trim(ShellService::exec('csf -g 2>/dev/null | grep DENY') ?: ''))),
            default => [],
        };
    }

    public function listAllowedIps(): array
    {
        return match ($this->backend) {
            'firewalld' => $this->parseFirewalldRichRules('accept'),
            'nftables' => array_filter(explode("\n", trim(ShellService::exec('nft list set inet openpanel whitelist 2>/dev/null') ?: ''))),
            'csf' => array_filter(explode("\n", trim(ShellService::exec('csf -g 2>/dev/null | grep ALLOW') ?: ''))),
            default => [],
        };
    }

    public function start(): bool
    {
        return match ($this->backend) {
            'firewalld' => (bool) ShellService::exec('systemctl start firewalld 2>&1'),
            'nftables' => (bool) ShellService::exec('systemctl start nftables 2>&1'),
            'csf' => (bool) ShellService::exec('csf -e 2>&1'),
            default => false,
        };
    }

    public function stop(): bool
    {
        return match ($this->backend) {
            'firewalld' => (bool) ShellService::exec('systemctl stop firewalld 2>&1'),
            'nftables' => (bool) ShellService::exec('systemctl stop nftables 2>&1'),
            'csf' => (bool) ShellService::exec('csf -x 2>&1'),
            default => false,
        };
    }

    public function restart(): bool
    {
        return match ($this->backend) {
            'firewalld' => (bool) ShellService::exec('systemctl restart firewalld 2>&1'),
            'nftables' => (bool) ShellService::exec('systemctl restart nftables 2>&1'),
            'csf' => (bool) ShellService::exec('csf -r 2>&1'),
            default => false,
        };
    }

    public function getRawRules(): string
    {
        return match ($this->backend) {
            'firewalld' => ShellService::exec('firewall-cmd --list-all 2>/dev/null') ?: 'No rules',
            'nftables' => ShellService::exec('nft list ruleset 2>/dev/null') ?: 'No rules',
            'csf' => ShellService::exec('csf -l 2>/dev/null') ?: 'No rules',
            default => 'No firewall backend active',
        };
    }

    protected function detectBackend(): string
    {
        if (ShellService::exec('which csf 2>/dev/null') && file_exists('/etc/csf/csf.conf')) {
            return 'csf';
        }
        if (ShellService::exec('which firewall-cmd 2>/dev/null')) {
            return 'firewalld';
        }
        if (ShellService::exec('which nft 2>/dev/null')) {
            return 'nftables';
        }
        return 'none';
    }

    protected function nftAddPort(string $port, string $action): string
    {
        $setup = <<<'BASH'
nft add table inet openpanel 2>/dev/null
nft add chain inet openpanel input '{ type filter hook input priority 0; policy accept; }' 2>/dev/null
nft add set inet openpanel blacklist '{ type ipv4_addr; }' 2>/dev/null
nft add set inet openpanel whitelist '{ type ipv4_addr; }' 2>/dev/null
nft add rule inet openpanel input ip saddr @blacklist drop 2>/dev/null
BASH;
        ShellService::exec($setup);
        return ShellService::exec("nft add rule inet openpanel input tcp dport {$port} {$action} 2>&1") ?? '';
    }

    protected function parseNftPorts(): array
    {
        $output = ShellService::exec('nft list chain inet openpanel input 2>/dev/null') ?: '';
        preg_match_all('/dport\s+(\S+)/', $output, $matches);
        return $matches[1] ?? [];
    }

    protected function parseCsfPorts(): array
    {
        $output = ShellService::exec('grep "^TCP_IN" /etc/csf/csf.conf 2>/dev/null') ?: '';
        if (preg_match('/"(.+)"/', $output, $m)) {
            return explode(',', $m[1]);
        }
        return [];
    }

    protected function parseFirewalldRichRules(string $action): array
    {
        $output = ShellService::exec("firewall-cmd --list-rich-rules 2>/dev/null") ?: '';
        $ips = [];
        foreach (explode("\n", $output) as $rule) {
            if (str_contains($rule, $action) && preg_match('/source address="([^"]+)"/', $rule, $m)) {
                $ips[] = $m[1];
            }
        }
        return $ips;
    }
}
