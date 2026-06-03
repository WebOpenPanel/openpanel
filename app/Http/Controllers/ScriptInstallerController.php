<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Process\Factory as ProcessFactory;
use Illuminate\Support\Str;

class ScriptInstallerController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    protected array $scripts = [
        'wordpress' => [
            'name' => 'WordPress',
            'description' => 'Popular CMS and blogging platform',
            'version' => 'latest',
            'download_url' => 'https://wordpress.org/latest.tar.gz',
            'db_required' => true,
        ],
        'joomla' => [
            'name' => 'Joomla',
            'description' => 'Content management system',
            'version' => 'latest',
            'download_url' => 'https://downloads.joomla.org/cms/joomla4/latest/Joomla_4-Stable-Full_Package.tar.gz',
            'db_required' => true,
        ],
        'drupal' => [
            'name' => 'Drupal',
            'description' => 'Enterprise content management',
            'version' => 'latest',
            'download_url' => 'https://www.drupal.org/download-latest/tar.gz',
            'db_required' => true,
        ],
        'laravel' => [
            'name' => 'Laravel',
            'description' => 'PHP web application framework',
            'version' => 'latest',
            'download_url' => null,
            'db_required' => true,
        ],
        'mediawiki' => [
            'name' => 'MediaWiki',
            'description' => 'Wiki software (powers Wikipedia)',
            'version' => 'latest',
            'download_url' => 'https://releases.wikimedia.org/mediawiki/1.41/mediawiki-1.41.1.tar.gz',
            'db_required' => true,
        ],
        'nextcloud' => [
            'name' => 'Nextcloud',
            'description' => 'Self-hosted productivity platform',
            'version' => 'latest',
            'download_url' => 'https://download.nextcloud.com/server/releases/latest.tar.bz2',
            'db_required' => true,
        ],
        'prestashop' => [
            'name' => 'PrestaShop',
            'description' => 'E-commerce solution',
            'version' => 'latest',
            'download_url' => 'https://download.prestashop.com/download/releases/prestashop_8.1.3.zip',
            'db_required' => true,
        ],
        'phpbb' => [
            'name' => 'phpBB',
            'description' => 'Forum software',
            'version' => 'latest',
            'download_url' => 'https://download.phpbb.com/pub/release/3.3.11/phpBB-3.3.11.tar.gz',
            'db_required' => true,
        ],
    ];

    public function index()
    {
        return view('script-installer.index', ['scripts' => $this->scripts]);
    }

    public function install(Request $request)
    {
        $request->validate([
            'script' => 'required|string|in:' . implode(',', array_keys($this->scripts)),
            'domain' => 'required|string',
            'username' => 'required|string|regex:/^[a-z0-9_]+$/',
            'directory' => 'nullable|string|regex:/^[a-zA-Z0-9_\-\/]+$/',
            'db_name' => 'nullable|string|regex:/^[a-zA-Z0-9_]+$/',
            'db_user' => 'nullable|string|regex:/^[a-zA-Z0-9_]+$/',
            'db_pass' => 'nullable|string|max:128',
        ]);

        $scriptKey = $request->input('script');
        $script = $this->scripts[$scriptKey];
        $username = $request->input('username');
        $directory = $request->input('directory');
        $home = "/home/{$username}";
        $webRoot = $directory ? "{$home}/public_html/{$directory}" : "{$home}/public_html";

        if (!is_dir($home)) {
            return back()->with('error', "User {$username} not found.");
        }

        if (!is_dir($webRoot)) {
            mkdir($webRoot, 0755, true);
        }

        if ($scriptKey === 'laravel') {
            return $this->installLaravel($username, $webRoot);
        }

        $downloadUrl = $script['download_url'];
        if (!$downloadUrl) {
            return back()->with('error', 'No download URL configured for this script.');
        }

        $ext = str_ends_with($downloadUrl, '.zip') ? 'zip' : 'tar.gz';
        $archivePath = "/tmp/" . escapeshellarg("{$scriptKey}.{$ext}");
        $webRootEsc = escapeshellarg($webRoot);
        $userEsc = escapeshellarg($username);

        $this->process()->run("curl -fsSL " . escapeshellarg($downloadUrl) . " -o {$archivePath} 2>&1");

        if (!file_exists(trim($archivePath, "'")) || filesize(trim($archivePath, "'")) < 1000) {
            return back()->with('error', 'Failed to download script archive.');
        }

        if ($ext === 'zip') {
            $this->process()->run("cd {$webRootEsc} && unzip -o {$archivePath} 2>&1");
        } else {
            $this->process()->run("cd {$webRootEsc} && tar -xzf {$archivePath} --strip-components=1 2>&1");
        }

        @unlink(trim($archivePath, "'"));

        $this->process()->run("chown -R {$userEsc}:{$userEsc} {$webRootEsc} 2>&1");
        $this->process()->run("find {$webRootEsc} -type d -exec chmod 755 {} \; 2>&1");
        $this->process()->run("find {$webRootEsc} -type f -exec chmod 644 {} \; 2>&1");

        if ($script['db_required'] && $request->input('db_name')) {
            $dbName = escapeshellarg($request->input('db_name'));
            $dbUser = escapeshellarg($request->input('db_user') ?? $request->input('db_name'));
            $dbPass = escapeshellarg($request->input('db_pass') ?: Str::random(16));

            $this->process()->run("mysql -e \"CREATE DATABASE IF NOT EXISTS {$dbName};\" 2>&1");
            $this->process()->run("mysql -e \"CREATE USER IF NOT EXISTS {$dbUser}@'localhost' IDENTIFIED BY {$dbPass};\" 2>&1");
            $this->process()->run("mysql -e \"GRANT ALL PRIVILEGES ON {$dbName}.* TO {$dbUser}@'localhost'; FLUSH PRIVILEGES;\" 2>&1");
        }

        return back()->with('success', "{$script['name']} installed to {$webRoot}");
    }

    protected function installLaravel(string $username, string $webRoot): mixed
    {
        $home = "/home/{$username}";
        $webRootEsc = escapeshellarg($webRoot);
        $userEsc = escapeshellarg($username);

        $this->process()->run("cd " . escapeshellarg($home) . " && composer create-project laravel/laravel {$webRootEsc} --prefer-dist 2>&1");
        $this->process()->run("chown -R {$userEsc}:{$userEsc} {$webRootEsc} 2>&1");

        return back()->with('success', "Laravel installed to {$webRoot}");
    }

    public function checkStatus(Request $request)
    {
        $request->validate([
            'username' => 'required|string|regex:/^[a-z0-9_]+$/',
            'directory' => 'nullable|string',
        ]);

        $username = $request->input('username');
        $directory = $request->input('directory');
        $home = "/home/{$username}";
        $webRoot = $directory ? "{$home}/public_html/{$directory}" : "{$home}/public_html";

        $installed = [];
        foreach ($this->scripts as $key => $script) {
            $marker = match ($key) {
                'wordpress' => 'wp-config.php',
                'joomla' => 'configuration.php',
                'drupal' => 'sites/default/settings.php',
                'laravel' => 'artisan',
                'mediawiki' => 'LocalSettings.php',
                'nextcloud' => 'config/config.php',
                'prestashop' => 'config/settings.inc.php',
                'phpbb' => 'config.php',
                default => null,
            };

            if ($marker && file_exists("{$webRoot}/{$marker}")) {
                $installed[] = $key;
            }
        }

        return new JsonResponse(['installed' => $installed]);
    }
}
