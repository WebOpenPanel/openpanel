@extends('user-layouts.app')
@section('title', $site->domain . ' - Performance')
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900">{{ $site->domain }} — Performance</h2>
            <p class="text-sm text-gray-500">Redis, Varnish, PHP-FPM, WP-Cron, Diagnostics</p>
        </div>
        <a href="{{ route('user.wordpress.show', $site->id) }}" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
            <i class="fas fa-arrow-left mr-1"></i> Back to Site
        </a>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    {{-- Overview Cards --}}
    <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-6 gap-4">
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <div class="text-xs text-gray-500 mb-1">TTFB</div>
            <div class="text-lg font-bold {{ ($report['ttfb_ms'] ?? 0) < 200 ? 'text-green-600' : (($report['ttfb_ms'] ?? 0) < 500 ? 'text-yellow-600' : 'text-red-600') }}">{{ $report['ttfb_ms'] ?? '-' }}ms</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <div class="text-xs text-gray-500 mb-1">Redis</div>
            <div class="text-lg font-bold {{ $site->redis_enabled ? 'text-green-600' : 'text-gray-400' }}">{{ $site->redis_enabled ? 'On' : 'Off' }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <div class="text-xs text-gray-500 mb-1">Varnish</div>
            <div class="text-lg font-bold {{ $site->varnish_enabled ? 'text-green-600' : 'text-gray-400' }}">{{ $site->varnish_enabled ? 'On' : 'Off' }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <div class="text-xs text-gray-500 mb-1">Object Cache</div>
            <div class="text-lg font-bold {{ ($report['object_cache_dropin'] ?? false) ? 'text-green-600' : 'text-gray-400' }}">{{ ($report['object_cache_dropin'] ?? false) ? 'Active' : 'None' }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <div class="text-xs text-gray-500 mb-1">DB Size</div>
            <div class="text-lg font-bold text-gray-900">{{ $report['db_size_mb'] ?? '-' }} MB</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <div class="text-xs text-gray-500 mb-1">Disk</div>
            <div class="text-lg font-bold text-gray-900">{{ $report['disk_usage'] ?? '-' }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Performance Profile --}}
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-tachometer-alt mr-2 text-indigo-500"></i>Performance Profile</h3>
            <div class="mb-3">
                <span class="px-2 py-1 text-xs rounded-full bg-indigo-100 text-indigo-700 font-medium">{{ $profiles[$site->performance_profile]['label'] ?? 'Safe Default' }}</span>
                <span class="text-xs text-gray-500 ml-2">{{ $profiles[$site->performance_profile]['description'] ?? '' }}</span>
            </div>
            <form method="POST" action="{{ route('user.wordpress.apply-profile', $site->id) }}" class="space-y-2">
                @csrf
                <select name="profile" class="w-full text-sm border rounded-lg px-3 py-2">
                    @foreach($profiles as $key => $p)
                        <option value="{{ $key }}" {{ $site->performance_profile === $key ? 'selected' : '' }}>{{ $p['label'] }}</option>
                    @endforeach
                </select>
                <button type="submit" class="px-3 py-1.5 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700">Apply Profile</button>
            </form>
        </div>

        {{-- Redis --}}
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-memory mr-2 text-red-500"></i>Redis Object Cache</h3>
            <div class="space-y-2 mb-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Service</span>
                    <span class="{{ ($redisHealth['running'] ?? false) ? 'text-green-600' : 'text-red-600' }}">{{ ($redisHealth['running'] ?? false) ? 'Running' : 'Not Running' }}</span>
                </div>
                @if($site->redis_enabled)
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Prefix</span>
                        <span class="text-gray-900 font-mono text-xs">{{ $site->redis_prefix }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">DB Index</span>
                        <span class="text-gray-900">{{ $site->redis_db_index }}</span>
                    </div>
                @endif
            </div>
            <div class="flex gap-2">
                @if($site->redis_enabled)
                    <form method="POST" action="{{ route('user.wordpress.flush-redis', $site->id) }}">
                        @csrf
                        <button type="submit" class="px-3 py-1 text-xs bg-orange-100 text-orange-700 rounded hover:bg-orange-200">Flush Cache</button>
                    </form>
                    <form method="POST" action="{{ route('user.wordpress.enable-redis', $site->id) }}">
                        @csrf
                        <button type="submit" class="px-3 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200">Disable</button>
                    </form>
                @else
                    <form method="POST" action="{{ route('user.wordpress.enable-redis', $site->id) }}">
                        @csrf
                        <button type="submit" class="px-3 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200">Enable Redis</button>
                    </form>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- Varnish --}}
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-bolt mr-2 text-yellow-500"></i>Varnish Cache</h3>
            <div class="space-y-2 mb-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Service</span>
                    <span class="{{ ($varnishStatus['running'] ?? false) ? 'text-green-600' : 'text-red-600' }}">{{ ($varnishStatus['running'] ?? false) ? 'Running' : 'Not Running' }}</span>
                </div>
                @if(($varnishStatus['running'] ?? false))
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Hit Rate</span>
                        <span class="text-gray-900">{{ $varnishStatus['hit_rate'] ?? 0 }}%</span>
                    </div>
                @endif
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Site Cache</span>
                    <span class="{{ $site->varnish_enabled ? 'text-green-600' : 'text-gray-400' }}">{{ $site->varnish_enabled ? 'Enabled' : 'Disabled' }}</span>
                </div>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('user.wordpress.varnish-test', $site->id) }}">
                    @csrf
                    <button type="submit" class="px-3 py-1 text-xs bg-blue-100 text-blue-700 rounded hover:bg-blue-200">Test HIT/MISS</button>
                </form>
                <form method="POST" action="{{ route('user.wordpress.purge-varnish', $site->id) }}">
                    @csrf
                    <button type="submit" class="px-3 py-1 text-xs bg-orange-100 text-orange-700 rounded hover:bg-orange-200">Purge Varnish</button>
                </form>
            </div>
        </div>

        {{-- PHP-FPM --}}
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-server mr-2 text-blue-500"></i>PHP-FPM Pool</h3>
            <form method="POST" action="{{ route('user.wordpress.update-php-fpm', $site->id) }}" class="space-y-3">
                @csrf
                <div class="grid grid-cols-2 gap-3">
                    <div>
                        <label class="text-xs text-gray-500">PM Mode</label>
                        <select name="pm" class="w-full text-sm border rounded px-2 py-1.5">
                            <option value="ondemand" {{ $site->php_fpm_pm === 'ondemand' ? 'selected' : '' }}>On Demand</option>
                            <option value="dynamic" {{ $site->php_fpm_pm === 'dynamic' ? 'selected' : '' }}>Dynamic</option>
                            <option value="static" {{ $site->php_fpm_pm === 'static' ? 'selected' : '' }}>Static</option>
                        </select>
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Max Children</label>
                        <input type="number" name="max_children" value="{{ $site->php_fpm_max_children }}" min="1" max="100" class="w-full text-sm border rounded px-2 py-1.5">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Memory Limit (MB)</label>
                        <input type="number" name="memory_limit" value="{{ $site->php_fpm_memory_limit }}" min="64" max="2048" class="w-full text-sm border rounded px-2 py-1.5">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Max Execution (s)</label>
                        <input type="number" name="max_execution_time" value="{{ $site->php_fpm_max_execution_time }}" min="5" max="600" class="w-full text-sm border rounded px-2 py-1.5">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500">Upload Limit (MB)</label>
                        <input type="number" name="upload_max_filesize" value="{{ $site->php_fpm_upload_max_filesize }}" min="2" max="1024" class="w-full text-sm border rounded px-2 py-1.5">
                    </div>
                </div>
                <button type="submit" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">Update PHP-FPM</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        {{-- WP-Cron --}}
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-clock mr-2 text-purple-500"></i>WP-Cron</h3>
            <div class="space-y-2 mb-3">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">WP-Cron</span>
                    <span class="{{ $site->wp_cron_disabled ? 'text-yellow-600' : 'text-green-600' }}">{{ $site->wp_cron_disabled ? 'Disabled (System Cron)' : 'Active' }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Scheduled Events</span>
                    <span class="text-gray-900">{{ $report['wp_cron']['event_count'] ?? 0 }}</span>
                </div>
            </div>
            <div class="flex gap-2">
                <form method="POST" action="{{ route('user.wordpress.cron-run', $site->id) }}">
                    @csrf
                    <button type="submit" class="px-3 py-1 text-xs bg-purple-100 text-purple-700 rounded hover:bg-purple-200">Run Due Events Now</button>
                </form>
                <form method="POST" action="{{ route('user.wordpress.cron-toggle', $site->id) }}">
                    @csrf
                    <input type="hidden" name="disabled" value="{{ $site->wp_cron_disabled ? 0 : 1 }}">
                    <button type="submit" class="px-3 py-1 text-xs {{ $site->wp_cron_disabled ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700' }} rounded hover:opacity-80">
                        {{ $site->wp_cron_disabled ? 'Re-enable WP-Cron' : 'Use System Cron' }}
                    </button>
                </form>
            </div>
        </div>

        {{-- Diagnostics --}}
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-chart-bar mr-2 text-green-500"></i>Diagnostics</h3>
            <div class="space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Plugins</span>
                    <span class="text-gray-900">{{ $report['plugin_count'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Themes</span>
                    <span class="text-gray-900">{{ $report['theme_count'] ?? 0 }}</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">Autoloaded Options</span>
                    <span class="text-gray-900">{{ $report['autoloaded_options'] ?? 0 }} ({{ $report['autoloaded_size_kb'] ?? '-' }} KB)</span>
                </div>
                <div class="flex justify-between text-sm">
                    <span class="text-gray-600">OPcache</span>
                    <span class="{{ ($report['opcache'] ?? null) ? 'text-green-600' : 'text-gray-400' }}">{{ ($report['opcache'] ?? null) ? 'Active' : 'N/A' }}</span>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
