<?php

namespace App\Services;

use App\Models\WordPressSite;
use App\Models\WordPressTask;
use App\Models\WordPressBackup;
use App\Models\WordPressSecurityScan;
use App\Models\ActivityLog;
use App\Models\UserAccount;
use App\Models\Domain;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Services\ShellService;

class WordPressService
{
    protected WebStackService $stackService;
    protected string $homeBase = '/home';
    protected string $backupBase = '/usr/local/openpanel/backups/wordpress';
    protected string $wpCliPath = '/usr/local/bin/wp';

    public function __construct(?WebStackService $stackService = null)
    {
        $this->stackService = $stackService ?? new WebStackService();
    }

    public function runWpCli(string $path, string $command, string $asUser = '', int $timeout = 120): array
    {
        // Strip --allow-root if caller still passes it (legacy compat)
        $command = str_replace('--allow-root', '', $command);
        $command = trim(preg_replace('/\s+/', ' ', $command));

        $wpCmd = "php -d memory_limit=512M {$this->wpCliPath} --path={$path} {$command} 2>&1";

        // Run as site owner (preferred) or root (system-level fallback)
        if ($asUser) {
            return ShellService::runAsUser($asUser, $wpCmd, $timeout, $path);
        }

        $result = Process::timeout($timeout)->run("HOME=/root {$wpCmd}");
        return [
            'success' => $result->successful(),
            'output' => $result->output(),
            'error' => $result->errorOutput(),
            'exit_code' => $result->exitCode(),
        ];
    }

    public function isWpCliInstalled(): bool
    {
        $result = Process::run("test -x {$this->wpCliPath} && {$this->wpCliPath} --version --allow-root 2>&1");
        return $result->successful() && str_contains($result->output(), 'WP-CLI');
    }

    public function detectWordPress(string $path, string $username = ''): ?array
    {
        if (!$username && preg_match('#^/home/([^/]+)/#', $path, $m)) {
            $username = $m[1];
        }

        if ($username) {
            $result = ShellService::runAsUser($username, "test -f {$path}/wp-config.php && echo EXISTS", 10, $path);
            $exists = str_contains($result['output'] ?? '', 'EXISTS');
        } else {
            $result = Process::run("test -f {$path}/wp-config.php && echo EXISTS");
            $exists = str_contains($result->output(), 'EXISTS');
        }
        if (!$exists) {
            return null;
        }

        $version = $this->runWpCli($path, 'core version', $username);
        $url = $this->runWpCli($path, 'option get siteurl', $username);

        if (!$version['success']) {
            return null;
        }

        return [
            'wp_version' => trim($version['output']),
            'site_url' => trim($url['output']),
            'path' => $path,
        ];
    }

    public function listWordPressSites(?int $userAccountId = null): \Illuminate\Support\Collection
    {
        $query = WordPressSite::with(['userAccount', 'domain', 'backups', 'securityScans']);
        if ($userAccountId) {
            $query->where('user_account_id', $userAccountId);
        }
        return $query->orderByDesc('created_at')->get();
    }

    public function installWordPress(array $params): array
    {
        $required = ['user_account_id', 'domain', 'site_title', 'admin_user', 'admin_password', 'admin_email'];
        foreach ($required as $field) {
            if (empty($params[$field])) {
                return ['success' => false, 'message' => "Missing required field: {$field}"];
            }
        }

        $account = UserAccount::find($params['user_account_id']);
        if (!$account) {
            $account = DB::connection('mysql')->table('accounts')->where('id', $params['user_account_id'])->first();
        }
        if (!$account) {
            return ['success' => false, 'message' => 'Account not found.'];
        }

        $domain = Domain::where('domain', $params['domain'])->first();

        $username = $account->username ?? '';
        if (!$username) {
            return ['success' => false, 'message' => 'Cannot determine username for account.'];
        }

        $installPath = $params['install_path'] ?? "/home/{$username}/public_html";
        $phpVersion = $params['php_version'] ?? '8.2';
        $stack = $this->stackService->getActiveStack();

        $existing = WordPressSite::where('install_path', $installPath)->first();
        if ($existing) {
            return ['success' => false, 'message' => 'WordPress already installed at this path.'];
        }

        $dbName = $this->generateDbName($username);
        $dbUser = $this->generateDbUser($username);
        $dbPass = Str::random(24);

        $task = WordPressTask::create([
            'type' => 'install',
            'status' => 'running',
            'started_at' => now(),
            'created_by' => $username,
        ]);

        try {
            $this->createMysqlDatabase($dbName);
            $this->createMysqlUser($dbUser, $dbPass);
            $this->grantMysqlPrivileges($dbUser, $dbName);

            ShellService::runAsUser($username, "mkdir -p " . escapeshellarg($installPath), 120, "/home/{$username}");

            $download = $this->runWpCli($installPath, "core download --force", $username);
            if (!$download['success']) {
                throw new \RuntimeException("WP core download failed: {$download['error']}");
            }

            $wpConfig = $this->generateWpConfig($installPath, $dbName, $dbUser, $dbPass, $username, $params);
            if (!$wpConfig['success']) {
                throw new \RuntimeException("wp-config.php generation failed: {$wpConfig['error']}");
            }

            $siteUrl = !empty($params['ssl_enabled']) ? "https://{$params['domain']}" : "http://{$params['domain']}";
            $installCmd = sprintf(
                'core install --url=%s --title=%s --admin_user=%s --admin_password=%s --admin_email=%s --skip-email',
                escapeshellarg($siteUrl),
                escapeshellarg($params['site_title']),
                escapeshellarg($params['admin_user']),
                escapeshellarg($params['admin_password']),
                escapeshellarg($params['admin_email'])
            );
            $install = $this->runWpCli($installPath, $installCmd, $username);
            if (!$install['success']) {
                $errMsg = $install['error'] ?: $install['output'] ?: 'exit code ' . ($install['exit_code'] ?? '?');
                throw new \RuntimeException("WP install failed: " . substr($errMsg, 0, 300));
            }

            $this->setFileOwnership($installPath, $username);
            $this->setSafePermissions($installPath, $username);

            $wpVersion = trim($this->runWpCli($installPath, 'core version', $username)['output'] ?? '');

            $site = WordPressSite::create([
                'user_account_id' => $account->id,
                'domain_id' => $domain?->id,
                'domain' => $params['domain'],
                'install_path' => $installPath,
                'site_url' => $siteUrl,
                'admin_user' => $params['admin_user'],
                'admin_email' => $params['admin_email'],
                'db_name' => $dbName,
                'db_user' => $dbUser,
                'db_password_encrypted' => $dbPass,
                'wp_version' => $wpVersion,
                'php_version' => $phpVersion,
                'stack_name' => $stack,
                'ssl_enabled' => !empty($params['ssl_enabled']),
                'status' => 'active',
            ]);

            $task->update([
                'wordpress_site_id' => $site->id,
                'status' => 'success',
                'output' => $install['output'],
                'finished_at' => now(),
            ]);

            if (!empty($params['enable_redis'])) {
                $this->enableRedis($site);
            }

            if (!empty($params['ssl_enabled'])) {
                $this->enableSsl($site);
            }

            $stack = $this->stackService->getActiveStack();
            if (str_contains($stack, 'varnish')) {
                $this->configureVarnishRules($site);
                $site->update(['varnish_enabled' => true]);
            }

            if (!empty($params['install_redis_plugin'])) {
                $this->installPlugin($site, 'redis-cache');
            }

            Log::info("WordPress installed: {$params['domain']} for user {$username}");
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'wordpress_installed',
                'module' => 'wordpress',
                'description' => "WordPress installed: {$params['domain']}",
                'ip_address' => request()?->ip(),
                'metadata' => ['domain' => $params['domain'], 'site_id' => $site->id],
            ]);

