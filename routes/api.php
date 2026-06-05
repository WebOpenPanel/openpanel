<?php

use Illuminate\Support\Facades\Route;

// API v1 — WHMCS-compatible provisioning API
// Public health endpoint for load balancers and installer smoke checks.
$c = \App\Http\Controllers\Api\V1Controller::class;
Route::get('v1/health', [$c, 'health']);

Route::prefix('v1')->middleware(\App\Http\Middleware\ApiAuthMiddleware::class)->group(function () use ($c) {
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
    Route::post('/wordpress/staging/push', [$c, 'wpStagingPush']);
    Route::post('/wordpress/staging/delete', [$c, 'wpStagingDelete']);
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
    Route::post('/email/suspend', [$c, 'emailSuspend']);
    Route::post('/email/unsuspend', [$c, 'emailUnsuspend']);
    Route::post('/email/test-auth', [$c, 'emailTestAuth']);
    Route::post('/email/test-delivery', [$c, 'emailTestDelivery']);
    Route::get('/email/deliverability/status', [$c, 'emailDeliverabilityStatus']);
    Route::post('/email/dkim/enable', [$c, 'emailDkimEnable']);
    Route::post('/email/deliverability/dns-helper', [$c, 'emailDeliverabilityDnsHelper']);
    Route::post('/email/deliverability/test-signing', [$c, 'emailDeliverabilityTestSigning']);
    Route::get('/email/list', [$c, 'emailList']);

    // Database
    Route::post('/database/create', [$c, 'dbCreate']);
    Route::post('/database/user/create', [$c, 'dbUserCreate']);
    Route::post('/database/delete', [$c, 'dbDelete']);
    Route::get('/database/list', [$c, 'dbList']);
    Route::get('/database/phpmyadmin/status', [$c, 'phpMyAdminStatus']);

    // SSL
    Route::post('/ssl/issue', [$c, 'sslIssue']);
    Route::post('/ssl/renew', [$c, 'sslRenew']);
    Route::post('/ssl/force-https', [$c, 'sslForceHttps']);
    Route::get('/ssl/status', [$c, 'sslStatus']);

    // Varnish
    Route::get('/varnish/status', [$c, 'varnishStatus']);
    Route::post('/varnish/mode', [$c, 'varnishMode']);
    Route::post('/varnish/purge', [$c, 'varnishPurge']);
    Route::post('/varnish/test', [$c, 'varnishTest']);
});
