<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class NodejsService
{
    const APPS_PATH = '/usr/local/openpanel/.conf/apps';
    const APP_MANAGER_PATH = '/usr/local/openpanel/.conf/apps_manager/';
    const NVM_CONFIG_PATH = '/usr/local/openpanel/.conf/nvm/';
    const NVM_INSTALL_PATH = '/opt/nvm';
    const ECOSYSTEM_FILE = 'nodejs_ecosystem.config.js';
    const LOG_FILE = '/var/log/openpanel/nodejs.log';

    public static function isInstalled(): bool
    {
        $output = self::runNvmCommand('command -v nvm');
        return !empty(trim($output)) && file_exists(self::NVM_CONFIG_PATH . 'nvm_data.json');
    }

    public static function install(): array
    {
        self::validateDirectories();
        $output = self::runNvmCommand('command -v nvm');
        $nvmInstalled = false;
        if (empty(trim($output))) {
            $release = ShellService::exec('cat /etc/redhat-release');
            $nvmVersion = 'v0.37.2';
            if (preg_match('/release 9/', $release)) {
                $nvmVersion = 'v0.39.7';
            }
            $cmd = 'source ' . self::NVM_CONFIG_PATH . 'nvm_bashrc;curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/' . $nvmVersion . '/install.sh | NVM_DIR="' . self::NVM_INSTALL_PATH . '" bash';
            ShellService::exec($cmd);
            $nvmInstalled = true;
        }
        if (self::installEnvironment()) {
            return ['result' => 'success', 'installed' => true, 'list' => self::listInstalled()];
        }
        if ($nvmInstalled) {
            ShellService::exec('rm -fr ' . self::NVM_INSTALL_PATH);
            ShellService::exec('rm -fr ' . self::NVM_CONFIG_PATH);
        }
        return ['result' => 'error'];
    }

    public static function uninstall(): array
    {
        $apps = self::renderAppList();
        foreach ($apps as $app) {
            self::deleteApp($app->key ?? $app['key']);
        }
        self::runNodeCommand('pm2 unstartup 2>&1');
        self::runNodeCommand('pm2 kill 2>&1');
        ShellService::exec('rm -fr ' . self::NVM_INSTALL_PATH);
        ShellService::exec('rm -fr ' . self::NVM_CONFIG_PATH);
        return ['result' => 'success'];
    }

    public static function listInstalled(): array
    {
        $versions = [];
        $output = self::runNvmCommand('nvm ls');
        $defaultVer = '';
        if (preg_match('/default\s->\s(.*)\s\(/', $output, $m)) {
            $defaultVer = $m[1];
        }
        $lines = preg_grep('/(^|^->)[ \t]+v([5-9]|[1-9]{2,})/', explode("\n", $output));
        $configVersions = self::getNvmDataConfig()->versions ?? [];
        foreach ($lines as $line) {
            if (preg_match('/(^|^->)[ \t]+v([5-9]|[1-9]{2,})\.(.*)/', $line, $m)) {
                $ver = trim($m[2]) . '.' . trim(str_replace('*', '', $m[3]));
                if (self::getInstalledNodeVersion() != $ver) {
                    $configData = self::getConfigVersionData($ver, $configVersions);
                    $versions[] = [
                        'ver' => $ver,
                        'ver_name' => $configData->name ?? $ver,
                        'qty' => $configData->apps ?? 0,
                        'default' => $defaultVer == $ver,
                        'path' => $configData->path ?? '',
                    ];
                }
            }
        }
        return $versions;
    }

    public static function listAvailable(): string
    {
        return self::runNvmCommand('nvm ls-remote 2>&1');
    }

    public static function installVersion(string $version): string
    {
        return self::runNvmCommand('nvm install ' . $version . ' --latest-npm 2>&1');
    }

    public static function uninstallVersion(string $version): string
    {
        return self::runNvmCommand('nvm uninstall ' . $version . ' 2>&1');
    }

    public static function setDefault(string $version): array
    {
        $response = self::runNvmCommand('nvm alias default ' . $version . ' 2>&1');
        return ['result' => 'success', 'output' => $response];
    }

    public static function listApps(): array
    {
        return ['result' => 'success', 'domains' => self::listDomains(), 'users' => self::listUsers(), 'list' => self::renderAppList()];
    }

    public static function saveApp(array $appData, string $type = 'new', ?string $keyId = null): array
    {
        if (!is_dir(self::APP_MANAGER_PATH . 'logs/')) {
            @mkdir(self::APP_MANAGER_PATH . 'logs/', 0755, true);
        }
        $app = json_decode(json_encode($appData));
        if (!$app || !isset($app->path) || !is_dir($app->path)) {
            return ['result' => 'error', 'code' => 1];
        }
        if (!self::isVersionInstalled($app->version)) {
            return ['result' => 'error', 'code' => 2];
        }
        if ($type === 'new') {
            self::persistApp($app);
            self::insertToEcosystem($app);
        } else {
            $app = self::updateAppRecord($keyId, $app);
            if ($app === false) return ['result' => 'error', 'code' => 4];
            self::updateInEcosystem($app);
        }
        return ['result' => 'success', 'list' => self::renderAppList()];
    }

    public static function deleteApp(string $keyId): array
    {
        $app = self::removeAppRecord($keyId);
        if (!$app) return ['result' => 'error', 'code' => 1];
        self::removeFromEcosystem($app);
        return ['result' => 'success'];
    }

    public static function getAppInfo(string $keyId): array
    {
        $info = self::getAppInformation($keyId);
        if (!$info) return ['result' => 'error', 'code' => 1];
        $ecosystemName = self::getEcosystemName($info->app_key, $info->key);
        $status = self::getAppEcosystemStatus($ecosystemName);
        $info->status = $status->status;
        return ['result' => 'success', 'info' => $info];
    }

    public static function handleStatus(string $action, string $appName): string
    {
        $ecosystemFile = self::APP_MANAGER_PATH . self::ECOSYSTEM_FILE;
        if ($action === 'stop') {
            return self::runNodeCommand('pm2 stop ' . $ecosystemFile . ' --only ' . $appName . ' 2>&1');
        }
        if ($action === 'start') {
            self::runNodeCommand('pm2 delete ' . $appName . ' 2>&1');
            return self::runNodeCommand('pm2 start ' . $ecosystemFile . ' --only ' . $appName . ' 2>&1');
        }
        if ($action === 'restart') {
            self::runNodeCommand('pm2 stop ' . $ecosystemFile . ' --only ' . $appName . ' 2>&1');
            self::runNodeCommand('pm2 delete ' . $appName . ' 2>&1');
            return self::runNodeCommand('pm2 start ' . $ecosystemFile . ' --only ' . $appName . ' 2>&1');
        }
        return '';
    }

    public static function npmInstall(string $keyId): array
    {
        $app = self::getAppInformation($keyId);
        if (!$app || !is_dir($app->path)) return ['result' => 'error', 'code' => 1];
        $ecosystemName = self::getEcosystemName($app->app_key, $app->key);
        self::runCommandInApp($app, 'npm install > ' . self::APP_MANAGER_PATH . 'logs/' . $ecosystemName . '_npm_install.log 2>&1 & echo $!');
        return ['result' => 'success'];
    }

    public static function npmCommand(string $keyId, string $command): array
    {
        $app = self::getAppInformation($keyId);
        if (!$app || !is_dir($app->path)) return ['result' => 'error', 'code' => 1];
        $ecosystemName = self::getEcosystemName($app->app_key, $app->key);
        self::runCommandInApp($app, $command . ' > ' . self::APP_MANAGER_PATH . 'logs/' . $ecosystemName . '_npm_command.log 2>&1 & echo $!');
        return ['result' => 'success'];
    }

    public static function getAppLog(string $keyId, int $lines = 20): array
    {
        $app = self::getAppInformation($keyId);
        if (!$app) return ['result' => 'error', 'code' => 1];
        $logFile = '/opt/nvm/.pm2/logs/' . str_replace('_', '-', self::getEcosystemName($app->app_key, $app->key)) . '-out.log';
        $log = file_exists($logFile) ? ShellService::exec("tail -{$lines} " . escapeshellarg($logFile)) : '';
        return ['result' => 'success', 'log' => explode("\n", $log)];
    }

    public static function getNpmInstallLog(string $keyId): array
    {
        $app = self::getAppInformation($keyId);
        if (!$app) return ['result' => 'error', 'code' => 1];
        $logFile = self::APP_MANAGER_PATH . 'logs/' . self::getEcosystemName($app->app_key, $app->key) . '_npm_install.log';
        $lines = [];
        if (file_exists($logFile)) {
            $content = file_get_contents($logFile);
            file_put_contents($logFile, '');
            $lines = explode("\n", $content);
        }
        return ['result' => 'success', 'lines' => $lines];
    }

    public static function getUserConfig(): object
    {
        $configFile = self::APP_MANAGER_PATH . 'nodejs_user_config';
        if (!is_dir(self::APP_MANAGER_PATH)) @mkdir(self::APP_MANAGER_PATH, 0755, true);
        if (!file_exists($configFile)) {
            file_put_contents($configFile, json_encode(['node_config' => ['user_enabled' => false, 'port_range' => [50000, 60000]]]));
        }
        $data = json_decode(file_get_contents($configFile));
        return is_object($data) ? $data : (object) ['node_config' => (object) ['user_enabled' => false, 'port_range' => [50000, 60000]]];
    }

    public static function saveUserConfig(array $config): array
    {
        file_put_contents(self::APP_MANAGER_PATH . 'nodejs_user_config', json_encode(['node_config' => $config]));
        return ['status' => 'success'];
    }

    private static function runNvmCommand(string $command): string
    {
        return ShellService::exec('source ' . self::NVM_CONFIG_PATH . 'nvm_bashrc;' . $command . ' 2>/dev/null');
    }

    private static function runNodeCommand(string $command): string
    {
        return ShellService::exec('source ' . self::NVM_CONFIG_PATH . 'nvm_bashrc;export PM2_HOME=' . self::NVM_INSTALL_PATH . '/.pm2;' . $command);
    }

    private static function runCommandInApp(object $app, string $command): string
    {
        $cdCmd = 'cd ' . str_replace(' ', '\\ ', $app->path);
        return self::runNvmCommand($cdCmd . ';nvm exec ' . $app->version . ' ' . $command);
    }

    private static function getInstalledNodeVersion(): string
    {
        $release = ShellService::exec('cat /etc/redhat-release');
        if (preg_match('/release 9/', $release)) return '18.17.1';
        if (preg_match('/release 8/', $release)) return '16.20.2';
        return '14.15.3';
    }

    private static function isVersionInstalled(string $version): bool
    {
        $output = self::runNvmCommand('nvm ls');
        return preg_match('/v' . preg_quote($version, '/') . '/', $output);
    }

    private static function validateDirectories(): void
    {
        if (!is_dir(self::NVM_INSTALL_PATH)) @mkdir(self::NVM_INSTALL_PATH, 0755, true);
        if (!is_dir(self::NVM_CONFIG_PATH)) {
            @mkdir(self::NVM_CONFIG_PATH, 0755, true);
            $bashrc = "\nif [ -f /etc/bashrc ]; then\n    . /etc/bashrc\nfi\nexport NVM_DIR=\"/opt/nvm\"\n[ -s \"\$NVM_DIR/nvm.sh\" ] && \\. \"\$NVM_DIR/nvm.sh\"\n[ -s \"\$NVM_DIR/bash_completion\" ] && \\. \"\$NVM_DIR/bash_completion\"\nexport PM2_HOME=/opt/nvm/.pm2\n";
            file_put_contents(self::NVM_CONFIG_PATH . 'nvm_bashrc', $bashrc);
            self::saveNvmConfigFile(['versions' => []]);
        } elseif (!file_exists(self::NVM_CONFIG_PATH . 'nvm_data.json')) {
            self::saveNvmConfigFile(['versions' => []]);
        }
    }

    private static function installEnvironment(): bool
    {
        $baseVer = self::getInstalledNodeVersion();
        $output = self::runNvmCommand('nvm install ' . $baseVer . ' --latest-npm 2>&1');
        if (preg_match('/Now using node/', $output) || preg_match('/Creating default alias/', $output)) {
            self::runNodeCommand('npm install pm2@latest -g 2>&1');
            $startup = self::runNodeCommand('pm2 startup 2>&1');
            if (preg_match('/(sudo env PATH=.*)/', $startup, $m)) {
                $cmd = str_replace('sudo ', '', $m[1]);
                ShellService::exec('source ' . self::NVM_CONFIG_PATH . 'nvm_bashrc;' . $cmd . ' 2>&1');
            }
            self::runNodeCommand('pm2 save 2>&1');
            return true;
        }
        return false;
    }

    private static function getNvmDataConfig(): object
    {
        $file = self::NVM_CONFIG_PATH . 'nvm_data.json';
        if (file_exists($file) && $data = json_decode(file_get_contents($file))) return $data;
        $data = (object) ['versions' => []];
        self::saveNvmConfigFile(['versions' => []]);
        return $data;
    }

    private static function saveNvmConfigFile(array $data): void
    {
        file_put_contents(self::NVM_CONFIG_PATH . 'nvm_data.json', json_encode($data, JSON_PRETTY_PRINT));
    }

    private static function getConfigVersionData(string $version, array $versions): object
    {
        $index = array_search($version, array_column($versions, 'version'));
        if ($index !== false) return (object) $versions[$index];
        $data = (object) ['version' => $version, 'name' => $version, 'path' => trim(self::runNvmCommand('nvm which ' . $version)), 'apps' => 0];
        return $data;
    }

    private static function persistApp(object $app): void
    {
        $db = self::getAppsDb();
        $db->app_db_index++;
        $app->key = $db->app_db_index;
        $app->app_key = uniqid();
        $db->apps_db[] = $app;
        self::saveAppsDb($db);
    }

    private static function updateAppRecord(?string $id, object $appData): object|false
    {
        $db = self::getAppsDb(true);
        $appData->key = $id;
        $index = array_search($id, array_column($db['apps_db'] ?? [], 'key'));
        if ($index !== false) {
            $appData->app_key = $db['apps_db'][$index]['app_key'] ?? $db['apps_db'][$index]['app_key'] ?? uniqid();
            $appData->old_url = !empty($db['apps_db'][$index]['url']) && ($db['apps_db'][$index]['url'] ?? '') !== ($appData->url ?? '') ? $db['apps_db'][$index]['url'] : false;
            $db['apps_db'][$index] = $appData;
            self::saveAppsDb($db);
            return $appData;
        }
        return false;
    }

    private static function removeAppRecord(string $id): object|false
    {
        $db = self::getAppsDb(true);
        $index = array_search($id, array_column($db['apps_db'] ?? [], 'key'));
        if ($index !== false) {
            $app = (object) $db['apps_db'][$index];
            array_splice($db['apps_db'], $index, 1);
            self::saveAppsDb($db);
            return $app;
        }
        return false;
    }

    private static function getAppInformation(string $key): object|false
    {
        $db = self::getAppsDb(true);
        $index = array_search($key, array_column($db['apps_db'] ?? [], 'key'));
        return $index !== false ? (object) $db['apps_db'][$index] : false;
    }

    private static function getAppsDb(bool $asArray = false): object|array
    {
        $file = self::APP_MANAGER_PATH . 'apps_manager_core';
        if (!is_dir(self::APP_MANAGER_PATH)) @mkdir(self::APP_MANAGER_PATH, 0755, true);
        if (!file_exists($file)) self::genAppsDb();
        $data = json_decode(file_get_contents($file), $asArray);
        if (!$data) { self::genAppsDb(); $data = json_decode(file_get_contents($file), $asArray); }
        return $data;
    }

    private static function saveAppsDb(object $data): void
    {
        file_put_contents(self::APP_MANAGER_PATH . 'apps_manager_core', json_encode($data, JSON_PRETTY_PRINT));
    }

    private static function genAppsDb(): void
    {
        self::saveAppsDb((object) ['app_db_index' => 0, 'apps_db' => []]);
    }

    private static function getEcosystemName(string $appKey, string $key): string
    {
        return 'OP_' . $appKey . '_' . $key;
    }

    private static function insertToEcosystem(object $app): void
    {
        $eco = self::getEcosystemObject();
        if ($eco) {
            $eco->apps[] = self::generateEcosystemEntry($app);
            self::saveEcosystemFile($eco);
        }
    }

    private static function updateInEcosystem(object $app): void
    {
        $name = self::getEcosystemName($app->app_key, $app->key);
        $eco = self::getEcosystemObject();
        if ($eco) {
            $index = array_search($name, array_column($eco->apps, 'name'));
            $entry = self::generateEcosystemEntry($app);
            if ($index !== false) {
                $eco->apps[$index] = $entry;
            } else {
                $eco->apps[] = $entry;
            }
            self::saveEcosystemFile($eco);
        }
    }

    private static function removeFromEcosystem(object $app): void
    {
        $name = self::getEcosystemName($app->app_key, $app->key);
        $status = self::getAppEcosystemStatus($name);
        if ($status->status === 'running') {
            self::handleStatus('stop', $name);
        }
        if ($status->in_console) {
            self::runNodeCommand('pm2 delete ' . $name . ' 2>&1');
        }
        $eco = self::getEcosystemObject();
        if ($eco) {
            $index = array_search($name, array_column($eco->apps, 'name'));
            if ($index !== false) {
                array_splice($eco->apps, $index, 1);
                self::saveEcosystemFile($eco);
            }
        }
    }

    private static function generateEcosystemEntry(object $app): array
    {
        $name = self::getEcosystemName($app->app_key, $app->key);
        $env = ['NODE_ENV' => $app->mode ?? 'development'];
        if (!empty($app->url) && !empty($app->extra_info->port)) {
            $env['PORT'] = $app->extra_info->port;
        }
        if (!empty($app->extra_info->env_vars)) {
            foreach ($app->extra_info->env_vars as $var) {
                $env[$var->key] = $var->value;
            }
        }
        return [
            'name' => $name,
            'script' => ($app->path ?? '') . ($app->extra_info->startup_file ?? 'app.js'),
            'env' => $env,
            'cwd' => $app->path ?? '',
            'user' => $app->user ?? 'root',
            'interpreter_args' => '--harmony',
            'log_file' => '/dev/null',
            'time' => true,
            'interpreter' => trim(self::runNvmCommand('nvm which ' . ($app->version ?? '18'))),
        ];
    }

    private static function getAppEcosystemStatus(string $appName, ?string $statusRaw = null): object
    {
        $result = (object) ['status' => 'stopped', 'pid' => 0, 'cpu' => 0, 'mem' => 0, 'in_console' => false];
        if ($statusRaw === null) $statusRaw = self::runNodeCommand('pm2 status 2>&1');
        $lines = preg_grep('/' . preg_quote($appName, '/') . '(.*)$/m', explode("\n", $statusRaw));
        if ($lines) {
            $line = current($lines);
            $parts = explode('│', $line);
            if (isset($parts[9])) {
                $status = trim(preg_replace('/\x1b\[[0-9;]*m/', '', $parts[9]));
                $result->status = $status === 'online' ? 'running' : $status;
                $result->in_console = true;
            }
        }
        return $result;
    }

    private static function getEcosystemObject(?string $appName = null): object|false
    {
        $file = self::APP_MANAGER_PATH . self::ECOSYSTEM_FILE;
        if (!file_exists($file)) file_put_contents($file, 'module.exports = {"apps" : []}');
        $content = file_get_contents($file);
        if (preg_match('/{(.*)}/s', $content, $m)) {
            $data = json_decode('{' . $m[1] . '}');
            if ($appName === null) return $data;
            $index = array_search($appName, array_column($data->apps ?? [], 'name'));
            return $index !== false ? $data->apps[$index] : false;
        }
        return false;
    }

    private static function saveEcosystemFile(object $data): void
    {
        file_put_contents(self::APP_MANAGER_PATH . self::ECOSYSTEM_FILE, 'module.exports = ' . json_encode($data, JSON_PRETTY_PRINT));
    }

    private static function renderAppList(): array
    {
        $statusRaw = self::runNodeCommand('pm2 status 2>&1');
        $apps = self::getAppsDb()->apps_db ?? [];
        $result = [];
        foreach ($apps as $app) {
            $name = self::getEcosystemName($app->app_key, $app->key);
            $status = self::getAppEcosystemStatus($name, $statusRaw);
            $app->status = $status->status;
            $result[] = $app;
        }
        return $result;
    }

    private static function listUsers(): array
    {
        return DB::table('user')->pluck('username')->toArray();
    }

    private static function listDomains(): array
    {
        $domains = [];
        $users = DB::table('user')->select('domain', 'username')->get();
        foreach ($users as $user) {
            $domains[] = (object) ['domain' => $user->domain, 'domain_label' => $user->domain, 'sub' => null, 'user' => $user->username];
            $subs = DB::table('subdomains')->where('domain', $user->domain)->get();
            foreach ($subs as $sub) {
                $domains[] = (object) ['domain' => $sub->domain, 'domain_label' => $sub->subdomain . '.' . $sub->domain, 'sub' => $sub->subdomain, 'user' => $user->username];
            }
        }
        $addonDomains = DB::table('domains')->select('domain', 'user')->get();
        foreach ($addonDomains as $d) {
            $domains[] = (object) ['domain' => $d->domain, 'domain_label' => $d->domain, 'sub' => null, 'user' => $d->user];
        }
        return $domains;
    }
}
