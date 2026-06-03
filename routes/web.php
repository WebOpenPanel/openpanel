<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserAccountController;
use App\Http\Controllers\DomainController;
use App\Http\Controllers\DnsController;
use App\Http\Controllers\MysqlController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\PackageController;
use App\Http\Controllers\CronController;
use App\Http\Controllers\SecurityController;
use App\Http\Controllers\SslController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\BackupController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\ServerController;
use App\Http\Controllers\FileManagerController;
use App\Http\Controllers\LogController;
use App\Http\Controllers\FtpController;
use App\Http\Controllers\IpController;
use App\Http\Controllers\NodejsController;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\TomcatController;
use App\Http\Controllers\VarnishController;
use App\Http\Controllers\MonitController;
use App\Http\Controllers\MigrationController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ThemeController;
use App\Http\Controllers\MessengerController;
use App\Http\Controllers\EmailStatsController;
use App\Http\Controllers\CgtopController;
use App\Http\Controllers\ServercastController;
use App\Http\Controllers\CloudLinuxController;
use App\Http\Controllers\AntiSpamController;
use App\Http\Controllers\ApacheBuilderController;
use App\Http\Controllers\ServiceMonitorController;
use App\Http\Controllers\ClusterController;
use App\Http\Controllers\WhmcsController;
use App\Http\Controllers\PythonController;
use App\Http\Controllers\PeclController;
use App\Http\Controllers\ObjectStorageController;
use App\Http\Controllers\BandwidthController;
use App\Http\Controllers\RblController;
use App\Http\Controllers\PolicydController;
use App\Http\Controllers\SnuffleupagusController;
use App\Http\Controllers\HelpDeskController;
use App\Http\Controllers\IncidentsController;
use App\Http\Controllers\WebServerTemplateController;
use App\Http\Controllers\WebServerWizardController;
use App\Http\Controllers\KernelSecurityController;
use App\Http\Controllers\PostfixListController;
use App\Http\Controllers\WebScanController;
use App\Http\Controllers\ClamavController;
use App\Http\Controllers\IcecastController;
use App\Http\Controllers\UserPanel\UserDashboardController;
use App\Http\Controllers\UserPanel\UserDomainController;
use App\Http\Controllers\UserPanel\UserEmailController;
use App\Http\Controllers\UserPanel\UserMysqlController;
use App\Http\Controllers\UserPanel\UserFileController;
use App\Http\Controllers\UserPanel\UserFtpController;
use App\Http\Controllers\UserPanel\UserCronController;
use App\Http\Controllers\UserPanel\UserSslController;
use App\Http\Controllers\UserPanel\UserDnsController;
use App\Http\Controllers\UserPanel\UserStatsController;
use App\Http\Controllers\UserPanel\UserBackupController;
use App\Http\Controllers\UserPanel\UserWordPressController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('login'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

Route::middleware(['auth', \App\Http\Middleware\AdminMiddleware::class, \App\Http\Middleware\ActivityLogger::class])->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // User Accounts
    Route::get('accounts', [UserAccountController::class, 'index'])->name('accounts.index');
    Route::get('accounts/create', [UserAccountController::class, 'create'])->name('accounts.create');
    Route::post('accounts', [UserAccountController::class, 'store'])->name('accounts.store');
    Route::get('accounts/{username}', [UserAccountController::class, 'show'])->name('accounts.show');
    Route::get('accounts/{username}/edit', [UserAccountController::class, 'edit'])->name('accounts.edit');
    Route::put('accounts/{username}', [UserAccountController::class, 'update'])->name('accounts.update');
    Route::delete('accounts/{username}', [UserAccountController::class, 'destroy'])->name('accounts.destroy');
    Route::post('accounts/{username}/suspend', [UserAccountController::class, 'suspend'])->name('accounts.suspend');
    Route::post('accounts/{username}/unsuspend', [UserAccountController::class, 'unsuspend'])->name('accounts.unsuspend');

    // Domains
    Route::resource('domains', DomainController::class);
    Route::get('subdomains', [DomainController::class, 'subdomains'])->name('subdomains');
    Route::delete('subdomains/{subdomain}', [DomainController::class, 'destroySubdomain'])->name('subdomains.destroy');
    Route::get('domain-aliases', [DomainController::class, 'aliases'])->name('domain-aliases');
    Route::delete('domain-aliases/{alias}', [DomainController::class, 'destroyAlias'])->name('domain-aliases.destroy');

    // DNS
    Route::prefix('dns')->name('dns.')->group(function () {
        Route::get('/', [DnsController::class, 'index'])->name('index');
        Route::get('/create', [DnsController::class, 'create'])->name('create');
        Route::post('/', [DnsController::class, 'store'])->name('store');
        Route::get('/{zone}', [DnsController::class, 'show'])->name('show');
        Route::delete('/{zone}', [DnsController::class, 'destroy'])->name('destroy');
        Route::post('/{zone}/records', [DnsController::class, 'addRecord'])->name('add-record');
        Route::delete('/records/{record}', [DnsController::class, 'deleteRecord'])->name('delete-record');
        Route::put('/records/{record}', [DnsController::class, 'editRecord'])->name('edit-record');
        Route::post('/rebuild-all', [DnsController::class, 'rebuildAll'])->name('rebuild-all');
        Route::post('/{zone}/rebuild', [DnsController::class, 'rebuildZone'])->name('rebuild-zone');
        Route::get('/templates', [DnsController::class, 'templates'])->name('templates');
        Route::get('/nameservers', [DnsController::class, 'nameservers'])->name('nameservers');
        Route::post('/nameservers', [DnsController::class, 'saveNameservers'])->name('save-nameservers');
        Route::post('/dkim/{domain}', [DnsController::class, 'addDkim'])->name('add-dkim');
        Route::post('/dkim-all', [DnsController::class, 'addDkimAll'])->name('add-dkim-all');
        Route::post('/spf/{domain}', [DnsController::class, 'addSpf'])->name('add-spf');
    });

    // MySQL
    Route::prefix('mysql')->name('mysql.')->group(function () {
        Route::get('/', [MysqlController::class, 'index'])->name('index');
        Route::post('/database', [MysqlController::class, 'createDatabase'])->name('create-database');
        Route::delete('/database/{database}', [MysqlController::class, 'destroyDatabase'])->name('destroy-database');
        Route::post('/user', [MysqlController::class, 'createUser'])->name('create-user');
        Route::delete('/user/{mysqlUser}', [MysqlController::class, 'deleteUser'])->name('delete-user');
        Route::post('/assign', [MysqlController::class, 'assignUser'])->name('assign-user');
        Route::post('/revoke', [MysqlController::class, 'revokeUser'])->name('revoke-user');
        Route::get('/status', [MysqlController::class, 'status'])->name('status');
        Route::get('/processes', [MysqlController::class, 'processes'])->name('processes');
        Route::post('/processes/{id}/kill', [MysqlController::class, 'killProcess'])->name('kill-process');
        Route::get('/config', [MysqlController::class, 'config'])->name('config');
        Route::post('/config', [MysqlController::class, 'saveConfig'])->name('save-config');
        Route::post('/optimize', [MysqlController::class, 'optimize'])->name('optimize');
        Route::post('/repair', [MysqlController::class, 'repair'])->name('repair');
        Route::get('/postgresql', [MysqlController::class, 'postgresql'])->name('postgresql');
        Route::get('/mongodb', [MysqlController::class, 'mongodb'])->name('mongodb');
    });

    // Email
    Route::prefix('email')->name('email.')->group(function () {
        Route::get('/', [EmailController::class, 'index'])->name('index');
        Route::post('/account', [EmailController::class, 'createAccount'])->name('create-account');
        Route::delete('/account/{emailAccount}', [EmailController::class, 'destroyAccount'])->name('destroy-account');
        Route::get('/forwarders', [EmailController::class, 'forwarders'])->name('forwarders');
        Route::post('/forwarder', [EmailController::class, 'createForwarder'])->name('create-forwarder');
        Route::delete('/forwarder/{forwarder}', [EmailController::class, 'destroyForwarder'])->name('destroy-forwarder');
        Route::get('/autoresponders', [EmailController::class, 'autoresponders'])->name('autoresponders');
        Route::post('/autoresponder', [EmailController::class, 'createAutoresponder'])->name('create-autoresponder');
        Route::delete('/autoresponder/{autoresponder}', [EmailController::class, 'destroyAutoresponder'])->name('destroy-autoresponder');
        Route::get('/queue', [EmailController::class, 'mailQueue'])->name('queue');
        Route::post('/queue/flush', [EmailController::class, 'flushQueue'])->name('queue-flush');
        Route::post('/queue/delete', [EmailController::class, 'deleteQueue'])->name('queue-delete');
        Route::get('/dkim', [EmailController::class, 'dkim'])->name('dkim');
        Route::post('/pipe', [EmailController::class, 'addPipe'])->name('add-pipe');
        Route::delete('/pipe', [EmailController::class, 'removePipe'])->name('remove-pipe');
        Route::get('/mx', [EmailController::class, 'mxEntry'])->name('mx');
        Route::post('/mx', [EmailController::class, 'saveMx'])->name('save-mx');
        Route::get('/postfix-config', [EmailController::class, 'postfixConfig'])->name('postfix-config');
        Route::post('/postfix-config', [EmailController::class, 'savePostfixConfig'])->name('save-postfix-config');
        Route::get('/dovecot-config', [EmailController::class, 'dovecotConfig'])->name('dovecot-config');
        Route::post('/dovecot-config', [EmailController::class, 'saveDovecotConfig'])->name('save-dovecot-config');
        Route::get('/mail-log', [EmailController::class, 'mailLog'])->name('mail-log');
        Route::get('/explorer', [EmailController::class, 'explorer'])->name('explorer');
    });

    // Packages
    Route::resource('packages', PackageController::class);

    // Cron Jobs
    Route::prefix('cron')->name('cron.')->group(function () {
        Route::get('/', [CronController::class, 'index'])->name('index');
        Route::post('/', [CronController::class, 'store'])->name('store');
        Route::delete('/{cronJob}', [CronController::class, 'destroy'])->name('destroy');
        Route::post('/{cronJob}/toggle', [CronController::class, 'toggle'])->name('toggle');
        Route::get('/system', [CronController::class, 'systemCron'])->name('system');
        Route::post('/system', [CronController::class, 'saveSystemCron'])->name('save-system');
        Route::get('/log', [CronController::class, 'cronLog'])->name('log');
    });

    // SSL
    Route::prefix('ssl')->name('ssl.')->group(function () {
        Route::get('/', [SslController::class, 'index'])->name('index');
        Route::get('/generate', [SslController::class, 'generate'])->name('generate');
        Route::post('/generate-self-signed', [SslController::class, 'generateSelfSigned'])->name('generate-self-signed');
        Route::post('/install', [SslController::class, 'install'])->name('install');
        Route::delete('/{certificate}', [SslController::class, 'destroy'])->name('destroy');
        Route::get('/letsencrypt', [SslController::class, 'letsEncrypt'])->name('letsencrypt');
        Route::post('/letsencrypt/issue', [SslController::class, 'letsEncryptIssue'])->name('letsencrypt-issue');
        Route::post('/letsencrypt/renew', [SslController::class, 'letsEncryptRenew'])->name('letsencrypt-renew');
        Route::post('/add-san', [SslController::class, 'addSan'])->name('add-san');
        Route::post('/generate-csr', [SslController::class, 'generateCsr'])->name('generate-csr');
        Route::post('/validate-dns', [SslController::class, 'validateDomainDns'])->name('validate-dns');
        Route::post('/force-renew-all', [SslController::class, 'forceRenewAll'])->name('force-renew-all');
        Route::get('/info/{domain}', [SslController::class, 'getInfo'])->name('info');
        Route::get('/panel', [SslController::class, 'panelSsl'])->name('panel');
        Route::post('/panel/issue', [SslController::class, 'panelSslIssue'])->name('panel-issue');
        Route::post('/panel/renew', [SslController::class, 'panelSslRenew'])->name('panel-renew');
        Route::post('/panel/revoke', [SslController::class, 'panelSslRevoke'])->name('panel-revoke');
        Route::post('/panel/self-signed', [SslController::class, 'panelSslSelfSigned'])->name('panel-self-signed');
        Route::post('/panel/install-certbot', [SslController::class, 'installCertbot'])->name('panel-install-certbot');
    });

    // Security
    Route::prefix('security')->name('security.')->group(function () {
        Route::get('/firewall', [SecurityController::class, 'firewall'])->name('firewall');
        Route::post('/firewall', [SecurityController::class, 'addFirewallRule'])->name('add-firewall-rule');
        Route::delete('/firewall/{rule}', [SecurityController::class, 'deleteFirewallRule'])->name('delete-firewall-rule');
        Route::post('/firewall/{rule}/toggle', [SecurityController::class, 'toggleFirewallRule'])->name('toggle-firewall-rule');
        Route::get('/blocked-ips', [SecurityController::class, 'blockedIps'])->name('blocked-ips');
        Route::post('/block-ip', [SecurityController::class, 'blockIp'])->name('block-ip');
        Route::delete('/blocked-ips/{blockedIp}', [SecurityController::class, 'unblockIp'])->name('unblock-ip');
        Route::get('/allowed-ips', [SecurityController::class, 'allowedIps'])->name('allowed-ips');
        Route::post('/allow-ip', [SecurityController::class, 'allowIp'])->name('allow-ip');
        Route::delete('/allowed-ips/{allowedIp}', [SecurityController::class, 'removeAllowedIp'])->name('remove-allowed-ip');
        Route::get('/csf', [SecurityController::class, 'csf'])->name('csf');
        Route::post('/csf/action', [SecurityController::class, 'csfAction'])->name('csf-action');
        Route::post('/csf/allow', [SecurityController::class, 'csfAllowIp'])->name('csf-allow');
        Route::post('/csf/deny', [SecurityController::class, 'csfDenyIp'])->name('csf-deny');
        Route::post('/csf/unblock', [SecurityController::class, 'csfUnblockIp'])->name('csf-unblock');
        Route::get('/csf/config', [SecurityController::class, 'csfConfig'])->name('csf-config');
        Route::post('/csf/config', [SecurityController::class, 'csfSaveConfig'])->name('csf-save-config');
        Route::get('/mod-security', [SecurityController::class, 'modSecurity'])->name('mod-security');
        Route::post('/mod-security/toggle', [SecurityController::class, 'modSecurityToggle'])->name('mod-security-toggle');
        Route::post('/mod-security/rules', [SecurityController::class, 'modSecuritySaveRules'])->name('mod-security-save-rules');
        Route::get('/maldet', [SecurityController::class, 'maldet'])->name('maldet');
        Route::post('/maldet/action', [SecurityController::class, 'maldetAction'])->name('maldet-action');
        Route::get('/rkhunter', [SecurityController::class, 'rkhunter'])->name('rkhunter');
        Route::post('/rkhunter/action', [SecurityController::class, 'rkhunterAction'])->name('rkhunter-action');
        Route::get('/lynis', [SecurityController::class, 'lynis'])->name('lynis');
        Route::post('/lynis/action', [SecurityController::class, 'lynisAction'])->name('lynis-action');
        Route::get('/cgroups', [SecurityController::class, 'cgroups'])->name('cgroups');
        Route::post('/cgroups/action', [SecurityController::class, 'cgroupsAction'])->name('cgroups-action');
        Route::post('/cgroups/limit', [SecurityController::class, 'cgroupsSetLimit'])->name('cgroups-limit');
        Route::get('/login-security', [SecurityController::class, 'loginSecurity'])->name('login-security');
        Route::get('/shell-access', [SecurityController::class, 'shellAccess'])->name('shell-access');
        Route::post('/shell', [SecurityController::class, 'setShell'])->name('set-shell');
        Route::get('/kernel', [SecurityController::class, 'kernel'])->name('kernel');
        Route::post('/kernel/update', [SecurityController::class, 'kernelUpdate'])->name('kernel-update');
        Route::get('/symlink-scan', [SecurityController::class, 'symlinkScan'])->name('symlink-scan');
        Route::get('/iptables', [SecurityController::class, 'iptables'])->name('iptables');
        Route::post('/iptables/flush', [SecurityController::class, 'iptablesFlush'])->name('iptables-flush');
    });

    // Services
    Route::prefix('services')->name('services.')->group(function () {
        Route::get('/', [ServiceController::class, 'index'])->name('index');
        Route::post('/{service}/restart', [ServiceController::class, 'restart'])->name('restart');
        Route::post('/{service}/toggle', [ServiceController::class, 'toggle'])->name('toggle');
        Route::post('/{service}/toggle-boot', [ServiceController::class, 'toggleBoot'])->name('toggle-boot');
        Route::post('/{service}/toggle-monitor', [ServiceController::class, 'toggleMonitor'])->name('toggle-monitor');
        Route::post('/{service}/action', [ServiceController::class, 'action'])->name('action');
        Route::get('/config/{service}', [ServiceController::class, 'config'])->name('config');
        Route::post('/config/{service}', [ServiceController::class, 'saveConfig'])->name('save-config');
    });

    // Backups
    Route::prefix('backups')->name('backups.')->group(function () {
        Route::get('/', [BackupController::class, 'index'])->name('index');
        Route::get('/config', [BackupController::class, 'config'])->name('config');
        Route::post('/config', [BackupController::class, 'saveConfig'])->name('save-config');
        Route::post('/generate', [BackupController::class, 'generate'])->name('generate');
        Route::post('/{backup}/restore', [BackupController::class, 'restore'])->name('restore');
        Route::delete('/{backup}', [BackupController::class, 'destroy'])->name('destroy');
        Route::get('/download/{backup}', [BackupController::class, 'download'])->name('download');
        Route::post('/cleanup', [BackupController::class, 'cleanup'])->name('cleanup');
        Route::get('/manager', [BackupController::class, 'managerIndex'])->name('manager');
        Route::post('/manager/save', [BackupController::class, 'managerSave'])->name('manager-save');
        Route::delete('/manager/{id}', [BackupController::class, 'managerDelete'])->name('manager-delete');
        Route::post('/manager/{id}/toggle', [BackupController::class, 'managerToggle'])->name('manager-toggle');
        Route::post('/manager/{id}/run', [BackupController::class, 'managerRun'])->name('manager-run');
        Route::post('/manager/{id}/default', [BackupController::class, 'managerSetDefault'])->name('manager-default');
        Route::get('/manager/monitor', [BackupController::class, 'managerMonitor'])->name('manager-monitor');
        Route::get('/manager/{id}/edit', [BackupController::class, 'managerEdit'])->name('manager-edit');
    });

    // File Manager
    Route::prefix('files')->name('files.')->group(function () {
        Route::get('/', [FileManagerController::class, 'index'])->name('index');
        Route::get('/view', [FileManagerController::class, 'view'])->name('view');
        Route::get('/edit', [FileManagerController::class, 'edit'])->name('edit');
        Route::post('/save', [FileManagerController::class, 'save'])->name('save');
        Route::post('/delete', [FileManagerController::class, 'delete'])->name('delete');
        Route::post('/rename', [FileManagerController::class, 'rename'])->name('rename');
        Route::post('/mkdir', [FileManagerController::class, 'createDirectory'])->name('mkdir');
        Route::post('/upload', [FileManagerController::class, 'upload'])->name('upload');
        Route::post('/permissions', [FileManagerController::class, 'permissions'])->name('permissions');
        Route::post('/compress', [FileManagerController::class, 'compress'])->name('compress');
        Route::post('/extract', [FileManagerController::class, 'extract'])->name('extract');
        Route::get('/download', [FileManagerController::class, 'download'])->name('download');
        Route::get('/search', [FileManagerController::class, 'search'])->name('search');
        Route::get('/disk-usage', [FileManagerController::class, 'diskUsage'])->name('disk-usage');
    });

    // Logs
    Route::prefix('logs')->name('logs.')->group(function () {
        Route::get('/', [LogController::class, 'index'])->name('index');
        Route::get('/view', [LogController::class, 'view'])->name('view');
        Route::get('/search', [LogController::class, 'search'])->name('search');
    });

    // FTP
    Route::prefix('ftp')->name('ftp.')->group(function () {
        Route::get('/', [FtpController::class, 'index'])->name('index');
        Route::post('/', [FtpController::class, 'create'])->name('create');
        Route::delete('/', [FtpController::class, 'destroy'])->name('destroy');
        Route::post('/password', [FtpController::class, 'changePassword'])->name('change-password');
        Route::get('/sessions', [FtpController::class, 'sessions'])->name('sessions');
        Route::post('/sessions/kill', [FtpController::class, 'killSession'])->name('kill-session');
        Route::get('/config', [FtpController::class, 'configuration'])->name('config');
        Route::post('/config', [FtpController::class, 'saveConfig'])->name('save-config');
        Route::post('/restart', [FtpController::class, 'restart'])->name('restart');
    });

    // IP Manager
    Route::prefix('ip')->name('ip.')->group(function () {
        Route::get('/', [IpController::class, 'index'])->name('index');
        Route::post('/', [IpController::class, 'add'])->name('add');
        Route::delete('/', [IpController::class, 'destroy'])->name('destroy');
        Route::get('/details', [IpController::class, 'details'])->name('details');
        Route::post('/shared', [IpController::class, 'setShared'])->name('set-shared');
        Route::post('/dedicated', [IpController::class, 'setDedicated'])->name('set-dedicated');
        Route::get('/nat', [IpController::class, 'nat'])->name('nat');
        Route::post('/nat', [IpController::class, 'saveNat'])->name('save-nat');
        Route::get('/dns-resolvers', [IpController::class, 'dnsResolvers'])->name('dns-resolvers');
        Route::post('/dns-resolvers', [IpController::class, 'saveDnsResolvers'])->name('save-dns-resolvers');
    });

    // Server
    Route::prefix('server')->name('server.')->group(function () {
        Route::get('/phpinfo', [ServerController::class, 'phpInfo'])->name('phpinfo');
        Route::get('/processes', [ServerController::class, 'processList'])->name('processes');
        Route::post('/processes/kill', [ServerController::class, 'killProcess'])->name('kill-process');
        Route::get('/network', [ServerController::class, 'networkConfig'])->name('network');
        Route::get('/disk', [ServerController::class, 'diskUsage'])->name('disk');
        Route::get('/services', [ServerController::class, 'serviceList'])->name('services');
        Route::post('/service/{action}/{service}', [ServerController::class, 'serviceAction'])->name('service-action');
        Route::get('/hostname', [ServerController::class, 'hostname'])->name('hostname');
        Route::post('/hostname', [ServerController::class, 'setHostname'])->name('set-hostname');
        Route::get('/time', [ServerController::class, 'dateTime'])->name('time');
        Route::post('/timezone', [ServerController::class, 'setTimezone'])->name('set-timezone');
        Route::post('/reboot', [ServerController::class, 'reboot'])->name('reboot');
        Route::post('/shutdown', [ServerController::class, 'shutdown'])->name('shutdown');
        Route::get('/ssh-keys', [ServerController::class, 'sshKeys'])->name('ssh-keys');
        Route::post('/ssh-keys/generate', [ServerController::class, 'generateSshKey'])->name('generate-ssh-key');
        Route::post('/change-root-password', [ServerController::class, 'changeRootPassword'])->name('change-root-password');
        Route::get('/yum', [ServerController::class, 'yumPackages'])->name('yum');
        Route::post('/yum/install', [ServerController::class, 'yumInstall'])->name('yum-install');
        Route::post('/yum/update', [ServerController::class, 'yumUpdate'])->name('yum-update');
        Route::get('/webserver', [ServerController::class, 'webserver'])->name('webserver');
        Route::post('/webserver', [ServerController::class, 'setWebserver'])->name('set-webserver');
        Route::get('/php', [ServerController::class, 'phpManager'])->name('php');
        Route::post('/php/default', [ServerController::class, 'setPhpDefault'])->name('set-php-default');
        Route::get('/terminal', [ServerController::class, 'terminal'])->name('terminal');
        Route::post('/terminal', [ServerController::class, 'runCommand'])->name('run-command');
    });

    // Web Stack Manager
    Route::prefix('web-stack')->name('web-stack.')->group(function () {
        Route::get('/', [\App\Http\Controllers\WebStackController::class, 'index'])->name('index');
        Route::post('/install', [\App\Http\Controllers\WebStackController::class, 'install'])->name('install');
        Route::post('/validate', [\App\Http\Controllers\WebStackController::class, 'validate'])->name('validate');
        Route::post('/switch', [\App\Http\Controllers\WebStackController::class, 'switchStack'])->name('switch');
        Route::post('/rollback', [\App\Http\Controllers\WebStackController::class, 'rollback'])->name('rollback');
        Route::get('/health', [\App\Http\Controllers\WebStackController::class, 'health'])->name('health');
        Route::post('/test-domain', [\App\Http\Controllers\WebStackController::class, 'testDomain'])->name('test-domain');
    });

    // Settings
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SettingsController::class, 'index'])->name('index');
        Route::put('/password', [SettingsController::class, 'changePassword'])->name('change-password');
        Route::put('/theme', [SettingsController::class, 'changeTheme'])->name('change-theme');
        Route::put('/language', [SettingsController::class, 'changeLanguage'])->name('change-language');
    });

    // Node.js Manager
    Route::prefix('nodejs')->name('nodejs.')->group(function () {
        Route::get('/', [NodejsController::class, 'index'])->name('index');
        Route::get('/apps', [NodejsController::class, 'apps'])->name('apps');
        Route::post('/install', [NodejsController::class, 'install'])->name('install');
        Route::post('/uninstall', [NodejsController::class, 'uninstall'])->name('uninstall');
        Route::post('/install-version', [NodejsController::class, 'installVersion'])->name('install-version');
        Route::post('/uninstall-version', [NodejsController::class, 'uninstallVersion'])->name('uninstall-version');
        Route::post('/set-default', [NodejsController::class, 'setDefault'])->name('set-default');
        Route::post('/app/save', [NodejsController::class, 'saveApp'])->name('app-save');
        Route::delete('/app', [NodejsController::class, 'deleteApp'])->name('app-delete');
        Route::get('/app/info', [NodejsController::class, 'appInfo'])->name('app-info');
        Route::post('/app/status', [NodejsController::class, 'handleStatus'])->name('app-status');
        Route::post('/app/npm-install', [NodejsController::class, 'npmInstall'])->name('app-npm-install');
        Route::post('/app/npm-command', [NodejsController::class, 'npmCommand'])->name('app-npm-command');
        Route::get('/app/log', [NodejsController::class, 'appLog'])->name('app-log');
        Route::get('/app/npm-install-log', [NodejsController::class, 'npmInstallLog'])->name('app-npm-install-log');
        Route::post('/save-config', [NodejsController::class, 'saveUserConfig'])->name('save-config');
        Route::get('/available', [NodejsController::class, 'listAvailable'])->name('available');
    });

    // API Keys
    Route::prefix('api')->name('api.')->group(function () {
        Route::get('/', [ApiController::class, 'index'])->name('index');
        Route::post('/generate', [ApiController::class, 'generate'])->name('generate');
        Route::delete('/', [ApiController::class, 'destroy'])->name('destroy');
    });

    // API Tokens (new)
    Route::prefix('api-tokens')->name('api-tokens.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ApiTokenController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\ApiTokenController::class, 'store'])->name('store');
        Route::post('/{id}/revoke', [\App\Http\Controllers\ApiTokenController::class, 'revoke'])->name('revoke');
        Route::post('/{id}/reactivate', [\App\Http\Controllers\ApiTokenController::class, 'reactivate'])->name('reactivate');
        Route::delete('/{id}', [\App\Http\Controllers\ApiTokenController::class, 'destroy'])->name('destroy');
    });

    // Billing Integration
    Route::prefix('billing')->name('billing.')->group(function () {
        Route::get('/', [\App\Http\Controllers\BillingProductController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\BillingProductController::class, 'store'])->name('store');
        Route::put('/{id}', [\App\Http\Controllers\BillingProductController::class, 'update'])->name('update');
        Route::delete('/{id}', [\App\Http\Controllers\BillingProductController::class, 'destroy'])->name('destroy');
    });

    // Tomcat Manager
    Route::prefix('tomcat')->name('tomcat.')->group(function () {
        Route::get('/', [TomcatController::class, 'index'])->name('index');
        Route::post('/install', [TomcatController::class, 'install'])->name('install');
        Route::post('/uninstall', [TomcatController::class, 'uninstall'])->name('uninstall');
        Route::post('/start', [TomcatController::class, 'start'])->name('start');
        Route::post('/stop', [TomcatController::class, 'stop'])->name('stop');
        Route::post('/restart', [TomcatController::class, 'restart'])->name('restart');
        Route::post('/user', [TomcatController::class, 'addUser'])->name('add-user');
        Route::delete('/user', [TomcatController::class, 'deleteUser'])->name('delete-user');
        Route::post('/deploy', [TomcatController::class, 'deploy'])->name('deploy');
        Route::delete('/undeploy', [TomcatController::class, 'undeploy'])->name('undeploy');
    });

    // Varnish Cache
    Route::prefix('varnish')->name('varnish.')->group(function () {
        Route::get('/', [VarnishController::class, 'index'])->name('index');
        Route::post('/install', [VarnishController::class, 'install'])->name('install');
        Route::post('/uninstall', [VarnishController::class, 'uninstall'])->name('uninstall');
        Route::post('/start', [VarnishController::class, 'start'])->name('start');
        Route::post('/stop', [VarnishController::class, 'stop'])->name('stop');
        Route::post('/restart', [VarnishController::class, 'restart'])->name('restart');
        Route::post('/config', [VarnishController::class, 'saveConfig'])->name('config');
        Route::post('/vcl', [VarnishController::class, 'saveVcl'])->name('vcl');
        Route::post('/clear-cache', [VarnishController::class, 'clearCache'])->name('clear-cache');
    });

    // Monit
    Route::prefix('monit')->name('monit.')->group(function () {
        Route::get('/', [MonitController::class, 'index'])->name('index');
        Route::post('/install', [MonitController::class, 'install'])->name('install');
        Route::post('/uninstall', [MonitController::class, 'uninstall'])->name('uninstall');
        Route::post('/start', [MonitController::class, 'start'])->name('start');
        Route::post('/stop', [MonitController::class, 'stop'])->name('stop');
        Route::post('/restart', [MonitController::class, 'restart'])->name('restart');
        Route::get('/summary', [MonitController::class, 'summary'])->name('summary');
        Route::get('/config/{file}/edit', [MonitController::class, 'editConfig'])->name('config-edit');
        Route::put('/config', [MonitController::class, 'saveConfig'])->name('config-save');
        Route::delete('/config', [MonitController::class, 'deleteConfig'])->name('config-delete');
        Route::post('/monitor', [MonitController::class, 'monitor'])->name('monitor');
        Route::post('/unmonitor', [MonitController::class, 'unmonitor'])->name('unmonitor');
    });

    // Migration
    Route::prefix('migration')->name('migration.')->group(function () {
        Route::get('/', [MigrationController::class, 'index'])->name('index');
        Route::post('/server', [MigrationController::class, 'serverTransfer'])->name('server');
        Route::post('/cpanel', [MigrationController::class, 'cpanelTransfer'])->name('cpanel');
        Route::get('/log', [MigrationController::class, 'log'])->name('log');
        Route::post('/restore', [MigrationController::class, 'restore'])->name('restore');
    });

    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/{id}/read', [NotificationController::class, 'markRead'])->name('read');
        Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
        Route::delete('/{id}', [NotificationController::class, 'destroy'])->name('destroy');
        Route::delete('/', [NotificationController::class, 'clear'])->name('clear');
        Route::get('/count', [NotificationController::class, 'count'])->name('count');
    });

    // Themes & Languages
    Route::prefix('themes')->name('themes.')->group(function () {
        Route::get('/', [ThemeController::class, 'index'])->name('index');
        Route::post('/theme', [ThemeController::class, 'setTheme'])->name('set-theme');
        Route::post('/language', [ThemeController::class, 'setLanguage'])->name('set-language');
        Route::get('/language/{lang}/edit', [ThemeController::class, 'editLanguage'])->name('edit-language');
        Route::put('/language', [ThemeController::class, 'saveLanguage'])->name('save-language');
    });

    // Messenger
    Route::prefix('messenger')->name('messenger.')->group(function () {
        Route::get('/', [MessengerController::class, 'index'])->name('index');
        Route::post('/send', [MessengerController::class, 'send'])->name('send');
        Route::post('/read-all', [MessengerController::class, 'markRead'])->name('read-all');
        Route::delete('/{index}', [MessengerController::class, 'destroy'])->name('destroy');
    });

    // Email Stats
    Route::prefix('email-stats')->name('email-stats.')->group(function () {
        Route::get('/', [EmailStatsController::class, 'index'])->name('index');
        Route::get('/daily', [EmailStatsController::class, 'daily'])->name('daily');
        Route::get('/weekly', [EmailStatsController::class, 'weekly'])->name('weekly');
        Route::post('/flush-queue', [EmailStatsController::class, 'flushQueue'])->name('flush-queue');
        Route::post('/delete-queue', [EmailStatsController::class, 'deleteQueue'])->name('delete-queue');
    });

    // Cgroups Top Monitor
    Route::prefix('cgtop')->name('cgtop.')->group(function () {
        Route::get('/', [CgtopController::class, 'index'])->name('index');
        Route::get('/cpu', [CgtopController::class, 'cpu'])->name('cpu');
        Route::get('/memory', [CgtopController::class, 'memory'])->name('memory');
        Route::post('/restart', [CgtopController::class, 'restart'])->name('restart');
    });

    // Servercast
    Route::prefix('servercast')->name('servercast.')->group(function () {
        Route::get('/', [ServercastController::class, 'index'])->name('index');
        Route::post('/', [ServercastController::class, 'store'])->name('store');
        Route::post('/{file}/execute', [ServercastController::class, 'execute'])->name('execute');
        Route::delete('/{file}', [ServercastController::class, 'destroy'])->name('destroy');
    });

    // CloudLinux
    Route::prefix('cloudlinux')->name('cloudlinux.')->group(function () {
        Route::get('/', [CloudLinuxController::class, 'index'])->name('index');
        Route::post('/cagefs/enable', [CloudLinuxController::class, 'enableCageFs'])->name('cagefs-enable');
        Route::post('/cagefs/disable', [CloudLinuxController::class, 'disableCageFs'])->name('cagefs-disable');
        Route::post('/cagefs/update', [CloudLinuxController::class, 'updateCageFs'])->name('cagefs-update');
        Route::post('/cagefs/enable-all', [CloudLinuxController::class, 'enableAll'])->name('cagefs-enable-all');
        Route::post('/cagefs/disable-all', [CloudLinuxController::class, 'disableAll'])->name('cagefs-disable-all');
        Route::post('/cagefs/enable-user', [CloudLinuxController::class, 'enableUser'])->name('cagefs-enable-user');
        Route::post('/cagefs/disable-user', [CloudLinuxController::class, 'disableUser'])->name('cagefs-disable-user');
        Route::post('/lve/set-user', [CloudLinuxController::class, 'setLveUser'])->name('lve-set-user');
        Route::post('/php/get-user', [CloudLinuxController::class, 'getUserPhp'])->name('php-get-user');
        Route::post('/php/set-user', [CloudLinuxController::class, 'setUserPhp'])->name('php-set-user');
    });

    // AntiSpam
    Route::prefix('antispam')->name('antispam.')->group(function () {
        Route::get('/', [AntiSpamController::class, 'index'])->name('index');
        Route::post('/install', [AntiSpamController::class, 'install'])->name('install');
        Route::post('/uninstall', [AntiSpamController::class, 'uninstall'])->name('uninstall');
        Route::get('/blocked', [AntiSpamController::class, 'listBlocked'])->name('blocked');
    });

    // Apache Builder
    Route::prefix('apache-builder')->name('apache-builder.')->group(function () {
        Route::get('/', [ApacheBuilderController::class, 'index'])->name('index');
        Route::post('/build', [ApacheBuilderController::class, 'build'])->name('build');
        Route::get('/log', [ApacheBuilderController::class, 'log'])->name('log');
    });

    // Service Monitor
    Route::prefix('service-monitor')->name('service-monitor.')->group(function () {
        Route::get('/', [ServiceMonitorController::class, 'index'])->name('index');
        Route::post('/save', [ServiceMonitorController::class, 'save'])->name('save');
    });

    // Cluster
    Route::prefix('cluster')->name('cluster.')->group(function () {
        Route::get('/', [ClusterController::class, 'index'])->name('index');
        Route::post('/init', [ClusterController::class, 'init'])->name('init');
        Route::post('/server', [ClusterController::class, 'addServer'])->name('add-server');
        Route::delete('/server/{id}', [ClusterController::class, 'removeServer'])->name('remove-server');
        Route::get('/config', [ClusterController::class, 'config'])->name('config');
        Route::post('/config', [ClusterController::class, 'saveConfig'])->name('save-config');
    });

    // WHMCS
    Route::prefix('whmcs')->name('whmcs.')->group(function () {
        Route::get('/', [WhmcsController::class, 'index'])->name('index');
        Route::post('/save', [WhmcsController::class, 'save'])->name('save');
        Route::post('/test', [WhmcsController::class, 'test'])->name('test');
    });

    // Drop Cache
    Route::post('/drop-cache', function () {
        \App\Services\DropCacheService::drop();
        return back()->with('success', 'Cache dropped.');
    })->name('drop-cache');

    // Root Password
    Route::get('/root-password', function () {
        return view('root_password');
    })->name('root-password');
    Route::post('/root-password', function (\Illuminate\Http\Request $request) {
        $request->validate(['password' => 'required|string|min:6', 'password_confirmation' => 'required|same:password']);
        $result = \App\Services\RootPasswordService::changePassword($request->password);
        return back()->with($result['success'] ? 'success' : 'error', $result['output']);
    })->name('root-password-change');

    // Auto Update 3rd Party
    Route::prefix('auto-update')->name('auto-update.')->group(function () {
        Route::get('/', [\App\Http\Controllers\AutoUpdateController::class, 'index'])->name('index');
        Route::post('/save', [\App\Http\Controllers\AutoUpdateController::class, 'save'])->name('save');
        Route::post('/pma', [\App\Http\Controllers\AutoUpdateController::class, 'updatePma'])->name('pma');
        Route::post('/roundcube', [\App\Http\Controllers\AutoUpdateController::class, 'updateRoundcube'])->name('roundcube');
    });

    // Webmail Auto-Login
    Route::post('/webmail-login', [\App\Http\Controllers\AutoLoginEmailController::class, 'webmailLogin'])->name('webmail-login');

    // Python Manager
    Route::prefix('python')->name('python.')->group(function () {
        Route::get('/', [PythonController::class, 'index'])->name('index');
        Route::post('/install', [PythonController::class, 'install'])->name('install');
        Route::post('/remove', [PythonController::class, 'remove'])->name('remove');
        Route::post('/set-user', [PythonController::class, 'setUserVersion'])->name('set-user');
    });

    // PHP PECL Extensions
    Route::prefix('pecl')->name('pecl.')->group(function () {
        Route::get('/', [PeclController::class, 'index'])->name('index');
        Route::get('/search', [PeclController::class, 'search'])->name('search');
        Route::post('/install', [PeclController::class, 'install'])->name('install');
        Route::post('/uninstall', [PeclController::class, 'uninstall'])->name('uninstall');
        Route::post('/toggle', [PeclController::class, 'toggle'])->name('toggle');
    });

    // Object Storage
    Route::prefix('object-storage')->name('object-storage.')->group(function () {
        Route::get('/', [ObjectStorageController::class, 'index'])->name('index');
        Route::post('/save', [ObjectStorageController::class, 'save'])->name('save');
        Route::post('/test', [ObjectStorageController::class, 'test'])->name('test');
        Route::get('/buckets', [ObjectStorageController::class, 'listBuckets'])->name('buckets');
    });

    // Bandwidth Monitor
    Route::prefix('bandwidth')->name('bandwidth.')->group(function () {
        Route::get('/', [BandwidthController::class, 'index'])->name('index');
        Route::get('/user', [BandwidthController::class, 'user'])->name('user');
        Route::get('/interface', [BandwidthController::class, 'interface'])->name('interface');
    });

    // RBL Check
    Route::prefix('rbl')->name('rbl.')->group(function () {
        Route::get('/', [RblController::class, 'index'])->name('index');
        Route::post('/check', [RblController::class, 'check'])->name('check');
        Route::post('/check-all', [RblController::class, 'checkAll'])->name('check-all');
        Route::post('/add', [RblController::class, 'addBlacklist'])->name('add');
        Route::post('/remove', [RblController::class, 'removeBlacklist'])->name('remove');
    });

    // Policyd
    Route::prefix('policyd')->name('policyd.')->group(function () {
        Route::get('/', [PolicydController::class, 'index'])->name('index');
        Route::post('/install', [PolicydController::class, 'install'])->name('install');
        Route::post('/rate-limit', [PolicydController::class, 'addRateLimit'])->name('rate-limit');
        Route::delete('/rate-limit/{id}', [PolicydController::class, 'removeRateLimit'])->name('rate-limit-delete');
        Route::post('/toggle/{id}', [PolicydController::class, 'togglePolicy'])->name('toggle');
        Route::post('/restart', [PolicydController::class, 'restart'])->name('restart');
    });

    // Snuffleupagus
    Route::prefix('snuffleupagus')->name('snuffleupagus.')->group(function () {
        Route::get('/', [SnuffleupagusController::class, 'index'])->name('index');
        Route::post('/install', [SnuffleupagusController::class, 'install'])->name('install');
        Route::post('/config', [SnuffleupagusController::class, 'saveConfig'])->name('config');
        Route::post('/rules', [SnuffleupagusController::class, 'saveRules'])->name('rules');
    });

    // Help Desk
    Route::prefix('helpdesk')->name('helpdesk.')->group(function () {
        Route::get('/', [HelpDeskController::class, 'index'])->name('index');
        Route::post('/save', [HelpDeskController::class, 'save'])->name('save');
        Route::post('/install', [HelpDeskController::class, 'install'])->name('install');
    });

    // Incidents Log
    Route::prefix('incidents')->name('incidents.')->group(function () {
        Route::get('/', [IncidentsController::class, 'index'])->name('index');
        Route::post('/scan', [IncidentsController::class, 'scan'])->name('scan');
        Route::post('/{id}/resolve', [IncidentsController::class, 'resolve'])->name('resolve');
        Route::delete('/{id}', [IncidentsController::class, 'destroy'])->name('destroy');
        Route::post('/clear', [IncidentsController::class, 'clear'])->name('clear');
    });

    // Web Server Wizard
    Route::prefix('webserver-wizard')->name('webserver-wizard.')->group(function () {
        Route::get('/', [WebServerWizardController::class, 'index'])->name('index');
        Route::post('/step/{step}', [WebServerWizardController::class, 'step'])->name('step');
        Route::post('/finish', [WebServerWizardController::class, 'finish'])->name('finish');
        Route::post('/reset', [WebServerWizardController::class, 'reset'])->name('reset');
    });

    // Web Server Templates
    Route::prefix('webserver-templates')->name('webserver-templates.')->group(function () {
        Route::get('/', [WebServerTemplateController::class, 'index'])->name('index');
        Route::get('/create', [WebServerTemplateController::class, 'create'])->name('create');
        Route::post('/save', [WebServerTemplateController::class, 'save'])->name('save');
        Route::get('/{name}/edit', [WebServerTemplateController::class, 'edit'])->name('edit');
        Route::delete('/{name}', [WebServerTemplateController::class, 'destroy'])->name('destroy');
        Route::post('/generate', [WebServerTemplateController::class, 'generate'])->name('generate');
    });

    // Kernel Security
    Route::prefix('kernel-security')->name('kernel-security.')->group(function () {
        Route::get('/', [KernelSecurityController::class, 'index'])->name('index');
        Route::post('/blacklist', [KernelSecurityController::class, 'blacklist'])->name('blacklist');
        Route::post('/unblacklist', [KernelSecurityController::class, 'unblacklist'])->name('unblacklist');
        Route::post('/harden', [KernelSecurityController::class, 'harden'])->name('harden');
    });

    // Postfix List Manager
    Route::prefix('postfix-lists')->name('postfix-lists.')->group(function () {
        Route::get('/', [PostfixListController::class, 'index'])->name('index');
        Route::get('/{type}', [PostfixListController::class, 'show'])->name('show');
        Route::post('/add', [PostfixListController::class, 'add'])->name('add');
        Route::post('/remove', [PostfixListController::class, 'remove'])->name('remove');
    });

    // Web Scan
    Route::prefix('webscan')->name('webscan.')->group(function () {
        Route::get('/', [WebScanController::class, 'index'])->name('index');
        Route::post('/scan', [WebScanController::class, 'scan'])->name('scan');
        Route::get('/results/{domain}', [WebScanController::class, 'results'])->name('results');
    });

    // ClamAV
    Route::prefix('clamav')->name('clamav.')->group(function () {
        Route::get('/', [ClamavController::class, 'index'])->name('index');
        Route::post('/install', [ClamavController::class, 'install'])->name('install');
        Route::post('/update', [ClamavController::class, 'updateDefinitions'])->name('update');
        Route::post('/scan-user', [ClamavController::class, 'scanUser'])->name('scan-user');
        Route::post('/scan-all', [ClamavController::class, 'scanAll'])->name('scan-all');
        Route::post('/scan-path', [ClamavController::class, 'scanPath'])->name('scan-path');
        Route::post('/restore', [ClamavController::class, 'restore'])->name('restore');
        Route::post('/delete', [ClamavController::class, 'deleteQuarantine'])->name('delete');
        Route::post('/log', [ClamavController::class, 'viewLog'])->name('log');
    });

    // Icecast
    Route::prefix('icecast')->name('icecast.')->group(function () {
        Route::get('/', [IcecastController::class, 'index'])->name('index');
        Route::post('/install', [IcecastController::class, 'install'])->name('install');
        Route::post('/options', [IcecastController::class, 'saveOptions'])->name('options');
        Route::post('/add', [IcecastController::class, 'addServer'])->name('add');
        Route::delete('/{port}', [IcecastController::class, 'removeServer'])->name('remove');
        Route::post('/{port}/start', [IcecastController::class, 'start'])->name('start');
        Route::post('/{port}/stop', [IcecastController::class, 'stop'])->name('stop');
        Route::post('/{port}/restart', [IcecastController::class, 'restart'])->name('restart');
    });

    // PostgreSQL
    Route::prefix('postgresql')->name('postgresql.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PostgresqlController::class, 'index'])->name('index');
        Route::post('/install', [\App\Http\Controllers\PostgresqlController::class, 'install'])->name('install');
        Route::post('/database', [\App\Http\Controllers\PostgresqlController::class, 'createDatabase'])->name('create-db');
        Route::delete('/database', [\App\Http\Controllers\PostgresqlController::class, 'dropDatabase'])->name('drop-db');
        Route::post('/user', [\App\Http\Controllers\PostgresqlController::class, 'createUser'])->name('create-user');
        Route::delete('/user', [\App\Http\Controllers\PostgresqlController::class, 'dropUser'])->name('drop-user');
        Route::post('/grant', [\App\Http\Controllers\PostgresqlController::class, 'grant'])->name('grant');
        Route::post('/service', [\App\Http\Controllers\PostgresqlController::class, 'service'])->name('service');
    });

    // MongoDB
    Route::prefix('mongo')->name('mongo.')->group(function () {
        Route::get('/', [\App\Http\Controllers\MongoController::class, 'index'])->name('index');
        Route::post('/install', [\App\Http\Controllers\MongoController::class, 'install'])->name('install');
        Route::post('/database', [\App\Http\Controllers\MongoController::class, 'createDatabase'])->name('create-db');
        Route::delete('/database', [\App\Http\Controllers\MongoController::class, 'dropDatabase'])->name('drop-db');
        Route::post('/user', [\App\Http\Controllers\MongoController::class, 'createUser'])->name('create-user');
        Route::post('/service', [\App\Http\Controllers\MongoController::class, 'service'])->name('service');
    });

    // ModSecurity
    Route::prefix('modsecurity')->name('modsecurity.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ModSecurityController::class, 'index'])->name('index');
        Route::post('/install', [\App\Http\Controllers\ModSecurityController::class, 'install'])->name('install');
        Route::post('/toggle', [\App\Http\Controllers\ModSecurityController::class, 'toggle'])->name('toggle');
        Route::post('/update-rules', [\App\Http\Controllers\ModSecurityController::class, 'updateRules'])->name('update-rules');
        Route::get('/log', [\App\Http\Controllers\ModSecurityController::class, 'viewLog'])->name('log');
    });

    // Firewall (CSF)
    Route::prefix('firewall')->name('firewall.')->group(function () {
        Route::get('/', [\App\Http\Controllers\FirewallController::class, 'index'])->name('index');
        Route::post('/install', [\App\Http\Controllers\FirewallController::class, 'install'])->name('install');
        Route::post('/toggle', [\App\Http\Controllers\FirewallController::class, 'toggle'])->name('toggle');
        Route::post('/block', [\App\Http\Controllers\FirewallController::class, 'blockIp'])->name('block');
        Route::post('/unblock', [\App\Http\Controllers\FirewallController::class, 'unblockIp'])->name('unblock');
        Route::post('/allow', [\App\Http\Controllers\FirewallController::class, 'allowIp'])->name('allow');
        Route::post('/remove-allow', [\App\Http\Controllers\FirewallController::class, 'removeAllowIp'])->name('remove-allow');
        Route::post('/port/allow', [\App\Http\Controllers\FirewallController::class, 'allowPort'])->name('port-allow');
        Route::post('/port/deny', [\App\Http\Controllers\FirewallController::class, 'denyPort'])->name('port-deny');
    });

    // PHP Selector
    Route::prefix('php-selector')->name('php-selector.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PhpSelectorController::class, 'index'])->name('index');
        Route::post('/switch', [\App\Http\Controllers\PhpSelectorController::class, 'switchVersion'])->name('switch');
        Route::post('/install', [\App\Http\Controllers\PhpSelectorController::class, 'installVersion'])->name('install');
        Route::post('/remove', [\App\Http\Controllers\PhpSelectorController::class, 'removeVersion'])->name('remove');
        Route::get('/modules', [\App\Http\Controllers\PhpSelectorController::class, 'getModules'])->name('modules');
    });

    // PHP-FPM Manager
    Route::prefix('php-fpm')->name('php-fpm.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PhpFpmManagerController::class, 'index'])->name('index');
        Route::get('/config', [\App\Http\Controllers\PhpFpmManagerController::class, 'editConfig'])->name('config');
        Route::post('/config', [\App\Http\Controllers\PhpFpmManagerController::class, 'saveConfig'])->name('save-config');
        Route::get('/pool/{pool}', [\App\Http\Controllers\PhpFpmManagerController::class, 'editPool'])->name('pool');
        Route::post('/pool', [\App\Http\Controllers\PhpFpmManagerController::class, 'savePool'])->name('save-pool');
        Route::post('/service', [\App\Http\Controllers\PhpFpmManagerController::class, 'service'])->name('service');
    });

    // DKIM
    Route::prefix('dkim')->name('dkim.')->group(function () {
        Route::get('/', [\App\Http\Controllers\DkimController::class, 'index'])->name('index');
        Route::post('/generate', [\App\Http\Controllers\DkimController::class, 'generate'])->name('generate');
        Route::get('/view', [\App\Http\Controllers\DkimController::class, 'viewKey'])->name('view');
        Route::post('/toggle', [\App\Http\Controllers\DkimController::class, 'toggle'])->name('toggle');
    });

    // SPF / DMARC
    Route::prefix('spf')->name('spf.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SpfController::class, 'index'])->name('index');
        Route::post('/check', [\App\Http\Controllers\SpfController::class, 'check'])->name('check');
    });

    // Login Security
    Route::prefix('login-security')->name('login-security.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LoginSecurityController::class, 'index'])->name('index');
        Route::post('/block', [\App\Http\Controllers\LoginSecurityController::class, 'blockIp'])->name('block');
        Route::post('/unblock', [\App\Http\Controllers\LoginSecurityController::class, 'unblockIp'])->name('unblock');
        Route::post('/kick', [\App\Http\Controllers\LoginSecurityController::class, 'kickUser'])->name('kick');
        Route::post('/ssh', [\App\Http\Controllers\LoginSecurityController::class, 'updateSsh'])->name('ssh');
    });

    // Terminal
    Route::prefix('terminal')->name('terminal.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TerminalController::class, 'index'])->name('index');
        Route::post('/execute', [\App\Http\Controllers\TerminalController::class, 'execute'])->name('execute');
        Route::get('/history', [\App\Http\Controllers\TerminalController::class, 'history'])->name('history');
    });

    // CGroups
    Route::prefix('cgroups')->name('cgroups.')->group(function () {
        Route::get('/', [\App\Http\Controllers\CgroupsController::class, 'index'])->name('index');
        Route::post('/install', [\App\Http\Controllers\CgroupsController::class, 'install'])->name('install');
        Route::post('/create', [\App\Http\Controllers\CgroupsController::class, 'createGroup'])->name('create');
        Route::post('/delete', [\App\Http\Controllers\CgroupsController::class, 'deleteGroup'])->name('delete');
        Route::post('/assign', [\App\Http\Controllers\CgroupsController::class, 'assignUser'])->name('assign');
    });

    // Network Config
    Route::prefix('network')->name('network.')->group(function () {
        Route::get('/', [\App\Http\Controllers\NetworkController::class, 'index'])->name('index');
        Route::post('/hostname', [\App\Http\Controllers\NetworkController::class, 'updateHostname'])->name('hostname');
        Route::post('/dns', [\App\Http\Controllers\NetworkController::class, 'updateDns'])->name('dns');
        Route::post('/add-ip', [\App\Http\Controllers\NetworkController::class, 'addIp'])->name('add-ip');
        Route::post('/remove-ip', [\App\Http\Controllers\NetworkController::class, 'removeIp'])->name('remove-ip');
    });

    // MX Routing
    Route::prefix('mx-routing')->name('mx-routing.')->group(function () {
        Route::get('/', [\App\Http\Controllers\MxRoutingController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\MxRoutingController::class, 'update'])->name('update');
    });

    // Mail Auto-Reply
    Route::prefix('mail-autoreply')->name('mail-autoreply.')->group(function () {
        Route::get('/', [\App\Http\Controllers\MailAutoReplyController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\MailAutoReplyController::class, 'store'])->name('store');
        Route::delete('/', [\App\Http\Controllers\MailAutoReplyController::class, 'destroy'])->name('destroy');
    });

    // Disk Quota
    Route::prefix('disk-quota')->name('disk-quota.')->group(function () {
        Route::get('/', [\App\Http\Controllers\DiskQuotaController::class, 'index'])->name('index');
        Route::post('/set', [\App\Http\Controllers\DiskQuotaController::class, 'setUserQuota'])->name('set');
        Route::post('/remove', [\App\Http\Controllers\DiskQuotaController::class, 'removeUserQuota'])->name('remove');
        Route::get('/report', [\App\Http\Controllers\DiskQuotaController::class, 'report'])->name('report');
    });

    // Nameservers
    Route::prefix('nameservers')->name('nameservers.')->group(function () {
        Route::get('/', [\App\Http\Controllers\NameserverController::class, 'index'])->name('index');
        Route::post('/', [\App\Http\Controllers\NameserverController::class, 'update'])->name('update');
    });

    // Process Monitor
    Route::prefix('process-monitor')->name('process-monitor.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ProcessMonitorController::class, 'index'])->name('index');
        Route::get('/top', [\App\Http\Controllers\ProcessMonitorController::class, 'top'])->name('top');
        Route::post('/kill', [\App\Http\Controllers\ProcessMonitorController::class, 'kill'])->name('kill');
        Route::get('/netstat', [\App\Http\Controllers\ProcessMonitorController::class, 'netstat'])->name('netstat');
    });

    // DNS Cluster
    Route::prefix('dns-cluster')->name('dns-cluster.')->group(function () {
        Route::get('/', [\App\Http\Controllers\DnsClusterController::class, 'index'])->name('index');
        Route::post('/add', [\App\Http\Controllers\DnsClusterController::class, 'addSlave'])->name('add');
        Route::post('/remove', [\App\Http\Controllers\DnsClusterController::class, 'removeSlave'])->name('remove');
        Route::post('/sync', [\App\Http\Controllers\DnsClusterController::class, 'sync'])->name('sync');
    });

    // Slave DNS
    Route::prefix('slave-dns')->name('slave-dns.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SlaveDnsController::class, 'index'])->name('index');
        Route::post('/add', [\App\Http\Controllers\SlaveDnsController::class, 'add'])->name('add');
        Route::post('/remove', [\App\Http\Controllers\SlaveDnsController::class, 'remove'])->name('remove');
    });

    // PHP.ini Editor
    Route::prefix('phpini')->name('phpini.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PhpIniEditorController::class, 'index'])->name('index');
        Route::post('/update', [\App\Http\Controllers\PhpIniEditorController::class, 'update'])->name('update');
        Route::post('/save', [\App\Http\Controllers\PhpIniEditorController::class, 'saveFull'])->name('save');
    });

    // Live Monitor
    Route::prefix('live-monitor')->name('live-monitor.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LiveMonitorController::class, 'index'])->name('index');
        Route::get('/data', [\App\Http\Controllers\LiveMonitorController::class, 'data'])->name('data');
    });

    // Tomcat
    Route::prefix('tomcat-new')->name('tomcat-new.')->group(function () {
        Route::get('/', [\App\Http\Controllers\TomcatController::class, 'index'])->name('index');
        Route::post('/install', [\App\Http\Controllers\TomcatController::class, 'install'])->name('install');
        Route::post('/deploy', [\App\Http\Controllers\TomcatController::class, 'deploy'])->name('deploy');
        Route::post('/undeploy', [\App\Http\Controllers\TomcatController::class, 'undeploy'])->name('undeploy');
        Route::post('/service', [\App\Http\Controllers\TomcatController::class, 'service'])->name('service');
        Route::get('/config', [\App\Http\Controllers\TomcatController::class, 'editConfig'])->name('config');
        Route::post('/config', [\App\Http\Controllers\TomcatController::class, 'saveConfig'])->name('save-config');
    });

    // Config File Editor
    Route::prefix('config-editor')->name('config-editor.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ConfigEditorController::class, 'index'])->name('index');
        Route::post('/load', [\App\Http\Controllers\ConfigEditorController::class, 'load'])->name('load');
        Route::post('/save', [\App\Http\Controllers\ConfigEditorController::class, 'save'])->name('save');
        Route::post('/syntax-check', [\App\Http\Controllers\ConfigEditorController::class, 'syntaxCheck'])->name('syntax-check');
    });

    // FreeDNS
    Route::prefix('freedns')->name('freedns.')->group(function () {
        Route::get('/', [\App\Http\Controllers\FreednsController::class, 'index'])->name('index');
        Route::post('/add', [\App\Http\Controllers\FreednsController::class, 'addZone'])->name('add');
        Route::post('/delete', [\App\Http\Controllers\FreednsController::class, 'deleteZone'])->name('delete');
        Route::get('/edit', [\App\Http\Controllers\FreednsController::class, 'editZone'])->name('edit');
        Route::post('/save', [\App\Http\Controllers\FreednsController::class, 'saveZone'])->name('save');
    });

    // Script Installer
    Route::prefix('scripts')->name('scripts.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ScriptInstallerController::class, 'index'])->name('index');
        Route::post('/install', [\App\Http\Controllers\ScriptInstallerController::class, 'install'])->name('install');
        Route::post('/status', [\App\Http\Controllers\ScriptInstallerController::class, 'checkStatus'])->name('status');
    });

    // Startup Services
    Route::prefix('startup-services')->name('startup-services.')->group(function () {
        Route::get('/', [\App\Http\Controllers\StartupServiceController::class, 'index'])->name('index');
        Route::post('/toggle', [\App\Http\Controllers\StartupServiceController::class, 'toggle'])->name('toggle');
    });

    // Logrotate Manager
    Route::prefix('logrotate')->name('logrotate.')->group(function () {
        Route::get('/', [\App\Http\Controllers\LogrotateController::class, 'index'])->name('index');
        Route::get('/edit', [\App\Http\Controllers\LogrotateController::class, 'edit'])->name('edit');
        Route::post('/save', [\App\Http\Controllers\LogrotateController::class, 'save'])->name('save');
        Route::post('/test', [\App\Http\Controllers\LogrotateController::class, 'test'])->name('test');
    });

    // Fix Permissions
    Route::prefix('fix-permissions')->name('fix-permissions.')->group(function () {
        Route::get('/', [\App\Http\Controllers\FixPermissionsController::class, 'index'])->name('index');
        Route::post('/fix', [\App\Http\Controllers\FixPermissionsController::class, 'fix'])->name('fix');
        Route::post('/fix-all', [\App\Http\Controllers\FixPermissionsController::class, 'fixAll'])->name('fix-all');
    });

    // Security Center
    Route::prefix('security-center')->name('security-center.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SecurityCenterController::class, 'index'])->name('index');
        Route::post('/harden', [\App\Http\Controllers\SecurityCenterController::class, 'harden'])->name('harden');
    });

    // SysStat / SAR
    Route::prefix('sysstat')->name('sysstat.')->group(function () {
        Route::get('/', [\App\Http\Controllers\SysStatController::class, 'index'])->name('index');
        Route::post('/install', [\App\Http\Controllers\SysStatController::class, 'install'])->name('install');
        Route::get('/report', [\App\Http\Controllers\SysStatController::class, 'report'])->name('report');
    });

    // Screen Manager
    Route::prefix('screen')->name('screen.')->group(function () {
        Route::get('/', [\App\Http\Controllers\ScreenController::class, 'index'])->name('index');
        Route::post('/install', [\App\Http\Controllers\ScreenController::class, 'install'])->name('install');
        Route::post('/create', [\App\Http\Controllers\ScreenController::class, 'create'])->name('create');
        Route::post('/kill', [\App\Http\Controllers\ScreenController::class, 'kill'])->name('kill');
    });

    // PHP Switcher
    Route::prefix('php-switch')->name('php-switch.')->group(function () {
        Route::get('/', [\App\Http\Controllers\PhpSwitchController::class, 'index'])->name('index');
        Route::post('/switch', [\App\Http\Controllers\PhpSwitchController::class, 'switchVersion'])->name('switch');
    });

    // FFmpeg Installer
    Route::prefix('ffmpeg')->name('ffmpeg.')->group(function () {
        Route::get('/', [\App\Http\Controllers\FfmpegController::class, 'index'])->name('index');
        Route::post('/install', [\App\Http\Controllers\FfmpegController::class, 'install'])->name('install');
        Route::post('/uninstall', [\App\Http\Controllers\FfmpegController::class, 'uninstall'])->name('uninstall');
    });

    // rDNS Checker
    Route::prefix('rdns')->name('rdns.')->group(function () {
        Route::get('/', [\App\Http\Controllers\RdnsController::class, 'index'])->name('index');
        Route::post('/check', [\App\Http\Controllers\RdnsController::class, 'check'])->name('check');
    });

    // Mail Explorer
    Route::prefix('mail-explorer')->name('mail-explorer.')->group(function () {
        Route::get('/', [\App\Http\Controllers\MailExplorerController::class, 'index'])->name('index');
        Route::get('/browse', [\App\Http\Controllers\MailExplorerController::class, 'browse'])->name('browse');
        Route::get('/file', [\App\Http\Controllers\MailExplorerController::class, 'viewFile'])->name('file');
    });

    // Mass Email
    Route::prefix('mass-email')->name('mass-email.')->group(function () {
        Route::get('/', [\App\Http\Controllers\MassEmailController::class, 'index'])->name('index');
        Route::post('/send', [\App\Http\Controllers\MassEmailController::class, 'send'])->name('send');
    });

    // Netdata
    Route::prefix('netdata')->name('netdata.')->group(function () {
        Route::get('/', [\App\Http\Controllers\NetdataController::class, 'index'])->name('index');
        Route::post('/install', [\App\Http\Controllers\NetdataController::class, 'install'])->name('install');
        Route::post('/toggle', [\App\Http\Controllers\NetdataController::class, 'toggle'])->name('toggle');
    });

    // DNS Zone Add
    Route::prefix('dns-zone-add')->name('dns-zone-add.')->group(function () {
        Route::get('/', [\App\Http\Controllers\DnsZoneAddController::class, 'index'])->name('index');
        Route::post('/store', [\App\Http\Controllers\DnsZoneAddController::class, 'store'])->name('store');
    });

    // Restore Backup
    Route::prefix('restore-backup')->name('restore-backup.')->group(function () {
        Route::get('/', [\App\Http\Controllers\RestoreBackupController::class, 'index'])->name('index');
        Route::post('/restore', [\App\Http\Controllers\RestoreBackupController::class, 'restore'])->name('restore');
        Route::post('/upload', [\App\Http\Controllers\RestoreBackupController::class, 'upload'])->name('upload');
    });

    // WordPress Manager
    Route::prefix('wordpress')->name('wordpress.')->group(function () {
        Route::get('/', [\App\Http\Controllers\WordPressController::class, 'index'])->name('index');
        Route::get('/create', [\App\Http\Controllers\WordPressController::class, 'create'])->name('create');
        Route::post('/', [\App\Http\Controllers\WordPressController::class, 'store'])->name('store');
        Route::get('/{site}', [\App\Http\Controllers\WordPressController::class, 'show'])->name('show');
        Route::post('/{site}/update-core', [\App\Http\Controllers\WordPressController::class, 'updateCore'])->name('update-core');
        Route::post('/{site}/update-plugins', [\App\Http\Controllers\WordPressController::class, 'updatePlugins'])->name('update-plugins');
        Route::post('/{site}/update-themes', [\App\Http\Controllers\WordPressController::class, 'updateThemes'])->name('update-themes');
        Route::post('/{site}/backup', [\App\Http\Controllers\WordPressController::class, 'backup'])->name('backup');
        Route::post('/{site}/restore', [\App\Http\Controllers\WordPressController::class, 'restore'])->name('restore');
        Route::post('/{site}/clone', [\App\Http\Controllers\WordPressController::class, 'cloneSite'])->name('clone');
        Route::post('/{site}/staging', [\App\Http\Controllers\WordPressController::class, 'createStaging'])->name('staging');
        Route::post('/{site}/push-staging', [\App\Http\Controllers\WordPressController::class, 'pushStaging'])->name('push-staging');
        Route::post('/{site}/scan', [\App\Http\Controllers\WordPressController::class, 'securityScan'])->name('scan');
        Route::post('/{site}/secure', [\App\Http\Controllers\WordPressController::class, 'secure'])->name('secure');
        Route::post('/{site}/repair-permissions', [\App\Http\Controllers\WordPressController::class, 'repairPermissions'])->name('repair-permissions');
        Route::post('/{site}/enable-redis', [\App\Http\Controllers\WordPressController::class, 'enableRedis'])->name('enable-redis');
        Route::post('/{site}/disable-redis', [\App\Http\Controllers\WordPressController::class, 'disableRedis'])->name('disable-redis');
        Route::post('/{site}/purge-cache', [\App\Http\Controllers\WordPressController::class, 'purgeCache'])->name('purge-cache');
        Route::post('/{site}/suspend', [\App\Http\Controllers\WordPressController::class, 'suspend'])->name('suspend');
        Route::post('/{site}/unsuspend', [\App\Http\Controllers\WordPressController::class, 'unsuspend'])->name('unsuspend');
        Route::post('/{site}/enable-ssl', [\App\Http\Controllers\WordPressController::class, 'enableSsl'])->name('enable-ssl');
        Route::delete('/{site}', [\App\Http\Controllers\WordPressController::class, 'delete'])->name('delete');
        // Performance routes
        Route::get('/{site}/performance', [\App\Http\Controllers\WordPressController::class, 'performanceReport'])->name('performance');
        Route::post('/{site}/flush-redis', [\App\Http\Controllers\WordPressController::class, 'flushRedis'])->name('flush-redis');
        Route::post('/{site}/varnish-test', [\App\Http\Controllers\WordPressController::class, 'varnishTest'])->name('varnish-test');
        Route::post('/{site}/purge-varnish', [\App\Http\Controllers\WordPressController::class, 'purgeVarnish'])->name('purge-varnish');
        Route::post('/{site}/apply-profile', [\App\Http\Controllers\WordPressController::class, 'applyProfile'])->name('apply-profile');
        Route::post('/{site}/reset-profile', [\App\Http\Controllers\WordPressController::class, 'resetProfile'])->name('reset-profile');
        Route::post('/{site}/update-php-fpm', [\App\Http\Controllers\WordPressController::class, 'updatePhpFpm'])->name('update-php-fpm');
        Route::get('/{site}/cron', [\App\Http\Controllers\WordPressController::class, 'cronStatus'])->name('cron');
        Route::post('/{site}/cron-run', [\App\Http\Controllers\WordPressController::class, 'cronRunNow'])->name('cron-run');
        Route::post('/{site}/cron-toggle', [\App\Http\Controllers\WordPressController::class, 'cronToggle'])->name('cron-toggle');
    });
});

