<?php

namespace App\Http\Controllers;

use App\Models\WordPressSite;
use App\Models\WordPressBackup;
use App\Models\UserAccount;
use App\Models\Domain;
use App\Services\WordPressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class WordPressController extends Controller
{
    protected WordPressService $wp;

    public function __construct(WordPressService $wp)
    {
        $this->wp = $wp;
    }

    public function index()
    {
        $user = Auth::user();
        $sites = $this->wp->listWordPressSites();
        return view('wordpress.index', compact('sites'));
    }

    public function show(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $updates = $this->wp->getUpdates($site);
        $plugins = $this->wp->listPlugins($site);
        $themes = $this->wp->listThemes($site);
        $diskUsage = $this->wp->getDiskUsage($site);
        $latestScan = $site->securityScans()->latest()->first();
        $latestBackup = $site->backups()->latest()->first();
        $stagingSites = $site->stagingSites()->get();
        return view('wordpress.show', compact('site', 'updates', 'plugins', 'themes', 'diskUsage', 'latestScan', 'latestBackup', 'stagingSites'));
    }

    public function create()
    {
        $accounts = UserAccount::with('user')->get();
        $domains = Domain::all();
        return view('wordpress.create', compact('accounts', 'domains'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'user_account_id' => 'required|integer',
            'domain' => 'required|string',
            'site_title' => 'required|string|max:200',
            'admin_user' => 'required|string|max:60',
            'admin_password' => 'required|string|min:8',
            'admin_email' => 'required|email',
            'php_version' => 'nullable|string',
            'ssl_enabled' => 'nullable|boolean',
            'enable_redis' => 'nullable|boolean',
        ]);

        $site = $this->wp->installWordPress($request->all());

        if ($site['success']) {
            return redirect()->route('wordpress.show', $site['site']->id)->with('success', $site['message']);
        }

        return back()->withInput()->with('error', $site['message']);
    }

    public function updateCore(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->updateCore($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['success'] ? 'WordPress core updated.' : ($result['message'] ?? 'Update failed.'));
    }

    public function updatePlugins(Request $request, WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->updatePlugins($site, $request->plugin);
        return back()->with($result['success'] ? 'success' : 'error', $result['success'] ? 'Plugins updated.' : 'Plugin update failed.');
    }

    public function updateThemes(Request $request, WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->updateThemes($site, $request->theme);
        return back()->with($result['success'] ? 'success' : 'error', $result['success'] ? 'Themes updated.' : 'Theme update failed.');
    }

    public function backup(Request $request, WordPressSite $site)
    {
        $this->authorizeSite($site);
        $type = $request->input('type', 'full');
        $result = $this->wp->backupSite($site, $type);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? 'Backup failed.');
    }

    public function restore(Request $request, WordPressSite $site)
    {
        $this->authorizeSite($site);
        $request->validate(['backup_id' => 'required|integer']);
        $backup = WordPressBackup::where('wordpress_site_id', $site->id)->findOrFail($request->backup_id);
        $result = $this->wp->restoreSite($site, $backup);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? 'Restore failed.');
    }

    public function cloneSite(Request $request, WordPressSite $site)
    {
        $this->authorizeSite($site);
        $request->validate(['target_domain' => 'required|string']);
        $result = $this->wp->cloneSite($site, $request->target_domain);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? 'Clone failed.');
    }

    public function createStaging(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->createStaging($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? 'Staging creation failed.');
    }

    public function pushStaging(Request $request, WordPressSite $site)
    {
        $this->authorizeSite($site);
        $stagingDomain = $request->input('staging_domain', "staging.{$site->domain}");
        $stagingSite = WordPressSite::where('domain', $stagingDomain)->first();
        if (!$stagingSite) {
            return back()->with('error', 'Staging site not found.');
        }
        $result = $this->wp->pushStagingToLive($stagingSite, $site);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? 'Push failed.');
    }

    public function deleteStaging(Request $request, WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->deleteStaging($site, $request->input('staging_domain'));
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? $result['message'] ?? 'Delete staging failed.');
    }

    public function securityScan(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->scanSite($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? 'Scan failed.');
    }

    public function secure(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->secureSite($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? 'Security hardening failed.');
    }

    public function repairPermissions(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->repairPermissions($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? 'Permission repair failed.');
    }

    public function enableRedis(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->enableRedis($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['message'] ?? 'Redis enable failed.');
    }

    public function disableRedis(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->disableRedis($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['message'] ?? 'Redis disable failed.');
    }

    public function purgeCache(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->purgeCache($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? 'Cache purge failed.');
    }

    public function suspend(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->suspendSite($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['message'] ?? 'Suspend failed.');
    }

    public function unsuspend(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->unsuspendSite($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['message'] ?? 'Unsuspend failed.');
    }

    public function enableSsl(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->enableSsl($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['message'] ?? 'SSL failed.');
    }

    public function delete(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $site->delete();
        return redirect()->route('wordpress.index')->with('success', "WordPress site {$site->domain} deleted.");
    }

    // --- Redis ---

    public function redisStatus(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $health = $this->wp->checkRedisHealth();
        $status = $site->redis_enabled ? $this->wp->getRedisStatus($site) : ['connected' => false, 'output' => 'Redis not enabled'];
        return view('wordpress.performance', compact('site', 'health', 'status'));
    }

    public function flushRedis(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->flushRedisCache($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    // --- Varnish ---

    public function varnishTest(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->testVarnishCacheHit($site);
        return back()->with($result['is_hit'] ? 'success' : 'error', $result['message'] . ' — ' . ($result['header'] ?? ''));
    }

    public function purgeVarnish(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->purgeVarnishCache($site);
        return back()->with('success', $result['message']);
    }

    // --- Performance Profiles ---

    public function applyProfile(Request $request, WordPressSite $site)
    {
        $this->authorizeSite($site);
        $request->validate(['profile' => 'required|string']);
        $result = $this->wp->applyPerformanceProfile($site, $request->profile);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function resetProfile(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->resetPerformanceProfile($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    // --- PHP-FPM ---

    public function updatePhpFpm(Request $request, WordPressSite $site)
    {
        $this->authorizeSite($site);
        $request->validate([
            'pm' => 'required|in:ondemand,dynamic,static',
            'max_children' => 'required|integer|min:1|max:100',
            'memory_limit' => 'required|integer|min:64|max:2048',
            'max_execution_time' => 'required|integer|min:5|max:600',
            'upload_max_filesize' => 'required|integer|min:2|max:1024',
        ]);
        $result = $this->wp->updatePhpFpmSettings($site, $request->all());
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    // --- WP-Cron ---

    public function cronStatus(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $cron = $this->wp->getWpCronStatus($site);
        return view('wordpress.cron', compact('site', 'cron'));
    }

    public function cronRunNow(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->runWpCronNow($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function cronToggle(Request $request, WordPressSite $site)
    {
        $this->authorizeSite($site);
        $disabled = $request->boolean('disabled', false);
        $this->wp->setWpCronDisabled($site, $disabled);
        return back()->with('success', $disabled ? 'System cron enabled, WP-Cron disabled.' : 'WP-Cron re-enabled.');
    }

    // --- Performance Report ---

    public function performanceReport(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $report = $this->wp->getPerformanceReport($site);
        $profiles = $this->wp->getPerformanceProfiles();
        $varnishStatus = $this->wp->getVarnishStatus();
        $redisHealth = $this->wp->checkRedisHealth();
        return view('wordpress.performance', compact('site', 'report', 'profiles', 'varnishStatus', 'redisHealth'));
    }

    protected function authorizeSite(WordPressSite $site): void
    {
        $user = Auth::user();
        if ($user->isAdmin()) {
            return;
        }
        if ($user->isReseller()) {
            $ownerUsername = $site->userAccount->user?->username ?? '';
            if ($user->canManageUser($ownerUsername)) {
                return;
            }
        }
        $userAccountId = $site->user_account_id;
        $myAccount = UserAccount::where('user_id', function ($q) use ($user) {
            $q->from('users')->where('username', $user->username)->select('id');
        })->first();
        if ($myAccount && $myAccount->id === $userAccountId) {
            return;
        }
        abort(403, 'You do not have access to this WordPress site.');
    }
}
