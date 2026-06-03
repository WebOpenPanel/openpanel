<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingProduct extends Model
{
    protected $fillable = [
        'external_product_id', 'product_name', 'package_id', 'account_type',
        'default_web_stack', 'auto_install_wordpress', 'wordpress_profile',
        'redis_enabled', 'varnish_enabled', 'backups_enabled', 'staging_enabled',
        'max_sites', 'max_storage', 'max_bandwidth', 'max_databases',
        'max_email_accounts', 'max_ftp_accounts',
    ];

    protected $casts = [
        'redis_enabled' => 'boolean',
        'varnish_enabled' => 'boolean',
        'backups_enabled' => 'boolean',
        'staging_enabled' => 'boolean',
        'auto_install_wordpress' => 'boolean',
    ];
}
