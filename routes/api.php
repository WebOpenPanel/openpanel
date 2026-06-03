<?php

use Illuminate\Support\Facades\Route;

// API v1 — WHMCS-compatible provisioning API
Route::prefix('v1')->middleware(\App\Http\Middleware\ApiAuthMiddleware::class)->group(function () {
    $c = \App\Http\Controllers\Api\V1Controller::class;

    Route::get('/health', [$c, 'health']);
    Route::get('/abuse-monitor', [$c, 'abuseMonitor']);
    Route::get('/server-info', [$c, 'serverInfo']);
    Route::get('/packages', [$c, 'packages']);

    // Accounts
    Route::post('/accounts/create', [$c, 'accountCreate']);
    Route::post('/accounts/{username}/suspend', [$c, 'accountSuspend']);
    Route::post('/accounts/{username}/unsuspend', [$c, 'accountUnsuspend']);
    Route::post('/accounts/{username}/terminate', [$c, 'accountTerminate']);
    Route::post('/accounts/{username}/change-password', [$c, 'accountChangePassword']);
    Route::post('/accounts/{username}/change-package', [$c, 'accountChangePackage']);
    Route::post('/accounts/{username}/repair-isolation', [$c, 'accountRepairIsolation']);
    Route::get('/accounts/{username}/resource-limits', [$c, 'accountResourceLimits']);
    Route::get('/accounts/{username}', [$c, 'accountGet']);
    Route::get('/accounts/{username}/usage', [$c, 'accountUsage']);

    // WordPress
    Route::post('/wordpress/install', [$c, 'wpInstall']);
    Route::post('/wordpress/enable-redis', [$c, 'wpEnableRedis']);
    Route::post('/wordpress/enable-varnish', [$c, 'wpEnableVarnish']);
    Route::post('/wordpress/apply-profile', [$c, 'wpApplyProfile']);
    Route::post('/wordpress/backup', [$c, 'wpBackup']);
    Route::post('/wordpress/restore', [$c, 'wpRestore']);
    Route::post('/wordpress/staging/create', [$c, 'wpStagingCreate']);
    Route::post('/wordpress/cache/purge', [$c, 'wpCachePurge']);
    Route::get('/wordpress/{siteId}', [$c, 'wpGet']);

    // DNS
    Route::post('/dns/zone/create', [$c, 'dnsZoneCreate']);
    Route::post('/dns/record/create', [$c, 'dnsRecordCreate']);
    Route::post('/dns/record/delete', [$c, 'dnsRecordDelete']);
    Route::get('/dns/zone/{domain}', [$c, 'dnsZoneGet']);

    // Email
    Route::post('/email/create', [$c, 'emailCreate']);
    Route::post('/email/delete', [$c, 'emailDelete']);
    Route::post('/email/password', [$c, 'emailPassword']);
    Route::get('/email/list', [$c, 'emailList']);

    // Database
    Route::post('/database/create', [$c, 'dbCreate']);
    Route::post('/database/user/create', [$c, 'dbUserCreate']);
    Route::post('/database/delete', [$c, 'dbDelete']);
    Route::get('/database/list', [$c, 'dbList']);

    // SSL
    Route::post('/ssl/issue', [$c, 'sslIssue']);
    Route::post('/ssl/renew', [$c, 'sslRenew']);
    Route::get('/ssl/status', [$c, 'sslStatus']);
});
