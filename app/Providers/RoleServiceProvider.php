<?php

namespace App\Providers;

use App\Models\LinuxAuthUser;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class RoleServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::define('admin-only', function (LinuxAuthUser $user) {
            return $user->isAdmin();
        });

        Gate::define('reseller-or-admin', function (LinuxAuthUser $user) {
            return $user->isAdmin() || $user->isReseller();
        });

        Gate::define('manage-account', function (LinuxAuthUser $user, string $targetUsername) {
            if ($user->isAdmin()) return true;
            if ($user->isReseller()) return $user->canManageUser($targetUsername);
            return false;
        });

        Gate::define('view-account', function (LinuxAuthUser $user, string $targetUsername) {
            if ($user->isAdmin()) return true;
            if ($user->isReseller()) return $user->canManageUser($targetUsername);
            return $user->username === $targetUsername;
        });

        Gate::define('manage-domain', function (LinuxAuthUser $user, $domain) {
            if ($user->isAdmin()) return true;
            if ($user->isReseller()) {
                $account = $domain->userAccount ?? null;
                if (!$account) return false;
                return $user->canManageUser($account->user->username ?? '');
            }
            return false;
        });

        Gate::define('manage-database', function (LinuxAuthUser $user, $database) {
            if ($user->isAdmin()) return true;
            if ($user->isReseller()) {
                $account = $database->userAccount ?? null;
                if (!$account) return false;
                return $user->canManageUser($account->user->username ?? '');
            }
            return false;
        });

        Gate::define('manage-email', function (LinuxAuthUser $user, $emailAccount) {
            if ($user->isAdmin()) return true;
            if ($user->isReseller()) {
                $account = $emailAccount->userAccount ?? null;
                if (!$account) return false;
                return $user->canManageUser($account->user->username ?? '');
            }
            return false;
        });

        Gate::define('manage-dns', function (LinuxAuthUser $user, $zone) {
            if ($user->isAdmin()) return true;
            if ($user->isReseller()) {
                $account = $zone->userAccount ?? null;
                if (!$account) return false;
                return $user->canManageUser($account->user->username ?? '');
            }
            return false;
        });

        Gate::define('manage-ftp', function (LinuxAuthUser $user, $ftpAccount) {
            if ($user->isAdmin()) return true;
            if ($user->isReseller()) {
                $account = $ftpAccount->userAccount ?? null;
                if (!$account) return false;
                return $user->canManageUser($account->user->username ?? '');
            }
            return false;
        });

        Gate::define('manage-ssl', function (LinuxAuthUser $user, $certificate) {
            if ($user->isAdmin()) return true;
            if ($user->isReseller()) {
                $account = $certificate->userAccount ?? null;
                if (!$account) return false;
                return $user->canManageUser($account->user->username ?? '');
            }
            return false;
        });

        Gate::define('manage-backup', function (LinuxAuthUser $user, $backup) {
            if ($user->isAdmin()) return true;
            if ($user->isReseller()) {
                $account = $backup->userAccount ?? null;
                if (!$account) return false;
                return $user->canManageUser($account->user->username ?? '');
            }
            return false;
        });

        Gate::define('manage-package', function (LinuxAuthUser $user, $package) {
            if ($user->isAdmin()) return true;
            if ($user->isReseller()) {
                return $package->reseller_id === null || $package->reseller_id === $user->uid;
            }
            return false;
        });

        Gate::define('access-server-settings', function (LinuxAuthUser $user) {
            return $user->isAdmin();
        });

        Gate::define('access-firewall', function (LinuxAuthUser $user) {
            return $user->isAdmin();
        });

        Gate::define('access-terminal', function (LinuxAuthUser $user) {
            return $user->isAdmin();
        });

        Gate::define('access-config-editor', function (LinuxAuthUser $user) {
            return $user->isAdmin();
        });

        Gate::define('access-dns-cluster', function (LinuxAuthUser $user) {
            return $user->isAdmin();
        });

        Gate::define('access-php-switcher', function (LinuxAuthUser $user) {
            return $user->isAdmin();
        });

        Gate::define('access-services', function (LinuxAuthUser $user) {
            return $user->isAdmin();
        });

        Gate::define('view-wordpress-site', function (LinuxAuthUser $user, $site) {
            if ($user->isAdmin()) return true;
            if ($user->isReseller()) {
                $account = $site->userAccount ?? null;
                if (!$account) return false;
                return $user->canManageUser($account->user->username ?? '');
            }
            $account = $site->userAccount ?? null;
            return $account && ($account->user?->username === $user->username);
        });

        Gate::define('manage-wordpress-site', function (LinuxAuthUser $user, $site) {
            if ($user->isAdmin()) return true;
            if ($user->isReseller()) {
                $account = $site->userAccount ?? null;
                if (!$account) return false;
                return $user->canManageUser($account->user->username ?? '');
            }
            $account = $site->userAccount ?? null;
            return $account && ($account->user?->username === $user->username);
        });
    }
}