            return [
                'success' => true,
                'message' => "WordPress installed successfully at {$params['domain']}",
                'site' => $site,
                'login_url' => "{$siteUrl}/wp-admin",
            ];
        } catch (\Throwable $e) {
            $task->update([
                'status' => 'failed',
                'output' => $e->getMessage(),
                'finished_at' => now(),
            ]);

            $this->cleanupFailedInstall($dbName, $dbUser, $installPath);

            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function generateWpConfig(string $path, string $dbName, string $dbUser, string $dbPass, string $username, array $extra = []): array
    {
        $tablePrefix = $extra['table_prefix'] ?? 'wp_';
        $redisPrefix = $extra['redis_prefix'] ?? '';

        $salts = $this->fetchSalts();

        $config = "<?php\n";
        $config .= "define('DB_NAME', " . var_export($dbName, true) . ");\n";
        $config .= "define('DB_USER', " . var_export($dbUser, true) . ");\n";
        $config .= "define('DB_PASSWORD', " . var_export($dbPass, true) . ");\n";
        $config .= "define('DB_HOST', 'localhost');\n";
        $config .= "define('DB_CHARSET', 'utf8mb4');\n";
        $config .= "define('DB_COLLATE', '');\n\n";
        $config .= $salts . "\n\n";
        $config .= "\$table_prefix = " . var_export($tablePrefix, true) . ";\n\n";

        if ($redisPrefix) {
            $config .= "define('WP_REDIS_PREFIX', " . var_export($redisPrefix, true) . ");\n";
            $config .= "define('WP_REDIS_HOST', '127.0.0.1');\n";
            $config .= "define('WP_REDIS_PORT', 6379);\n";
            $config .= "define('WP_REDIS_DATABASE', 0);\n\n";
        }

        $config .= "define('DISALLOW_FILE_EDIT', true);\n";
        $config .= "define('WP_DEBUG', false);\n";
        $config .= "define('WP_MEMORY_LIMIT', '256M');\n";
        $config .= "define('WP_MAX_MEMORY_LIMIT', '512M');\n\n";

        $config .= "if ( ! defined( 'ABSPATH' ) ) {\n";
        $config .= "    define( 'ABSPATH', __DIR__ . '/' );\n";
        $config .= "}\n";
        $config .= "require_once ABSPATH . 'wp-settings.php';\n";

        $configPath = "{$path}/wp-config.php";
        $tempFile = tempnam(sys_get_temp_dir(), 'wpconfig');
        file_put_contents($tempFile, $config);
        chmod($tempFile, 0644);

        $result = ShellService::runAsUser($username, "cp " . escapeshellarg($tempFile) . " " . escapeshellarg($configPath) . " && chmod 640 " . escapeshellarg($configPath), 10, $path);
        @unlink($tempFile);

        return [
            'success' => $result['success'],
            'output' => $result['output'] ?? '',
            'error' => $result['error'] ?? '',
        ];
    }

    public function updateCore(WordPressSite $site): array
    {
        $task = $this->createTask($site, 'update');

        try {
            $this->enableMaintenanceMode($site);
            $this->backupSite($site, 'db');

            $result = $this->runWpCli($site->install_path, 'core update', $this->getUsername($site));

            if ($result['success']) {
                $version = trim($this->runWpCli($site->install_path, 'core version', $this->getUsername($site))['output']);
                $site->update(['wp_version' => $version]);
                $this->purgeAfterUpdate($site);
            }

            $this->disableMaintenanceMode($site);

            return $this->finishTask($task, $result);
        } catch (\Throwable $e) {
            $this->disableMaintenanceMode($site);
            return $this->failTask($task, $e->getMessage());
        }
    }

    public function updatePlugins(WordPressSite $site, ?string $plugin = null): array
    {
        $task = $this->createTask($site, 'update');
        $cmd = $plugin ? "plugin update " . escapeshellarg($plugin) : "plugin update --all";
        $result = $this->runWpCli($site->install_path, $cmd, $this->getUsername($site));
        if ($result['success']) $this->purgeAfterUpdate($site);
        return $this->finishTask($task, $result);
    }

    public function updateThemes(WordPressSite $site, ?string $theme = null): array
    {
        $task = $this->createTask($site, 'update');
        $cmd = $theme ? "theme update " . escapeshellarg($theme) : "theme update --all";
        $result = $this->runWpCli($site->install_path, $cmd, $this->getUsername($site));
        if ($result['success']) $this->purgeAfterUpdate($site);
        return $this->finishTask($task, $result);
    }

    public function getUpdates(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $core = $this->runWpCli($site->install_path, 'core check-update --format=json', $username);
        $plugins = $this->runWpCli($site->install_path, 'plugin list --update=available --format=json', $username);
        $themes = $this->runWpCli($site->install_path, 'theme list --update=available --format=json', $username);

        return [
            'core' => $core['success'] ? json_decode($core['output'], true) : [],
            'plugins' => $plugins['success'] ? json_decode($plugins['output'], true) : [],
            'themes' => $themes['success'] ? json_decode($themes['output'], true) : [],
        ];
    }

    public function backupSite(WordPressSite $site, string $type = 'full'): array
    {
        $task = $this->createTask($site, 'backup');
        $dumpPath = null;
        $username = '';

        try {
            if (!in_array($type, ['full', 'db', 'files'], true)) {
                throw new \InvalidArgumentException('Invalid WordPress backup type.');
            }

            $backupDir = "{$this->backupBase}/{$site->id}";
            $backupDirArg = escapeshellarg($backupDir);
            $mkdir = Process::timeout(10)->run("sudo mkdir -p {$backupDirArg} && sudo chown root:nginx {$backupDirArg} && sudo chmod 0750 {$backupDirArg}");
            if (!$mkdir->successful()) {
                throw new \RuntimeException('Unable to create WordPress backup directory.');
            }

            $timestamp = date('Y-m-d_H-i-s');
            $backupFile = "{$backupDir}/backup_{$type}_{$timestamp}.tar.gz";
            $backupFileArg = escapeshellarg($backupFile);
            $username = $this->getUsername($site);

            if ($type === 'full') {
                $cmd = "sudo tar -czf {$backupFileArg} -C " . escapeshellarg(dirname($site->install_path)) . " " . escapeshellarg(basename($site->install_path));
            } elseif ($type === 'db') {
                $dumpDir = "/home/{$username}/.openpanel/tmp";
                ShellService::runAsUser($username, "mkdir -p " . escapeshellarg($dumpDir) . " && chmod 700 " . escapeshellarg($dumpDir), 10, "/home/{$username}");
                $dumpPath = "{$dumpDir}/db_{$site->id}_{$timestamp}.sql";
                $export = $this->runWpCli($site->install_path, "db export " . escapeshellarg($dumpPath), $username, 300);
                if (!$export['success']) {
                    throw new \RuntimeException('Database export failed.');
                }
                $cmd = "sudo tar -czf {$backupFileArg} -C " . escapeshellarg($dumpDir) . " " . escapeshellarg(basename($dumpPath));
            } else {
                $cmd = "sudo tar -czf {$backupFileArg} -C " . escapeshellarg($site->install_path) . " wp-content";
            }

            $result = Process::timeout(300)->run($cmd);

            if ($dumpPath) {
                ShellService::runAsUser($username, "rm -f " . escapeshellarg($dumpPath), 10, "/home/{$username}");
            }

            $size = 0;
            if ($result->successful()) {
                Process::timeout(10)->run("sudo chown root:nginx {$backupFileArg} && sudo chmod 0640 {$backupFileArg}");
                $sizeResult = Process::run("stat -c%s {$backupFileArg} 2>/dev/null || echo 0");
                $size = (int) trim($sizeResult->output());
            }

            $completed = $result->successful() && $size > 0;

            $backup = WordPressBackup::create([
                'wordpress_site_id' => $site->id,
                'backup_path' => $backupFile,
                'backup_type' => $type,
                'size_bytes' => $size,
                'status' => $completed ? 'completed' : 'failed',
            ]);

            $site->update(['last_backup_at' => now()]);

            return $this->finishTask($task, [
                'success' => $completed,
                'output' => "Backup saved: {$backupFile} (" . $this->formatBytes($size) . ")",
                'error' => $result->errorOutput(),
            ]);
        } catch (\Throwable $e) {
            if ($dumpPath && $username) {
                ShellService::runAsUser($username, "rm -f " . escapeshellarg($dumpPath), 10, "/home/{$username}");
            }
            return $this->failTask($task, $e->getMessage());
        }
    }

    public function restoreSite(WordPressSite $site, WordPressBackup $backup): array
    {
        $task = $this->createTask($site, 'restore');

        try {
            if (!file_exists($backup->backup_path) && !Process::run("test -f {$backup->backup_path}")->successful()) {
                throw new \RuntimeException("Backup file not found: {$backup->backup_path}");
            }

            $preBackup = $this->backupSite($site, 'full');

            if ($backup->backup_type === 'db') {
                $tmpDir = "/tmp/wp_restore_{$site->id}_" . time();
                Process::timeout(60)->run("mkdir -p {$tmpDir}");
                Process::timeout(60)->run("tar -xzf {$backup->backup_path} -C {$tmpDir}");

                $sqlFile = Process::run("ls {$tmpDir}/*.sql 2>/dev/null | head -1")->output();
                if (empty(trim($sqlFile))) {
                    throw new \RuntimeException('No SQL file found in backup.');
                }

                $result = $this->runWpCli($site->install_path, 'db import ' . trim($sqlFile), $this->getUsername($site));
                Process::timeout(30)->run("rm -rf {$tmpDir}");
            } else {
                $username = $this->getUsername($site);
                $preRestoreBackup = "{$site->install_path}_pre_restore_" . time();
                ShellService::runAsUser($username, "cp -a " . escapeshellarg($site->install_path) . " " . escapeshellarg($preRestoreBackup), 120, "/home/{$username}");

                ShellService::runAsUser($username, "tar -xzf " . escapeshellarg($backup->backup_path) . " -C " . escapeshellarg(dirname($site->install_path)), 300, "/home/{$username}");
                $this->setFileOwnership($site->install_path, $username);
                $this->setSafePermissions($site->install_path, $username);

                $result = ['success' => true, 'output' => 'Files restored.', 'error' => ''];
            }

            Log::info("WordPress restored: {$site->domain} from backup {$backup->id}");
            ActivityLog::create([
                'user_id' => auth()->id(),
                'action' => 'wordpress_restored',
                'module' => 'wordpress',
                'description' => "WordPress restored: {$site->domain} from backup {$backup->id}",
                'ip_address' => request()?->ip(),
                'metadata' => ['site_id' => $site->id, 'backup_id' => $backup->id],
            ]);

            $this->purgeAfterUpdate($site);
            return $this->finishTask($task, $result);
        } catch (\Throwable $e) {
            return $this->failTask($task, $e->getMessage());
        }
    }

    public function cloneSite(WordPressSite $site, string $targetDomain, string $targetPath = ''): array
    {
        $task = $this->createTask($site, 'clone');
        $cloneDbName = null;
        $cloneDbUser = null;
        $targetPathForCleanup = null;
        $dumpPath = "/tmp/wp_clone_db_{$site->id}_" . time() . ".sql";

        try {
            $username = $this->getUsername($site);
            if (!$targetPath) {
                $targetPath = "/home/{$username}/public_html_staging";
            }
            $targetPathForCleanup = $targetPath;

            if (!$this->pathInsideHome($targetPath, $username)) {
                throw new \RuntimeException('Target path must be inside the account home.');
            }
            if (WordPressSite::where('domain', $targetDomain)->exists()) {
                throw new \RuntimeException("WordPress site record already exists for {$targetDomain}.");
            }

            $cloneDbName = $this->generateDbName($username, '_staging');
            $cloneDbUser = $this->generateUniqueDbUser($username, '_stg');
            $cloneDbPass = Str::random(24);

            ShellService::runAsUser($username, "mkdir -p " . escapeshellarg($targetPath), 10, "/home/{$username}");
            $copyResult = ShellService::runAsUser($username, "cp -a " . escapeshellarg($site->install_path) . "/. " . escapeshellarg($targetPath) . "/", 300, "/home/{$username}");
            if (!$copyResult['success']) {
                throw new \RuntimeException("File copy failed: " . ($copyResult['error'] ?? ''));
            }

            $this->createMysqlDatabase($cloneDbName);
            $this->createMysqlUser($cloneDbUser, $cloneDbPass);
            $this->grantMysqlPrivileges($cloneDbUser, $cloneDbName);

            $export = $this->runWpCli($site->install_path, "db export " . escapeshellarg($dumpPath), $username, 300);
            if (!$export['success']) {
                throw new \RuntimeException("Database export failed: " . ($export['error'] ?: $export['output'] ?? ''));
            }

            $this->generateWpConfig($targetPath, $cloneDbName, $cloneDbUser, $cloneDbPass, $username);

            $import = $this->runWpCli($targetPath, "db import " . escapeshellarg($dumpPath), $username, 300);
            if (!$import['success']) {
                throw new \RuntimeException("Database import failed: " . ($import['error'] ?: $import['output'] ?? ''));
            }
            Process::timeout(10)->run("rm -f " . escapeshellarg($dumpPath));

            $targetUrl = "http://{$targetDomain}";
            $replace = $this->runWpCli($targetPath, "search-replace " . escapeshellarg($site->site_url) . " " . escapeshellarg($targetUrl) . " --all-tables", $username, 300);
            if (!$replace['success']) {
                throw new \RuntimeException("URL search-replace failed: " . ($replace['error'] ?: $replace['output'] ?? ''));
            }

            $siteUrl = trim($this->runWpCli($targetPath, 'option get siteurl', $username)['output'] ?? '');
            $homeUrl = trim($this->runWpCli($targetPath, 'option get home', $username)['output'] ?? '');
            if ($siteUrl !== $targetUrl || $homeUrl !== $targetUrl) {
                throw new \RuntimeException('Staging URL verification failed after search-replace.');
            }

            $this->setFileOwnership($targetPath, $username);
            $this->setSafePermissions($targetPath, $username);

            $activeStack = $this->stackService->getActiveStack();
            $this->stackService->generateVhostForDomain($activeStack, $username, $targetDomain, "/home/{$username}", $targetPath);

            $wpVersion = trim($this->runWpCli($targetPath, 'core version', $username)['output'] ?? '');
            $cloneSite = WordPressSite::create([
                'user_account_id' => $site->user_account_id,
                'parent_site_id' => $site->id,
                'site_type' => str_starts_with($targetDomain, 'staging.') ? 'staging' : 'clone',
                'domain_id' => null,
                'domain' => $targetDomain,
                'install_path' => $targetPath,
                'site_url' => $targetUrl,
                'admin_user' => $site->admin_user,
                'admin_email' => $site->admin_email,
                'db_name' => $cloneDbName,
                'db_user' => $cloneDbUser,
                'db_password_encrypted' => $cloneDbPass,
                'wp_version' => $wpVersion,
                'php_version' => $site->php_version,
                'stack_name' => $activeStack,
                'redis_enabled' => false,
                'varnish_enabled' => false,
                'ssl_enabled' => false,
                'status' => 'active',
                'performance_profile' => 'development',
            ]);

            $task->update(['wordpress_site_id' => $cloneSite->id]);
            $safeSite = $cloneSite->toArray();
            unset($safeSite['db_password_encrypted']);

            return $this->finishTask($task, [
                'success' => true,
                'output' => "Cloned to {$targetDomain} at {$targetPath}",
                'error' => '',
                'staging_site' => $safeSite,
            ]);
        } catch (\Throwable $e) {
            Process::timeout(10)->run("rm -f " . escapeshellarg($dumpPath));
            if ($cloneDbName) {
                $this->dropMysqlDatabase($cloneDbName);
            }
            if ($cloneDbUser) {
                $this->dropMysqlUser($cloneDbUser);
            }
            if ($targetPathForCleanup && isset($username) && $this->pathInsideHome($targetPathForCleanup, $username)) {
                ShellService::runAsUser($username, "rm -rf " . escapeshellarg($targetPathForCleanup), 120, "/home/{$username}");
            }
            return $this->failTask($task, $e->getMessage());
        }
    }

    public function createStaging(WordPressSite $site): array
    {
        if (($site->site_type ?? 'live') === 'staging') {
            return ['success' => false, 'message' => 'Cannot create staging from a staging site.'];
        }
        if (!$this->canCreateStaging($site)) {
            return ['success' => false, 'message' => 'Staging limit reached for this package.'];
        }

        $stagingDomain = "staging.{$site->domain}";
        $username = $this->getUsername($site);
        $safeDomain = preg_replace('/[^A-Za-z0-9_.-]/', '_', $site->domain);
        $targetPath = "/home/{$username}/staging/{$safeDomain}/public_html";

        return $this->cloneSite($site, $stagingDomain, $targetPath);
    }

    public function pushStagingToLive(WordPressSite $stagingSite, WordPressSite $liveSite): array
    {
        $task = $this->createTask($liveSite, 'staging');
        $username = $this->getUsername($liveSite);
        $timestamp = time();
        $stagingDbDump = "/tmp/wp_push_staging_{$liveSite->id}_{$timestamp}.sql";
        $liveDbDump = "/tmp/wp_push_live_{$liveSite->id}_{$timestamp}.sql";
        $preparedPath = "{$liveSite->install_path}_push_tmp_{$timestamp}";
        $oldLivePath = "{$liveSite->install_path}_pre_push_{$timestamp}";

        try {
            if (($stagingSite->site_type ?? '') !== 'staging' || (int) $stagingSite->parent_site_id !== (int) $liveSite->id) {
                throw new \RuntimeException('Staging site does not belong to the selected live site.');
            }
            if (!$this->pathInsideHome($stagingSite->install_path, $username) || !$this->pathInsideHome($liveSite->install_path, $username)) {
                throw new \RuntimeException('Invalid WordPress path for push.');
            }

            $preBackup = $this->backupSite($liveSite, 'full');
            if (!($preBackup['success'] ?? false)) {
                throw new \RuntimeException('Pre-push backup failed.');
            }
            $preDbBackup = $this->backupSite($liveSite, 'db');
            if (!($preDbBackup['success'] ?? false)) {
                throw new \RuntimeException('Pre-push database backup failed.');
            }

            $liveExport = $this->runWpCli($liveSite->install_path, "db export " . escapeshellarg($liveDbDump), $username, 300);
            if (!$liveExport['success']) {
                throw new \RuntimeException('Live database pre-push export failed.');
            }
            $stagingExport = $this->runWpCli($stagingSite->install_path, "db export " . escapeshellarg($stagingDbDump), $username, 300);
            if (!$stagingExport['success']) {
                throw new \RuntimeException('Staging database export failed.');
            }

            ShellService::runAsUser($username, "rm -rf " . escapeshellarg($preparedPath) . " && mkdir -p " . escapeshellarg($preparedPath), 120, "/home/{$username}");
            $copy = ShellService::runAsUser($username, "cp -a " . escapeshellarg($stagingSite->install_path) . "/. " . escapeshellarg($preparedPath) . "/", 300, "/home/{$username}");
            if (!$copy['success']) {
                throw new \RuntimeException('Prepared staging file copy failed.');
            }

            $liveDbName = $liveSite->db_name;
            $liveDbUser = $liveSite->db_user;
            $liveDbPass = $liveSite->db_password_encrypted;
            $this->generateWpConfig($preparedPath, $liveDbName, $liveDbUser, $liveDbPass, $username);

            ShellService::runAsUser($username, "mv " . escapeshellarg($liveSite->install_path) . " " . escapeshellarg($oldLivePath) . " && mv " . escapeshellarg($preparedPath) . " " . escapeshellarg($liveSite->install_path), 300, "/home/{$username}");

            $import = $this->runWpCli($liveSite->install_path, "db import " . escapeshellarg($stagingDbDump), $username, 300);
            if (!$import['success']) {
                throw new \RuntimeException('Live database import from staging failed.');
            }

            $replace = $this->runWpCli($liveSite->install_path, "search-replace " . escapeshellarg($stagingSite->site_url) . " " . escapeshellarg($liveSite->site_url) . " --all-tables", $username, 300);
            if (!$replace['success']) {
                throw new \RuntimeException('Live URL search-replace failed.');
            }

            $this->setFileOwnership($liveSite->install_path, $username);
            $this->setSafePermissions($liveSite->install_path, $username);

            ShellService::runAsUser($username, "rm -rf " . escapeshellarg($oldLivePath), 120, "/home/{$username}");
            Process::timeout(10)->run("rm -f " . escapeshellarg($stagingDbDump) . " " . escapeshellarg($liveDbDump));

            $stagingSite->update(['last_pushed_at' => now()]);
            $this->purgeAfterUpdate($liveSite);
            return $this->finishTask($task, [
                'success' => true,
                'output' => "Staging pushed to live: {$liveSite->domain}",
                'error' => '',
            ]);
        } catch (\Throwable $e) {
            if (isset($username) && $this->pathInsideHome($oldLivePath, $username)) {
                ShellService::runAsUser($username, "if [ -d " . escapeshellarg($oldLivePath) . " ]; then rm -rf " . escapeshellarg($liveSite->install_path) . " && mv " . escapeshellarg($oldLivePath) . " " . escapeshellarg($liveSite->install_path) . "; fi", 300, "/home/{$username}");
            }
            if (isset($username) && $this->pathInsideHome($preparedPath, $username)) {
                ShellService::runAsUser($username, "rm -rf " . escapeshellarg($preparedPath), 120, "/home/{$username}");
            }
            if (is_file($liveDbDump)) {
                $this->runWpCli($liveSite->install_path, "db import " . escapeshellarg($liveDbDump), $username, 300);
            }
            Process::timeout(10)->run("rm -f " . escapeshellarg($stagingDbDump) . " " . escapeshellarg($liveDbDump));
            return $this->failTask($task, $e->getMessage());
        }
    }

    public function deleteStaging(WordPressSite $liveOrStagingSite, ?string $stagingDomain = null): array
    {
        $stagingSite = ($liveOrStagingSite->site_type ?? 'live') === 'staging'
            ? $liveOrStagingSite
            : WordPressSite::where('parent_site_id', $liveOrStagingSite->id)
                ->where('site_type', 'staging')
                ->when($stagingDomain, fn($q) => $q->where('domain', $stagingDomain))
                ->first();

        if (!$stagingSite) {
            return ['success' => false, 'message' => 'Staging site not found.'];
        }

        $username = $this->getUsername($stagingSite);
        if (!$username || !$this->pathInsideHome($stagingSite->install_path, $username)) {
            return ['success' => false, 'message' => 'Invalid staging path.'];
        }

        try {
            $stack = $this->stackService->getActiveStack();
            $this->stackService->removeDomainScopedVhost($stack, $username, $stagingSite->domain);
            ShellService::runAsUser($username, "rm -rf " . escapeshellarg($stagingSite->install_path), 120, "/home/{$username}");
            $parentDir = dirname($stagingSite->install_path);
            ShellService::runAsUser($username, "rmdir " . escapeshellarg($parentDir) . " 2>/dev/null || true", 10, "/home/{$username}");
            $this->dropMysqlDatabase($stagingSite->db_name);
            $this->dropMysqlUser($stagingSite->db_user);
            $stagingSite->delete();

            return [
                'success' => true,
                'output' => "Deleted staging site {$stagingSite->domain}",
                'domain' => $stagingSite->domain,
            ];
        } catch (\Throwable $e) {
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function secureSite(WordPressSite $site): array
    {
        $task = $this->createTask($site, 'security_scan');
        $username = $this->getUsername($site);

        try {
            $result = $this->runWpCli($site->install_path, 'config set DISALLOW_FILE_EDIT true --raw', $username);

            $result = $this->runWpCli($site->install_path, 'config set WP_DEBUG false --raw', $username);

            $salts = $this->fetchSalts();
            $this->patchWpConfigSalts($site, $salts);

            $this->runWpCli($site->install_path, 'config set block_unauthenticated_requests true --raw', $username);

            return $this->finishTask($task, [
                'success' => true,
                'output' => "Security hardening applied to {$site->domain}",
                'error' => '',
            ]);
        } catch (\Throwable $e) {
            return $this->failTask($task, $e->getMessage());
        }
    }

    public function scanSite(WordPressSite $site): array
    {
        $task = $this->createTask($site, 'security_scan');
        $username = $this->getUsername($site);

        try {
            $wpVersion = trim($this->runWpCli($site->install_path, 'core version', $username)['output'] ?? '');
            $coreCheck = $this->runWpCli($site->install_path, 'core check-update --format=json', $username);
            $outdatedCore = $coreCheck['success'] && !empty(json_decode($coreCheck['output'], true));

            $plugins = $this->runWpCli($site->install_path, 'plugin list --format=json', $username);
            $pluginData = $plugins['success'] ? json_decode($plugins['output'], true) : [];
            $outdatedPlugins = 0;
            foreach ($pluginData as $p) {
                if (($p['update'] ?? '') === 'available') {
                    $outdatedPlugins++;
                }
            }

            $themes = $this->runWpCli($site->install_path, 'theme list --format=json', $username);
            $themeData = $themes['success'] ? json_decode($themes['output'], true) : [];
            $outdatedThemes = 0;
            foreach ($themeData as $t) {
                if (($t['update'] ?? '') === 'available') {
                    $outdatedThemes++;
                }
            }

            $suspiciousResult = ShellService::runAsUser($username, "find " . escapeshellarg($site->install_path) . " -name '*.php' -path '*/wp-content/uploads/*' 2>/dev/null | wc -l", 60, "/home/{$username}");
            $suspiciousFiles = (int) trim($suspiciousResult['output'] ?? '0');

            $permsResult = ShellService::runAsUser($username, "find " . escapeshellarg($site->install_path) . " -perm -o+w -not -path '*/wp-content/uploads/*' 2>/dev/null | wc -l", 30, "/home/{$username}");
            $weakPerms = (int) trim($permsResult['output'] ?? '0');

            $debugMode = $this->runWpCli($site->install_path, 'config get WP_DEBUG', $username);
            $xmlRpc = ShellService::runAsUser($username, "grep -c 'xmlrpc' " . escapeshellarg($site->install_path) . "/.htaccess 2>/dev/null || echo 0", 10, "/home/{$username}");

            $scan = WordPressSecurityScan::create([
                'wordpress_site_id' => $site->id,
                'wp_version' => $wpVersion,
                'outdated_core' => $outdatedCore,
                'outdated_plugins' => $outdatedPlugins,
                'outdated_themes' => $outdatedThemes,
                'suspicious_files' => $suspiciousFiles,
                'weak_permissions' => $weakPerms,
                'result_json' => [
                    'core_updates' => $coreCheck['success'] ? json_decode($coreCheck['output'], true) : [],
                    'outdated_plugin_list' => array_filter($pluginData, fn($p) => ($p['update'] ?? '') === 'available'),
                    'outdated_theme_list' => array_filter($themeData, fn($t) => ($t['update'] ?? '') === 'available'),
                    'debug_enabled' => trim($debugMode['output'] ?? '') === '1',
                    'xmlrpc_accessible' => (int) trim($xmlRpc['output'] ?? '0') === 0,
                ],
            ]);

            $site->update(['last_scan_at' => now()]);

            $summary = "Scan complete: WP {$wpVersion} | Core outdated: " . ($outdatedCore ? 'YES' : 'no')
                . " | Plugins outdated: {$outdatedPlugins} | Themes outdated: {$outdatedThemes}"
                . " | Suspicious files: {$suspiciousFiles} | Weak perms: {$weakPerms}";

            return $this->finishTask($task, [
                'success' => true,
                'output' => $summary,
                'error' => '',
                'scan' => $scan,
            ]);
        } catch (\Throwable $e) {
            return $this->failTask($task, $e->getMessage());
        }
    }

    public function repairPermissions(WordPressSite $site): array
    {
        $task = $this->createTask($site, 'security_scan');
        $username = $this->getUsername($site);

        $this->setFileOwnership($site->install_path, $username);
        $this->setSafePermissions($site->install_path, $username);

        return $this->finishTask($task, [
            'success' => true,
            'output' => "Permissions repaired for {$site->domain}",
            'error' => '',
        ]);
    }

    public function purgeCache(WordPressSite $site): array
    {
        $task = $this->createTask($site, 'cache_purge');
        $username = $this->getUsername($site);

        $this->runWpCli($site->install_path, 'cache flush', $username);

        if ($site->redis_enabled) {
            $this->runWpCli($site->install_path, 'redis flush', $username);
        }

        if ($site->varnish_enabled) {
            $this->purgeVarnishCache($site);
        }

        return $this->finishTask($task, [
            'success' => true,
            'output' => "Cache purged for {$site->domain}",
            'error' => '',
        ]);
    }

    public function installPlugin(WordPressSite $site, string $plugin): array
    {
        $username = $this->getUsername($site);
        $result = $this->runWpCli($site->install_path, "plugin install " . escapeshellarg($plugin) . " --activate", $username);
        return $result;
    }

    public function activatePlugin(WordPressSite $site, string $plugin): array
    {
        $username = $this->getUsername($site);
        return $this->runWpCli($site->install_path, "plugin activate " . escapeshellarg($plugin), $username);
    }

    public function deactivatePlugin(WordPressSite $site, string $plugin): array
    {
        $username = $this->getUsername($site);
        return $this->runWpCli($site->install_path, "plugin deactivate " . escapeshellarg($plugin), $username);
    }

    public function listPlugins(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $result = $this->runWpCli($site->install_path, 'plugin list --format=json', $username);
        return $result['success'] ? json_decode($result['output'], true) : [];
    }

    public function listThemes(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $result = $this->runWpCli($site->install_path, 'theme list --format=json', $username);
        return $result['success'] ? json_decode($result['output'], true) : [];
    }

    public function getDiskUsage(WordPressSite $site): string
    {
        $username = $this->getUsername($site);
        $result = ShellService::runAsUser($username, "du -sh " . escapeshellarg($site->install_path) . " 2>/dev/null | cut -f1", 30, "/home/{$username}");
        return trim($result['output'] ?? '0');
    }

    public function suspendSite(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $domain = $site->domain;

        // 1. Stack-aware suspension (403 vhost + Varnish ban)
        $stackResult = $this->stackService->suspendDomain($username, $domain);

        // 2. Replace wp-config.php with maintenance stub as defense-in-depth
        ShellService::runAsUser($username, "mv " . escapeshellarg($site->install_path) . "/wp-config.php " . escapeshellarg($site->install_path) . "/wp-config.php.suspended", 10, "/home/{$username}");

        $maintenance = "<?php http_response_code(403); echo 'This account has been suspended.'; exit; ?>";
        $tempFile = tempnam(sys_get_temp_dir(), 'maint');
        file_put_contents($tempFile, $maintenance);
        chmod($tempFile, 0644);
        ShellService::runAsUser($username, "cp " . escapeshellarg($tempFile) . " " . escapeshellarg($site->install_path) . "/wp-config.php && chmod 640 " . escapeshellarg($site->install_path) . "/wp-config.php", 10, "/home/{$username}");
        @unlink($tempFile);

        $site->update(['status' => 'suspended']);
        return [
            'success' => true,
            'message' => "Site {$domain} suspended",
            'stack_actions' => $stackResult['actions'] ?? [],
        ];
    }

    public function unsuspendSite(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $domain = $site->domain;

        // 1. Restore wp-config.php
        ShellService::runAsUser($username, "mv " . escapeshellarg($site->install_path) . "/wp-config.php.suspended " . escapeshellarg($site->install_path) . "/wp-config.php", 10, "/home/{$username}");

        // 2. Stack-aware unsuspend (restore vhost + purge Varnish)
        $stackResult = $this->stackService->unsuspendDomain($username, $domain);

        $site->update(['status' => 'active']);
        return [
            'success' => true,
            'message' => "Site {$domain} unsuspended",
            'stack_actions' => $stackResult['actions'] ?? [],
        ];
    }

    public function enableSsl(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $domain = $site->domain;
        $home = "/home/{$username}";

        $certResult = Process::timeout(120)->run("certbot certonly --webroot -w {$home}/public_html -d {$domain} --non-interactive --agree-tos --register-unsafely-without-email 2>&1");

        if ($certResult->successful()) {
            $activeStack = $this->stackService->getActiveStack();
            $this->stackService->generateVhostForDomain($activeStack, $username, $domain, $home);
            $site->update(['ssl_enabled' => true]);
            return ['success' => true, 'message' => "SSL enabled for {$domain}"];
        }

        return ['success' => false, 'message' => "SSL failed: {$certResult->output()}"];
    }

    protected function createTask(WordPressSite $site, string $type): WordPressTask
    {
        return WordPressTask::create([
            'wordpress_site_id' => $site->id,
            'type' => $type,
            'status' => 'running',
            'started_at' => now(),
            'created_by' => auth()->user()?->username ?? 'system',
        ]);
    }

    protected function finishTask(WordPressTask $task, array $result): array
    {
        $task->update([
            'status' => $result['success'] ? 'success' : 'failed',
            'output' => $result['output'] ?? '',
            'finished_at' => now(),
        ]);
        return $result;
    }

    protected function failTask(WordPressTask $task, string $message): array
    {
        $task->update([
            'status' => 'failed',
            'output' => $message,
            'finished_at' => now(),
        ]);
        return ['success' => false, 'message' => $message];
    }

    protected function createMysqlDatabase(string $dbName): void
    {
        $this->mysql()->statement("CREATE DATABASE IF NOT EXISTS `" . str_replace('`', '', $dbName) . "`");
    }

    protected function createMysqlUser(string $dbUser, string $dbPass): void
    {
        $this->mysql()->statement("CREATE USER IF NOT EXISTS '" . str_replace("'", '', $dbUser) . "'@'localhost' IDENTIFIED BY '" . str_replace("'", '', $dbPass) . "'");
    }

    protected function grantMysqlPrivileges(string $dbUser, string $dbName): void
    {
        $this->mysql()->statement("GRANT ALL PRIVILEGES ON `" . str_replace('`', '', $dbName) . "`.* TO '" . str_replace("'", '', $dbUser) . "'@'localhost'");
        $this->mysql()->statement("FLUSH PRIVILEGES");
    }

    protected function dropMysqlDatabase(string $dbName): void
    {
        $this->mysql()->statement("DROP DATABASE IF EXISTS `" . str_replace('`', '', $dbName) . "`");
    }

    protected function dropMysqlUser(string $dbUser): void
    {
        $this->mysql()->statement("DROP USER IF EXISTS '" . str_replace("'", '', $dbUser) . "'@'localhost'");
    }

    protected function mysql(): \Illuminate\Database\Connection
    {
        return DB::connection('mysql');
    }

    protected function generateDbName(string $username, string $suffix = '_wp'): string
    {
        $base = substr($username, 0, 12) . $suffix;
        $name = $base;
        $i = 1;
        while ($this->mysql()->select("SHOW DATABASES LIKE '{$name}'")) {
            $name = $base . $i;
            $i++;
            if ($i > 100) break;
        }
        return $name;
    }

    protected function generateDbUser(string $username, string $suffix = '_wp'): string
    {
        return substr($username, 0, 16) . $suffix;
    }

    protected function generateUniqueDbUser(string $username, string $suffix = '_wp'): string
    {
        $base = substr($username, 0, 12) . $suffix;
        for ($i = 0; $i < 100; $i++) {
            $candidate = $base . ($i === 0 ? '' : $i);
            $exists = $this->mysql()->select('SELECT User FROM mysql.user WHERE User = ? LIMIT 1', [$candidate]);
            if (!$exists) {
                return $candidate;
            }
        }

        return substr($base, 0, 22) . Str::lower(Str::random(6));
    }

    protected function pathInsideHome(string $path, string $username): bool
    {
        $home = "/home/{$username}";
        $normalized = rtrim(str_replace('\\', '/', $path), '/');
        return $normalized === $home || str_starts_with($normalized . '/', $home . '/');
    }

    protected function fetchSalts(): string
    {
        $result = Process::timeout(15)->run("curl -s https://api.wordpress.org/secret-key/1.1/salt/ 2>/dev/null");
        if ($result->successful() && !empty($result->output())) {
            return $result->output();
        }

        $keys = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'];
        $salts = '';
        foreach ($keys as $key) {
            $salts .= "define('{$key}', '" . Str::random(64) . "');\n";
        }
        return $salts;
    }

    protected function patchWpConfigSalts(WordPressSite $site, string $salts): void
    {
        $username = $this->getUsername($site);
        $configPath = "{$site->install_path}/wp-config.php";
        $keys = ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT'];

        $readResult = ShellService::runAsUser($username, "cat " . escapeshellarg($configPath), 10, "/home/{$username}");
        $content = $readResult['output'] ?? '';
        if (empty($content)) return;

        foreach ($keys as $key) {
            $pattern = "/define\s*\(\s*['\"]{$key}['\"].*?\);/";
            if (preg_match($pattern, $salts, $match)) {
                $content = preg_replace($pattern, $match[0], $content);
            }
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'wpconfig');
        file_put_contents($tempFile, $content);
        chmod($tempFile, 0644);
        ShellService::runAsUser($username, "cp " . escapeshellarg($tempFile) . " " . escapeshellarg($configPath) . " && chmod 640 " . escapeshellarg($configPath), 10, "/home/{$username}");
        @unlink($tempFile);
    }

    protected function setFileOwnership(string $path, string $username): void
    {
        Process::timeout(60)->run("chown -R {$username}:{$username} {$path}");
    }

    protected function setSafePermissions(string $path, string $username): void
    {
        Process::timeout(60)->run("find {$path} -type d -exec chmod 755 {} \\;");
        Process::timeout(60)->run("find {$path} -type f -exec chmod 644 {} \\;");
        Process::timeout(30)->run("chmod 640 {$path}/wp-config.php");
    }

    protected function enableMaintenanceMode(WordPressSite $site): void
    {
        $this->runWpCli($site->install_path, 'maintenance-mode activate', $this->getUsername($site));
    }

    protected function disableMaintenanceMode(WordPressSite $site): void
    {
        $this->runWpCli($site->install_path, 'maintenance-mode deactivate', $this->getUsername($site));
    }

    protected function cleanupFailedInstall(?string $dbName, ?string $dbUser, string $installPath): void
    {
        if ($dbName) {
            $this->dropMysqlDatabase($dbName);
        }
        if ($dbUser) {
            $this->dropMysqlUser($dbUser);
        }
        Process::timeout(30)->run("rm -rf {$installPath}");
    }

    protected function getUsername(WordPressSite $site): string
    {
        $account = UserAccount::find($site->user_account_id);
        if ($account) {
            return $account->user?->username ?? $account->username ?? '';
        }
        $account = DB::connection('mysql')->table('accounts')->where('id', $site->user_account_id)->first();
        return $account->username ?? '';
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    protected function generateVarnishWordPressVcl(): string
    {
        return <<<'VCL'
sub vcl_recv {
    if (req.url ~ "wp-admin" || req.url ~ "wp-login.php" || req.url ~ "xmlrpc.php") {
        return (pass);
    }
    if (req.method == "POST") {
        return (pass);
    }
    if (req.http.Authorization) {
        return (pass);
    }
    if (req.url ~ "cart" || req.url ~ "checkout" || req.url ~ "my-account") {
        return (pass);
    }
    if (req.http.Cookie ~ "wordpress_logged_in|wp-postpass|woocommerce_session|comment_author") {
        return (pass);
    }
    unset req.http.Cookie;
    return (hash);
}

sub vcl_backend_response {
    if (bereq.url ~ "wp-admin" || bereq.url ~ "wp-login.php") {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }
    if (beresp.http.Set-Cookie) {
        set beresp.uncacheable = true;
        set beresp.ttl = 0s;
        return (deliver);
    }
    set beresp.ttl = 1h;
    return (deliver);
}

sub vcl_deliver {
    if (obj.hits > 0) {
        set resp.http.X-Cache = "HIT";
    } else {
        set resp.http.X-Cache = "MISS";
    }
}
VCL;
    }

    // =========================================================================
    // PHASE 1: Redis Object Cache
    // =========================================================================

    public function checkRedisHealth(): array
    {
        $result = Process::timeout(10)->run("redis-cli ping 2>/dev/null");
        $running = trim($result->output()) === 'PONG';

        $info = Process::timeout(10)->run("redis-cli INFO memory 2>/dev/null | grep used_memory_human");
        $memory = '';
        if ($info->successful()) {
            preg_match('/used_memory_human:(\S+)/', $info->output(), $m);
            $memory = $m[1] ?? '';
        }

        $keys = Process::timeout(10)->run("redis-cli DBSIZE 2>/dev/null");
        $keyCount = '';
        if ($keys->successful()) {
            preg_match('/keys=(\d+)/', $keys->output(), $m);
            $keyCount = $m[1] ?? '0';
        }

        $version = Process::timeout(10)->run("redis-cli INFO server 2>/dev/null | grep redis_version");
        $redisVersion = '';
        if ($version->successful()) {
            preg_match('/redis_version:(\S+)/', $version->output(), $m);
            $redisVersion = $m[1] ?? '';
        }

        return [
            'running' => $running,
            'version' => $redisVersion,
            'memory' => $memory,
            'keys' => $keyCount,
        ];
    }

    public function allocateRedisDbIndex(WordPressSite $site): int
    {
        if ($site->redis_db_index > 0) {
            return $site->redis_db_index;
        }

        $used = WordPressSite::where('redis_enabled', true)
            ->where('redis_db_index', '>', 0)
            ->where('id', '!=', $site->id)
            ->pluck('redis_db_index')
            ->toArray();

        for ($i = 1; $i <= 15; $i++) {
            if (!in_array($i, $used)) {
                $site->update(['redis_db_index' => $i]);
                return $i;
            }
        }

        $site->update(['redis_db_index' => 0]);
        return 0;
    }

    public function getRedisStatus(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $result = $this->runWpCli($site->install_path, 'redis status', $username);
        return [
            'success' => $result['success'],
            'output' => $result['output'] ?? '',
            'connected' => str_contains($result['output'] ?? '', 'Connected'),
        ];
    }

    public function enableRedis(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $redisPrefix = 'wp_' . Str::slug($site->domain) . '_';
        $dbIndex = $this->allocateRedisDbIndex($site);

        $this->runWpCli($site->install_path, "config set WP_REDIS_PREFIX '{$redisPrefix}'", $username);
        $this->runWpCli($site->install_path, "config set WP_REDIS_HOST 127.0.0.1", $username);
        $this->runWpCli($site->install_path, "config set WP_REDIS_PORT 6379 --raw", $username);
        $this->runWpCli($site->install_path, "config set WP_REDIS_DATABASE {$dbIndex} --raw", $username);
        $this->runWpCli($site->install_path, "config set WP_CACHE true --raw", $username);

        $this->installPlugin($site, 'redis-cache');
        $this->activatePlugin($site, 'redis-cache');

        $result = $this->runWpCli($site->install_path, 'redis enable', $username);

        $dropin = ShellService::runAsUser($username, "test -f " . escapeshellarg($site->install_path) . "/wp-content/object-cache.php && echo EXISTS", 10, "/home/{$username}");
        $dropinExists = str_contains($dropin['output'] ?? '', 'EXISTS');

        $site->update([
            'redis_enabled' => true,
            'redis_prefix' => $redisPrefix,
            'redis_db_index' => $dbIndex,
        ]);

        return [
            'success' => $result['success'] && $dropinExists,
            'message' => "Redis enabled for {$site->domain} (prefix={$redisPrefix}, db={$dbIndex}, dropin=" . ($dropinExists ? 'ok' : 'missing') . ")",
        ];
    }

    public function disableRedis(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $this->runWpCli($site->install_path, 'redis disable', $username);
        $this->runWpCli($site->install_path, 'config delete WP_CACHE', $username);

        // Remove drop-in
        ShellService::runAsUser($username, "rm -f " . escapeshellarg($site->install_path) . "/wp-content/object-cache.php", 10, "/home/{$username}");

        $site->update(['redis_enabled' => false]);
        return ['success' => true, 'message' => "Redis disabled for {$site->domain}"];
    }

    public function flushRedisCache(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $result = $this->runWpCli($site->install_path, 'redis flush', $username);

        // Also flush specific DB index
        if ($site->redis_db_index > 0) {
            Process::timeout(10)->run("redis-cli -n {$site->redis_db_index} FLUSHDB 2>/dev/null");
        }

        return [
            'success' => $result['success'],
            'message' => "Redis cache flushed for {$site->domain}",
        ];
    }

    // =========================================================================
    // PHASE 2: Varnish Integration
    // =========================================================================

    public function getVarnishStatus(): array
    {
        $result = Process::timeout(10)->run("varnishstat -1 -f MAIN.cache_hit -f MAIN.cache_miss 2>/dev/null");
        $hits = 0;
        $misses = 0;
        if ($result->successful()) {
            preg_match('/MAIN\.cache_hit\s+(\d+)/', $result->output(), $hm);
            preg_match('/MAIN\.cache_miss\s+(\d+)/', $result->output(), $mm);
            $hits = (int) ($hm[1] ?? 0);
            $misses = (int) ($mm[1] ?? 0);
        }
        $total = $hits + $misses;
        return [
            'running' => $result->successful(),
            'hits' => $hits,
            'misses' => $misses,
            'hit_rate' => $total > 0 ? round(($hits / $total) * 100, 1) : 0,
        ];
    }

    public function testVarnishCacheHit(WordPressSite $site): array
    {
        $result = (new VarnishDomainService())->test($site->domain);
        $cacheHeader = 'X-Cache: ' . ($result['second']['x_cache'] ?? 'MISS');
        $isHit = (bool) ($result['html_hit'] ?? false);

        return [
            'success' => true,
            'header' => $cacheHeader,
            'is_hit' => $isHit,
            'message' => $isHit ? 'Varnish cache HIT' : 'Varnish cache MISS',
            'result' => $result,
        ];
    }

    public function configureVarnishRules(WordPressSite $site, string $mode = 'cache'): array
    {
        $stack = $this->stackService->getActiveStack();
        if (!str_contains($stack, 'varnish')) {
            return ['success' => false, 'message' => 'Active stack does not use Varnish.'];
        }

        $result = (new VarnishDomainService())->configure($site->domain, [
            'varnish_enabled' => $mode !== 'bypass',
            'varnish_mode' => $mode,
            'static_asset_mode' => 'nginx_direct',
            'html_ttl' => $mode === 'cache' ? 300 : 0,
            'static_ttl' => 86400,
            'grace_ttl' => 3600,
            'purge_enabled' => true,
        ]);

        $site->update(['varnish_enabled' => $mode !== 'bypass']);

        return $result;
    }

    public function purgeVarnishCache(WordPressSite $site): array
    {
        return (new VarnishDomainService())->purge($site->domain) + [
            'message' => "Varnish cache purged for {$site->domain}",
        ];
    }

    public function generatePerSiteVarnishVcl(WordPressSite $site): string
    {
        $domain = $site->domain;

        return <<<VCL
# Per-site Varnish rules for {$domain}
sub vcl_recv {
    if (req.http.host == "{$domain}" || req.http.host == "www.{$domain}") {
        # Bypass cache for admin, login, cron, xmlrpc
        if (req.url ~ "^/wp-admin" || req.url ~ "^/wp-login\\.php" || req.url ~ "^/wp-cron\\.php") {
            return (pass);
        }
        if (req.url ~ "^/xmlrpc\\.php") {
            return (pass);
        }

        # WooCommerce bypass
        if (req.url ~ "^/cart" || req.url ~ "^/checkout" || req.url ~ "^/my-account" || req.url ~ "^/addons") {
            return (pass);
        }

        # Bypass for logged-in users
        if (req.http.Cookie ~ "wordpress_logged_in|wp-postpass|woocommerce_session|woocommerce_items_in_cart|comment_author") {
            return (pass);
        }

        # Bypass for POST requests
        if (req.method == "POST") {
            return (pass);
        }

        # Bypass for Authorization
        if (req.http.Authorization) {
            return (pass);
        }

        # Strip cookies for static assets â€” cache aggressively
        if (req.url ~ "\\.(css|js|jpg|jpeg|png|gif|ico|svg|woff|woff2|ttf|eot|webp|avif)(\\?.*)?$") {
            unset req.http.Cookie;
            return (hash);
        }

        # Strip all other cookies
        unset req.http.Cookie;
        return (hash);
    }
}
VCL;
    }

    public function purgeAfterUpdate(WordPressSite $site): void
    {
        $this->purgeCache($site);
    }

    // =========================================================================
    // PHASE 3: Performance Presets
    // =========================================================================

    public const PERFORMANCE_PROFILES = [
        'safe_default' => [
            'label' => 'Safe Default',
            'description' => 'Balanced settings for most WordPress sites',
            'php_fpm_pm' => 'ondemand',
            'php_fpm_max_children' => 10,
            'php_fpm_memory_limit' => 256,
            'php_fpm_max_execution_time' => 60,
            'php_fpm_upload_max_filesize' => 64,
            'redis_enabled' => true,
            'varnish_enabled' => true,
            'varnish_mode' => 'shield',
            'static_asset_mode' => 'nginx_direct',
            'html_ttl' => 0,
            'static_ttl' => 86400,
            'grace_ttl' => 3600,
            'wp_cron_disabled' => false,
            'xmlrpc_blocked' => false,
            'static_cache_ttl' => '30d',
        ],
        'high_traffic' => [
            'label' => 'High Traffic News',
            'description' => 'Optimized for high-traffic content sites with anonymous visitors',
            'php_fpm_pm' => 'static',
            'php_fpm_max_children' => 30,
            'php_fpm_memory_limit' => 256,
            'php_fpm_max_execution_time' => 30,
            'php_fpm_upload_max_filesize' => 32,
            'redis_enabled' => true,
            'varnish_enabled' => true,
            'varnish_mode' => 'cache',
            'static_asset_mode' => 'nginx_direct',
            'html_ttl' => 300,
            'static_ttl' => 86400,
            'grace_ttl' => 3600,
            'wp_cron_disabled' => true,
            'xmlrpc_blocked' => true,
            'static_cache_ttl' => '90d',
        ],
        'woocommerce' => [
            'label' => 'WooCommerce',
            'description' => 'E-commerce with cart/checkout bypass and longer PHP timeout',
            'php_fpm_pm' => 'dynamic',
            'php_fpm_max_children' => 20,
            'php_fpm_memory_limit' => 512,
            'php_fpm_max_execution_time' => 120,
            'php_fpm_upload_max_filesize' => 128,
            'redis_enabled' => true,
            'varnish_enabled' => true,
            'varnish_mode' => 'shield',
            'static_asset_mode' => 'nginx_direct',
            'html_ttl' => 0,
            'static_ttl' => 86400,
            'grace_ttl' => 3600,
            'wp_cron_disabled' => false,
            'xmlrpc_blocked' => true,
            'static_cache_ttl' => '30d',
        ],
        'membership' => [
            'label' => 'Membership / Logged-in Heavy',
            'description' => 'Mostly logged-in users, minimal caching, higher PHP resources',
            'php_fpm_pm' => 'dynamic',
            'php_fpm_max_children' => 25,
            'php_fpm_memory_limit' => 512,
            'php_fpm_max_execution_time' => 90,
            'php_fpm_upload_max_filesize' => 128,
            'redis_enabled' => true,
            'varnish_enabled' => true,
            'varnish_mode' => 'shield',
            'static_asset_mode' => 'nginx_direct',
            'html_ttl' => 0,
            'static_ttl' => 86400,
            'grace_ttl' => 3600,
            'wp_cron_disabled' => false,
            'xmlrpc_blocked' => true,
            'static_cache_ttl' => '7d',
        ],
        'development' => [
            'label' => 'Development / Staging',
            'description' => 'Debug-friendly, no caching, higher timeouts for debugging',
            'php_fpm_pm' => 'ondemand',
            'php_fpm_max_children' => 5,
            'php_fpm_memory_limit' => 512,
            'php_fpm_max_execution_time' => 300,
            'php_fpm_upload_max_filesize' => 256,
            'redis_enabled' => false,
            'varnish_enabled' => false,
            'varnish_mode' => 'bypass',
            'static_asset_mode' => 'nginx_direct',
            'html_ttl' => 0,
            'static_ttl' => 86400,
            'grace_ttl' => 0,
            'wp_cron_disabled' => false,
            'xmlrpc_blocked' => false,
            'static_cache_ttl' => '0',
        ],
    ];

    public function getPerformanceProfiles(): array
    {
        return self::PERFORMANCE_PROFILES;
    }

    public function applyPerformanceProfile(WordPressSite $site, string $profileKey): array
    {
        $profiles = self::PERFORMANCE_PROFILES;
        if (!isset($profiles[$profileKey])) {
            return ['success' => false, 'message' => "Unknown profile: {$profileKey}"];
        }

        $profile = $profiles[$profileKey];
        $username = $this->getUsername($site);

        // Apply PHP-FPM settings
        $site->update([
            'performance_profile' => $profileKey,
            'php_fpm_pm' => $profile['php_fpm_pm'],
            'php_fpm_max_children' => $profile['php_fpm_max_children'],
            'php_fpm_memory_limit' => $profile['php_fpm_memory_limit'],
            'php_fpm_max_execution_time' => $profile['php_fpm_max_execution_time'],
            'php_fpm_upload_max_filesize' => $profile['php_fpm_upload_max_filesize'],
        ]);

        // Apply Redis
        if ($profile['redis_enabled'] && !$site->redis_enabled) {
            $this->enableRedis($site);
        } elseif (!$profile['redis_enabled'] && $site->redis_enabled) {
            $this->disableRedis($site);
        }

        // Apply Varnish mode/static routing. No WordPress plugin is required.
        (new VarnishDomainService())->configure($site->domain, [
            'varnish_enabled' => $profile['varnish_enabled'],
            'varnish_mode' => $profile['varnish_mode'],
            'static_asset_mode' => $profile['static_asset_mode'],
            'html_ttl' => $profile['html_ttl'],
            'static_ttl' => $profile['static_ttl'],
            'grace_ttl' => $profile['grace_ttl'],
            'purge_enabled' => true,
        ]);
        $site->update(['varnish_enabled' => $profile['varnish_enabled']]);

        // Apply WP-Cron
        $this->setWpCronDisabled($site, $profile['wp_cron_disabled']);

        // Apply XML-RPC block
        $this->setXmlRpcBlock($site, $profile['xmlrpc_blocked']);

        // Regenerate PHP-FPM pool
        $this->generatePhpFpmPool($site);

        // Regenerate Nginx vhost with static cache headers
        $this->regenerateVhostStaticCache($site, $profile['static_cache_ttl']);

        Log::info("Performance profile '{$profileKey}' applied to {$site->domain}");
        ActivityLog::create([
            'user_id' => auth()->id(),
            'action' => 'performance_profile_applied',
            'module' => 'wordpress',
            'description' => "Profile '{$profileKey}' applied to {$site->domain}",
            'ip_address' => request()?->ip(),
            'metadata' => ['site_id' => $site->id, 'profile' => $profileKey],
        ]);

        return [
            'success' => true,
            'message' => "Profile '{$profile['label']}' applied to {$site->domain}",
            'profile' => $profile,
        ];
    }

    public function resetPerformanceProfile(WordPressSite $site): array
    {
        return $this->applyPerformanceProfile($site, 'safe_default');
    }

    protected function setXmlRpcBlock(WordPressSite $site, bool $blocked): void
    {
        $username = $this->getUsername($site);
        $htaccess = "{$site->install_path}/.htaccess";

        if ($blocked) {
            $blockRule = "\n# Block xmlrpc.php\n<Files xmlrpc.php>\nOrder Deny,Allow\nDeny from all\n</Files>\n";
            ShellService::runAsUser($username, "grep -q 'Block xmlrpc' " . escapeshellarg($htaccess) . " 2>/dev/null || echo " . escapeshellarg($blockRule) . " >> " . escapeshellarg($htaccess), 10, "/home/{$username}");
        } else {
            ShellService::runAsUser($username, "sed -i '/# Block xmlrpc.php/,/<\\/Files>/d' " . escapeshellarg($htaccess), 10, "/home/{$username}");
        }
    }

    // =========================================================================
    // PHASE 4: Per-site PHP-FPM Tuning
    // =========================================================================

    public function generatePhpFpmPool(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $home = "/home/{$username}";
        $poolDir = '/etc/php-fpm.d/users';

        $pm = $site->php_fpm_pm;
        $maxChildren = $site->php_fpm_max_children;
        $memoryLimit = $site->php_fpm_memory_limit;
        $maxExecTime = $site->php_fpm_max_execution_time;
        $uploadSize = $site->php_fpm_upload_max_filesize;

        // Validate ranges
        $maxChildren = max(1, min(100, $maxChildren));
        $memoryLimit = max(64, min(2048, $memoryLimit));
        $maxExecTime = max(5, min(600, $maxExecTime));
        $uploadSize = max(2, min(1024, $uploadSize));

        $pool = <<<FPM
[{$username}]
user = {$username}
group = {$username}
listen = /run/openpanel-php-user-{$username}.sock
listen.owner = nginx
listen.group = nginx
listen.mode = 0660
listen.acl_users = nginx,apache

pm = {$pm}
pm.max_children = {$maxChildren}
FPM;

        if ($pm === 'dynamic') {
            $startServers = max(1, intdiv($maxChildren, 4));
            $minSpare = max(1, intdiv($maxChildren, 8));
            $maxSpare = max($minSpare + 1, intdiv($maxChildren, 2));
            $pool .= "\npm.start_servers = {$startServers}";
            $pool .= "\npm.min_spare_servers = {$minSpare}";
            $pool .= "\npm.max_spare_servers = {$maxSpare}";
        }

        if ($pm === 'ondemand') {
            $pool .= "\npm.process_idle_timeout = 60s";
        }

        $pool .= "\npm.max_requests = 500";
        $pool .= "\n";
        $pool .= "\nphp_admin_value[error_log] = {$home}/logs/php-error.log";
        $pool .= "\nphp_admin_flag[log_errors] = 1";
        $pool .= "\nphp_value[memory_limit] = {$memoryLimit}M";
        $pool .= "\nphp_value[max_execution_time] = {$maxExecTime}";
        $pool .= "\nphp_value[upload_max_filesize] = {$uploadSize}M";
        $pool .= "\nphp_value[post_max_size] = {$uploadSize}M";
        $pool .= "\nphp_value[open_basedir] = {$home}:/tmp";
        $pool .= "\nphp_value[session.save_path] = {$home}/tmp";
        $pool .= "\n";

        // Backup current config
        $poolFile = "{$poolDir}/{$username}.conf";
        $backupFile = "{$poolFile}.bak." . date('YmdHis');
        Process::timeout(10)->run("test -f {$poolFile} && cp {$poolFile} {$backupFile} 2>/dev/null");

        // Write new config
        $tempFile = tempnam(sys_get_temp_dir(), 'fpm');
        file_put_contents($tempFile, $pool);
        Process::timeout(10)->run("cp {$tempFile} {$poolFile}");
        @unlink($tempFile);

        // Test config
        $test = Process::timeout(15)->run("php-fpm -t 2>&1");
        if (!$test->successful()) {
            // Rollback
            if (file_exists($backupFile)) {
                Process::timeout(10)->run("cp {$backupFile} {$poolFile}");
            }
            return [
                'success' => false,
                'message' => "PHP-FPM config test failed: " . $test->errorOutput(),
            ];
        }

        // Reload PHP-FPM
        Process::timeout(15)->run("systemctl reload php-fpm 2>/dev/null || systemctl restart php-fpm 2>/dev/null");

        return [
            'success' => true,
            'message' => "PHP-FPM pool updated for {$username} (pm={$pm}, max_children={$maxChildren}, memory={$memoryLimit}M)",
        ];
    }

    public function getPhpFpmPoolStatus(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $poolFile = "/etc/php-fpm.d/users/{$username}.conf";

        if (!file_exists($poolFile) && !Process::run("test -f {$poolFile}")->successful()) {
            return ['exists' => false];
        }

        $content = Process::run("cat {$poolFile}")->output();

        return [
            'exists' => true,
            'content' => $content,
            'pm' => $site->php_fpm_pm,
            'max_children' => $site->php_fpm_max_children,
            'memory_limit' => $site->php_fpm_memory_limit,
            'max_execution_time' => $site->php_fpm_max_execution_time,
            'upload_max_filesize' => $site->php_fpm_upload_max_filesize,
        ];
    }

    public function updatePhpFpmSettings(WordPressSite $site, array $settings): array
    {
        $site->update([
            'php_fpm_pm' => $settings['pm'] ?? $site->php_fpm_pm,
            'php_fpm_max_children' => $settings['max_children'] ?? $site->php_fpm_max_children,
            'php_fpm_memory_limit' => $settings['memory_limit'] ?? $site->php_fpm_memory_limit,
            'php_fpm_max_execution_time' => $settings['max_execution_time'] ?? $site->php_fpm_max_execution_time,
            'php_fpm_upload_max_filesize' => $settings['upload_max_filesize'] ?? $site->php_fpm_upload_max_filesize,
        ]);

        return $this->generatePhpFpmPool($site);
    }

    // =========================================================================
    // PHASE 5: Static Asset Caching (vhost regeneration)
    // =========================================================================

    protected function regenerateVhostStaticCache(WordPressSite $site, string $ttl): void
    {
        $stack = $this->stackService->getActiveStack();
        $username = $this->getUsername($site);
        $home = "/home/{$username}";
        $domain = $site->domain;

        $this->stackService->generateVhostForDomain($stack, $username, $domain, $home);
    }

    // =========================================================================
    // PHASE 6: WP-Cron Control
    // =========================================================================

    public function setWpCronDisabled(WordPressSite $site, bool $disabled): void
    {
        $username = $this->getUsername($site);
        $value = $disabled ? 'true' : 'false';

        $this->runWpCli($site->install_path, "config set DISABLE_WP_CRON {$value} --raw", $username);
        $site->update(['wp_cron_disabled' => $disabled]);

        if ($disabled) {
            // Add system cron job
            $cronCmd = "*/5 * * * * /usr/local/bin/wp cron event run --due-now --path={$site->install_path} --quiet";
            Process::timeout(10)->run("(crontab -u {$username} -l 2>/dev/null | grep -v 'wp cron event run.*{$site->install_path}'; echo '{$cronCmd}') | crontab -u {$username} - 2>/dev/null");
        } else {
            // Remove system cron job
            Process::timeout(10)->run("crontab -u {$username} -l 2>/dev/null | grep -v 'wp cron event run.*{$site->install_path}' | crontab -u {$username} - 2>/dev/null");
        }
    }

    public function setWpCronInterval(WordPressSite $site, int $intervalMinutes): void
    {
        $site->update(['wp_cron_interval' => $intervalMinutes]);

        if ($site->wp_cron_disabled && $intervalMinutes > 0) {
            $username = $this->getUsername($site);
            $cronCmd = "*/{$intervalMinutes} * * * * /usr/local/bin/wp cron event run --due-now --path={$site->install_path} --quiet";
            Process::timeout(10)->run("(crontab -u {$username} -l 2>/dev/null | grep -v 'wp cron event run.*{$site->install_path}'; echo '{$cronCmd}') | crontab -u {$username} - 2>/dev/null");
        }
    }

    public function runWpCronNow(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $result = $this->runWpCli($site->install_path, 'cron event run --due-now', $username);
        return [
            'success' => $result['success'],
            'message' => $result['success'] ? "WP-Cron events executed for {$site->domain}" : "WP-Cron failed: " . ($result['error'] ?? ''),
            'output' => $result['output'] ?? '',
        ];
    }

    public function getWpCronStatus(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $result = $this->runWpCli($site->install_path, 'cron event list --format=json', $username);
        $events = $result['success'] ? json_decode($result['output'], true) : [];

        // Check system crontab
        $cronCheck = Process::timeout(10)->run("crontab -u {$username} -l 2>/dev/null | grep 'wp cron event run'");
        $hasSystemCron = !empty(trim($cronCheck->output()));

        return [
            'wp_cron_disabled' => $site->wp_cron_disabled,
            'has_system_cron' => $hasSystemCron,
            'interval' => $site->wp_cron_interval,
            'events' => $events,
            'event_count' => count($events),
        ];
    }

    // =========================================================================
    // PHASE 7: Performance Diagnostics
    // =========================================================================

    public function getPerformanceReport(WordPressSite $site): array
    {
        $username = $this->getUsername($site);
        $report = [];

        // TTFB
        $ttfb = Process::timeout(15)->run("curl -so /dev/null -w '%{time_starttransfer}' -H 'Host: {$site->domain}' http://127.0.0.1/ 2>/dev/null");
        $report['ttfb_ms'] = round((float) trim($ttfb->output()) * 1000, 1);

        // Cache HIT/MISS (only if Varnish active)
        if ($site->varnish_enabled) {
            $cacheTest = $this->testVarnishCacheHit($site);
            $report['varnish_cache'] = $cacheTest['header'] ?? 'N/A';
            $report['varnish_hit'] = $cacheTest['is_hit'] ?? false;
        }

        // Redis status
        if ($site->redis_enabled) {
            $redisStatus = $this->getRedisStatus($site);
            $report['redis_connected'] = $redisStatus['connected'] ?? false;
            $report['redis_output'] = $redisStatus['output'] ?? '';
        }

        // OPcache
        $opcache = $this->runWpCli($site->install_path, 'eval "echo json_encode(function_exists(\"opcache_get_status\") ? @opcache_get_status(false) : null);"', $username);
        $report['opcache'] = $opcache['success'] ? json_decode($opcache['output'], true) : null;

        // Database size
        $dbSize = $this->runWpCli($site->install_path, 'db query "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS size_mb FROM information_schema.tables WHERE table_schema = DATABASE();" --skip-column-names', $username);
        $report['db_size_mb'] = $dbSize['success'] ? trim($dbSize['output']) : 'N/A';

        // Autoloaded options
        $autoload = $this->runWpCli($site->install_path, 'option list --autoload=on --format=count', $username);
        $report['autoloaded_options'] = $autoload['success'] ? (int) trim($autoload['output']) : 0;

        $autoloadSize = $this->runWpCli($site->install_path, 'db query "SELECT ROUND(SUM(LENGTH(option_value)) / 1024, 2) AS size_kb FROM wp_options WHERE autoload = \"yes\";" --skip-column-names', $username);
        $report['autoloaded_size_kb'] = $autoloadSize['success'] ? trim($autoloadSize['output']) : 'N/A';

        // Plugin/theme counts
        $report['plugin_count'] = count($this->listPlugins($site));
        $report['theme_count'] = count($this->listThemes($site));

        // Object cache drop-in
        $dropin = ShellService::runAsUser($username, "test -f " . escapeshellarg($site->install_path) . "/wp-content/object-cache.php && echo EXISTS", 10, "/home/{$username}");
        $report['object_cache_dropin'] = str_contains($dropin['output'] ?? '', 'EXISTS');

        // PHP-FPM pool status
        $report['php_fpm'] = $this->getPhpFpmPoolStatus($site);

        // WP-Cron status
        $report['wp_cron'] = $this->getWpCronStatus($site);

        // Disk usage
        $report['disk_usage'] = $this->getDiskUsage($site);

        return $report;
    }

    // =========================================================================
    // PHASE 8: Package Limits (enforcement helpers)
    // =========================================================================

    public function checkPackageLimit(WordPressSite $site, string $limit): mixed
    {
        $account = UserAccount::find($site->user_account_id);
        if (!$account) {
            return null;
        }

        $pkg = $account->package ?? 'default';
        $limits = config("openpanel.packages.{$pkg}", []);

        return $limits[$limit] ?? null;
    }

    public function canEnableRedis(WordPressSite $site): bool
    {
        $allowed = $this->checkPackageLimit($site, 'redis_allowed');
        return $allowed === null || $allowed === true;
    }

    public function canCreateStaging(WordPressSite $site): bool
    {
        $allowed = $this->checkPackageLimit($site, 'staging_allowed');
        if ($allowed === false) return false;

        $maxStaging = $this->checkPackageLimit($site, 'max_staging_sites');
        if ($maxStaging !== null) {
            $currentStaging = WordPressSite::where('user_account_id', $site->user_account_id)
                ->where('domain', 'like', 'staging.%')
                ->count();
            return $currentStaging < $maxStaging;
        }

        return true;
    }
}