// Reseller Panel (ports 2082/2083)
Route::middleware(['auth', \App\Http\Middleware\ResellerMiddleware::class])->prefix('reseller')->name('reseller.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Reseller\ResellerDashboardController::class, 'index'])->name('dashboard');
    Route::get('/accounts', [\App\Http\Controllers\Reseller\ResellerDashboardController::class, 'accounts'])->name('accounts');
    Route::get('/accounts/create', [\App\Http\Controllers\Reseller\ResellerDashboardController::class, 'createAccount'])->name('accounts.create');
    Route::post('/accounts', [\App\Http\Controllers\Reseller\ResellerDashboardController::class, 'storeAccount'])->name('accounts.store');
});

// REST API (no CSRF, outside auth middleware)
Route::any('/v1', [\App\Http\Controllers\LegacyApiController::class, 'handle']);
Route::any('/v1/', [\App\Http\Controllers\LegacyApiController::class, 'handle']);

// User Panel (ports 2082/2083) - accessible by both admin and regular users
Route::middleware(['auth', \App\Http\Middleware\UserMiddleware::class])->prefix('user')->name('user.')->group(function () {
    Route::get('/dashboard', [UserDashboardController::class, 'index'])->name('dashboard');

    // Domains
    Route::get('/domains', [UserDomainController::class, 'index'])->name('domains.index');
    Route::get('/domains/subdomains', [UserDomainController::class, 'subdomains'])->name('domains.subdomains');
    Route::get('/domains/aliases', [UserDomainController::class, 'aliases'])->name('domains.aliases');
    Route::post('/domains/subdomain/add', [UserDomainController::class, 'addSubdomain'])->name('domains.subdomain.add');
    Route::post('/domains/subdomain/remove', [UserDomainController::class, 'removeSubdomain'])->name('domains.subdomain.remove');
    Route::post('/domains/alias/add', [UserDomainController::class, 'addAlias'])->name('domains.alias.add');
    Route::post('/domains/alias/remove', [UserDomainController::class, 'removeAlias'])->name('domains.alias.remove');

    // Email
    Route::get('/email', [UserEmailController::class, 'index'])->name('email.index');
    Route::get('/email/forwarders', [UserEmailController::class, 'forwarders'])->name('email.forwarders');
    Route::get('/email/autoresponders', [UserEmailController::class, 'autoresponders'])->name('email.autoresponders');
    Route::post('/email/create', [UserEmailController::class, 'createAccount'])->name('email.create');
    Route::post('/email/delete', [UserEmailController::class, 'deleteAccount'])->name('email.delete');
    Route::post('/email/forwarder/create', [UserEmailController::class, 'createForwarder'])->name('email.forwarder.create');
    Route::post('/email/forwarder/delete', [UserEmailController::class, 'deleteForwarder'])->name('email.forwarder.delete');
    Route::post('/email/autoresponder/create', [UserEmailController::class, 'createAutoresponder'])->name('email.autoresponder.create');
    Route::post('/email/autoresponder/delete', [UserEmailController::class, 'deleteAutoresponder'])->name('email.autoresponder.delete');

    // MySQL
    Route::get('/mysql', [UserMysqlController::class, 'index'])->name('mysql.index');
    Route::get('/mysql/phpmyadmin', [UserMysqlController::class, 'phpmyadmin'])->name('mysql.phpmyadmin');
    Route::post('/mysql/database/create', [UserMysqlController::class, 'createDatabase'])->name('mysql.database.create');
    Route::post('/mysql/database/delete', [UserMysqlController::class, 'deleteDatabase'])->name('mysql.database.delete');
    Route::post('/mysql/user/create', [UserMysqlController::class, 'createUser'])->name('mysql.user.create');
    Route::post('/mysql/user/delete', [UserMysqlController::class, 'deleteUser'])->name('mysql.user.delete');
    Route::post('/mysql/assign', [UserMysqlController::class, 'assignUser'])->name('mysql.assign');
    Route::post('/mysql/revoke', [UserMysqlController::class, 'revokeUser'])->name('mysql.revoke');
    Route::post('/mysql/password', [UserMysqlController::class, 'changePassword'])->name('mysql.password');

    // phpMyAdmin shortcut
    Route::get('/phpmyadmin', [UserMysqlController::class, 'phpmyadmin'])->name('phpmyadmin');

    // Files
    Route::get('/files', [UserFileController::class, 'index'])->name('files.index');
    Route::get('/files/read', [UserFileController::class, 'readFile'])->name('files.read');
    Route::post('/files/save', [UserFileController::class, 'saveFile'])->name('files.save');
    Route::post('/files/mkdir', [UserFileController::class, 'createDirectory'])->name('files.mkdir');
    Route::post('/files/delete', [UserFileController::class, 'delete'])->name('files.delete');
    Route::post('/files/chmod', [UserFileController::class, 'changePermissions'])->name('files.chmod');

    // FTP
    Route::get('/ftp', [UserFtpController::class, 'index'])->name('ftp.index');
    Route::post('/ftp/create', [UserFtpController::class, 'create'])->name('ftp.create');
    Route::post('/ftp/delete', [UserFtpController::class, 'delete'])->name('ftp.delete');
    Route::post('/ftp/password', [UserFtpController::class, 'changePassword'])->name('ftp.password');

    // Cron
    Route::get('/cron', [UserCronController::class, 'index'])->name('cron.index');
    Route::post('/cron/store', [UserCronController::class, 'store'])->name('cron.store');
    Route::post('/cron/destroy', [UserCronController::class, 'destroy'])->name('cron.destroy');

    // SSL
    Route::get('/ssl', [UserSslController::class, 'index'])->name('ssl.index');
    Route::get('/ssl/generate', [UserSslController::class, 'generate'])->name('ssl.generate');
    Route::post('/ssl/request', [UserSslController::class, 'requestCert'])->name('ssl.request');
    Route::post('/ssl/selfsigned', [UserSslController::class, 'selfSigned'])->name('ssl.selfsigned');

    // DNS
    Route::get('/dns', [UserDnsController::class, 'index'])->name('dns.index');
    Route::get('/dns/{domain}', [UserDnsController::class, 'show'])->name('dns.show');
    Route::post('/dns/record/add', [UserDnsController::class, 'addRecord'])->name('dns.record.add');
    Route::post('/dns/record/delete', [UserDnsController::class, 'deleteRecord'])->name('dns.record.delete');

    // Stats
    Route::get('/stats', [UserStatsController::class, 'index'])->name('stats.index');

    // Backups
    Route::get('/backups', [UserBackupController::class, 'index'])->name('backups.index');
    Route::post('/backups/create', [UserBackupController::class, 'create'])->name('backups.create');
    Route::get('/backups/download', [UserBackupController::class, 'download'])->name('backups.download');
    Route::post('/backups/restore', [UserBackupController::class, 'restore'])->name('backups.restore');
    Route::post('/backups/delete', [UserBackupController::class, 'delete'])->name('backups.delete');

    // WordPress
    Route::get('/wordpress', [UserWordPressController::class, 'index'])->name('wordpress.index');
    Route::get('/wordpress/create', [UserWordPressController::class, 'create'])->name('wordpress.create');
    Route::post('/wordpress', [UserWordPressController::class, 'store'])->name('wordpress.store');
    Route::get('/wordpress/{site}', [UserWordPressController::class, 'show'])->name('wordpress.show');
    Route::post('/wordpress/{site}/backup', [UserWordPressController::class, 'backup'])->name('wordpress.backup');
    Route::post('/wordpress/{site}/restore', [UserWordPressController::class, 'restore'])->name('wordpress.restore');
    Route::post('/wordpress/{site}/staging', [UserWordPressController::class, 'createStaging'])->name('wordpress.staging');
    Route::post('/wordpress/{site}/purge-cache', [UserWordPressController::class, 'purgeCache'])->name('wordpress.purge-cache');
    Route::post('/wordpress/{site}/enable-redis', [UserWordPressController::class, 'enableRedis'])->name('wordpress.enable-redis');
    Route::post('/wordpress/{site}/disable-redis', [UserWordPressController::class, 'disableRedis'])->name('wordpress.disable-redis');
    Route::post('/wordpress/{site}/update-core', [UserWordPressController::class, 'updateCore'])->name('wordpress.update-core');
    Route::post('/wordpress/{site}/update-plugins', [UserWordPressController::class, 'updatePlugins'])->name('wordpress.update-plugins');
    Route::post('/wordpress/{site}/scan', [UserWordPressController::class, 'securityScan'])->name('wordpress.scan');
    // User performance routes
    Route::get('/wordpress/{site}/performance', [UserWordPressController::class, 'performance'])->name('wordpress.performance');
    Route::post('/wordpress/{site}/flush-redis', [UserWordPressController::class, 'flushRedis'])->name('wordpress.flush-redis');
    Route::post('/wordpress/{site}/varnish-test', [UserWordPressController::class, 'varnishTest'])->name('wordpress.varnish-test');
    Route::post('/wordpress/{site}/purge-varnish', [UserWordPressController::class, 'purgeVarnish'])->name('wordpress.purge-varnish');
    Route::post('/wordpress/{site}/apply-profile', [UserWordPressController::class, 'applyProfile'])->name('wordpress.apply-profile');
    Route::post('/wordpress/{site}/update-php-fpm', [UserWordPressController::class, 'updatePhpFpm'])->name('wordpress.update-php-fpm');
    Route::post('/wordpress/{site}/cron-run', [UserWordPressController::class, 'cronRunNow'])->name('wordpress.cron-run');
    Route::post('/wordpress/{site}/cron-toggle', [UserWordPressController::class, 'cronToggle'])->name('wordpress.cron-toggle');
});
