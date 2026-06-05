<?php

namespace App\Http\Controllers\UserPanel;

use App\Http\Controllers\Controller;
use App\Models\WordPressSite;
use App\Models\WordPressBackup;
use App\Models\UserAccount;
use App\Models\Domain;
use App\Services\WordPressService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserWordPressController extends Controller
{
    protected WordPressService $wp;

    public function __construct(WordPressService $wp)
    {
        $this->wp = $wp;
    }

    protected function userAccountId(): ?int
    {
        $user = Auth::user();
        $account = UserAccount::whereHas('user', fn($q) => $q->where('username', $user->username))->first();
        return $account?->id;
    }

    protected function authorizeSite(WordPressSite $site): void
    {
        if ($site->user_account_id !== $this->userAccountId()) {
            abort(403, 'Access denied.');
        }
    }

    public function index()
    {
        $accountId = $this->userAccountId();
        $sites = $accountId ? $this->wp->listWordPressSites($accountId) : collect();
        return view('user-panel.wordpress.index', compact('sites'));
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
        return view('user-panel.wordpress.show', compact('site', 'updates', 'plugins', 'themes', 'diskUsage', 'latestScan', 'latestBackup', 'stagingSites'));
    }

    public function create()
    {
        $accountId = $this->userAccountId();
        $domains = Domain::where('user_account_id', $accountId)->get();
        return view('user-panel.wordpress.create', compact('domains'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'domain' => 'required|string',
            'site_title' => 'required|string|max:200',
            'admin_user' => 'required|string|max:60',
            'admin_password' => 'required|string|min:8',
            'admin_email' => 'required|email',
        ]);

        $data = $request->all();
        $data['user_account_id'] = $this->userAccountId();

        $result = $this->wp->installWordPress($data);

        if ($result['success']) {
            return redirect()->route('user.wordpress.show', $result['site']->id)->with('success', $result['message']);
        }

        return back()->withInput()->with('error', $result['message']);
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

    public function createStaging(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->createStaging($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? 'Staging creation failed.');
    }

    public function pushStaging(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $stagingSite = $site->stagingSites()->first();
        if (!$stagingSite) {
            return back()->with('error', 'Staging site not found.');
        }
        $result = $this->wp->pushStagingToLive($stagingSite, $site);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? $result['message'] ?? 'Push failed.');
    }

    public function deleteStaging(Request $request, WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->deleteStaging($site, $request->input('staging_domain'));
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? $result['message'] ?? 'Delete staging failed.');
    }

    public function purgeCache(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->purgeCache($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? 'Cache purge failed.');
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

    public function updateCore(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->updateCore($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['success'] ? 'Core updated.' : ($result['message'] ?? 'Update failed.'));
    }

    public function updatePlugins(Request $request, WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->updatePlugins($site, $request->plugin);
        return back()->with($result['success'] ? 'success' : 'error', $result['success'] ? 'Plugins updated.' : 'Plugin update failed.');
    }

    public function securityScan(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->scanSite($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? 'Scan failed.');
    }

    public function performance(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $report = $this->wp->getPerformanceReport($site);
        $profiles = $this->wp->getPerformanceProfiles();
        $varnishStatus = $this->wp->getVarnishStatus();
        $redisHealth = $this->wp->checkRedisHealth();
        return view('user-panel.wordpress.performance', compact('site', 'report', 'profiles', 'varnishStatus', 'redisHealth'));
    }

    public function flushRedis(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->flushRedisCache($site);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function varnishTest(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->testVarnishCacheHit($site);
        return back()->with($result['is_hit'] ? 'success' : 'error', $result['message']);
    }

    public function purgeVarnish(WordPressSite $site)
    {
        $this->authorizeSite($site);
        $result = $this->wp->purgeVarnishCache($site);
        return back()->with('success', $result['message']);
    }

    public function applyProfile(Request $request, WordPressSite $site)
    {
        $this->authorizeSite($site);
        $request->validate(['profile' => 'required|string']);
        $result = $this->wp->applyPerformanceProfile($site, $request->profile);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

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
        return back()->with('success', $disabled ? 'System cron enabled.' : 'WP-Cron re-enabled.');
    }
}
