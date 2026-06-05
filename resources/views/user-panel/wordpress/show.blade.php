@extends('user-layouts.app')
@section('title', $site->domain . ' - WordPress')
@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900">{{ $site->domain }}</h2>
            <p class="text-sm text-gray-500">{{ $site->site_url }}</p>
        </div>
        <div class="flex gap-2">
            <a href="{{ $site->site_url }}" target="_blank" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <i class="fas fa-external-link-alt mr-1"></i> Visit
            </a>
            <a href="{{ $site->site_url }}/wp-admin" target="_blank" class="px-3 py-1.5 text-sm bg-indigo-100 text-indigo-700 rounded-lg hover:bg-indigo-200">
                <i class="fas fa-cog mr-1"></i> WP Admin
            </a>
            <a href="{{ route('user.wordpress.performance', $site->id) }}" class="px-3 py-1.5 text-sm bg-green-100 text-green-700 rounded-lg hover:bg-green-200">
                <i class="fas fa-tachometer-alt mr-1"></i> Performance
            </a>
            <a href="{{ route('user.wordpress.index') }}" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200">
                <i class="fas fa-arrow-left mr-1"></i> Back
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <div class="text-xs text-gray-500 mb-1">WordPress</div>
            <div class="text-lg font-bold text-gray-900">{{ $site->wp_version ?? '-' }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <div class="text-xs text-gray-500 mb-1">PHP</div>
            <div class="text-lg font-bold text-gray-900">{{ $site->php_version }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <div class="text-xs text-gray-500 mb-1">SSL</div>
            <div class="text-lg font-bold {{ $site->ssl_enabled ? 'text-green-600' : 'text-gray-400' }}">{{ $site->ssl_enabled ? 'On' : 'Off' }}</div>
        </div>
        <div class="bg-white rounded-xl shadow-sm border p-4">
            <div class="text-xs text-gray-500 mb-1">Disk</div>
            <div class="text-lg font-bold text-gray-900">{{ $diskUsage }}</div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-sync mr-2 text-indigo-500"></i>Updates</h3>
            @if(!empty($updates['core']))
                <div class="mb-3 p-3 bg-yellow-50 rounded-lg">
                    <div class="text-sm font-medium text-yellow-800">WordPress core update available</div>
                    <form method="POST" action="{{ route('user.wordpress.update-core', $site->id) }}" class="mt-2">@csrf
                        <button type="submit" class="px-3 py-1 text-xs bg-yellow-600 text-white rounded hover:bg-yellow-700">Update Core</button>
                    </form>
                </div>
            @else
                <p class="text-sm text-green-600 mb-3"><i class="fas fa-check mr-1"></i> Core up to date</p>
            @endif

            @php $outdatedPlugins = collect($updates['plugins'] ?? [])->filter(fn($p) => ($p['update'] ?? '') === 'available'); @endphp
            @if($outdatedPlugins->count())
                <div class="mb-3 p-3 bg-orange-50 rounded-lg">
                    <div class="text-sm font-medium text-orange-800">{{ $outdatedPlugins->count() }} plugin update(s)</div>
                    <form method="POST" action="{{ route('user.wordpress.update-plugins', $site->id) }}" class="mt-2">@csrf
                        <button type="submit" class="px-3 py-1 text-xs bg-orange-600 text-white rounded hover:bg-orange-700">Update All</button>
                    </form>
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-shield-alt mr-2 text-green-500"></i>Security</h3>
            @if($latestScan)
                <div class="space-y-2 mb-3">
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Outdated Core</span>
                        <span class="{{ $latestScan->outdated_core ? 'text-red-600' : 'text-green-600' }}">{{ $latestScan->outdated_core ? 'Yes' : 'No' }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Outdated Plugins</span>
                        <span class="{{ $latestScan->outdated_plugins > 0 ? 'text-orange-600' : 'text-green-600' }}">{{ $latestScan->outdated_plugins }}</span>
                    </div>
                    <div class="flex justify-between text-sm">
                        <span class="text-gray-600">Suspicious Files</span>
                        <span class="{{ $latestScan->suspicious_files > 0 ? 'text-red-600' : 'text-green-600' }}">{{ $latestScan->suspicious_files }}</span>
                    </div>
                </div>
            @else
                <p class="text-sm text-gray-500 mb-3">No scan yet.</p>
            @endif
            <form method="POST" action="{{ route('user.wordpress.scan', $site->id) }}">@csrf
                <button type="submit" class="px-3 py-1 text-xs bg-green-600 text-white rounded hover:bg-green-700">Scan Now</button>
            </form>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-database mr-2 text-blue-500"></i>Backup</h3>
            <div class="flex gap-2 mb-3">
                <form method="POST" action="{{ route('user.wordpress.backup', $site->id) }}">@csrf
                    <input type="hidden" name="type" value="full">
                    <button type="submit" class="px-3 py-1.5 text-xs bg-blue-600 text-white rounded hover:bg-blue-700">Full Backup</button>
                </form>
                <form method="POST" action="{{ route('user.wordpress.backup', $site->id) }}">@csrf
                    <input type="hidden" name="type" value="db">
                    <button type="submit" class="px-3 py-1.5 text-xs bg-blue-500 text-white rounded hover:bg-blue-600">DB Only</button>
                </form>
            </div>
            @if($site->backups->count())
                <div class="space-y-2 max-h-48 overflow-y-auto">
                    @foreach($site->backups->sortByDesc('created_at')->take(5) as $backup)
                        <div class="flex items-center justify-between p-2 bg-gray-50 rounded text-sm">
                            <div>
                                <span class="font-medium">{{ $backup->backup_type }}</span>
                                <span class="text-gray-500 ml-2">{{ $backup->formatted_size }}</span>
                                <span class="text-gray-400 ml-2">{{ $backup->created_at->diffForHumans() }}</span>
                            </div>
                            <form method="POST" action="{{ route('user.wordpress.restore', $site->id) }}">@csrf
                                <input type="hidden" name="backup_id" value="{{ $backup->id }}">
                                <button type="submit" class="text-xs text-orange-600 hover:text-orange-800" onclick="return confirm('Restore this backup?')">Restore</button>
                            </form>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-layer-group mr-2 text-purple-500"></i>Cache & Staging</h3>
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Redis Object Cache</span>
                    @if($site->redis_enabled)
                        <form method="POST" action="{{ route('user.wordpress.disable-redis', $site->id) }}">@csrf
                            <button type="submit" class="px-3 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200">Disable</button>
                        </form>
                    @else
                        <form method="POST" action="{{ route('user.wordpress.enable-redis', $site->id) }}">@csrf
                            <button type="submit" class="px-3 py-1 text-xs bg-green-100 text-green-700 rounded hover:bg-green-200">Enable</button>
                        </form>
                    @endif
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Purge Cache</span>
                    <form method="POST" action="{{ route('user.wordpress.purge-cache', $site->id) }}">@csrf
                        <button type="submit" class="px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200">Purge</button>
                    </form>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-sm text-gray-600">Create Staging</span>
                    <form method="POST" action="{{ route('user.wordpress.staging', $site->id) }}">@csrf
                        <button type="submit" class="px-3 py-1 text-xs bg-purple-100 text-purple-700 rounded hover:bg-purple-200" onclick="return confirm('Create staging at staging.{{ $site->domain }}?')">Create</button>
                    </form>
                </div>
                @foreach(($stagingSites ?? collect()) as $staging)
                    <div class="flex items-center justify-between">
                        <span class="text-sm text-gray-600">{{ $staging->domain }}</span>
                        <div class="flex gap-2">
                            <form method="POST" action="{{ route('user.wordpress.push-staging', $site->id) }}">@csrf
                                <input type="hidden" name="staging_domain" value="{{ $staging->domain }}">
                                <button type="submit" class="px-3 py-1 text-xs bg-amber-100 text-amber-700 rounded hover:bg-amber-200" onclick="return confirm('Push staging to live? A pre-push backup will be created.')">Push</button>
                            </form>
                            <form method="POST" action="{{ route('user.wordpress.delete-staging', $site->id) }}">@csrf
                                <input type="hidden" name="staging_domain" value="{{ $staging->domain }}">
                                <button type="submit" class="px-3 py-1 text-xs bg-red-100 text-red-700 rounded hover:bg-red-200" onclick="return confirm('Delete staging site {{ $staging->domain }}?')">Delete</button>
                            </form>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-puzzle-piece mr-2 text-orange-500"></i>Plugins ({{ count($plugins) }})</h3>
        @if(count($plugins))
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead><tr class="text-left text-xs text-gray-500 uppercase"><th class="py-2 pr-4">Name</th><th class="py-2 pr-4">Version</th><th class="py-2 pr-4">Status</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($plugins as $plugin)
                        <tr>
                            <td class="py-2 pr-4 font-medium">{{ $plugin['name'] ?? $plugin['title'] ?? '-' }}</td>
                            <td class="py-2 pr-4 text-gray-600">{{ $plugin['version'] ?? '-' }}</td>
                            <td class="py-2 pr-4">
                                @if(($plugin['status'] ?? '') === 'active')
                                    <span class="px-2 py-0.5 text-xs bg-green-100 text-green-700 rounded">Active</span>
                                @else
                                    <span class="px-2 py-0.5 text-xs bg-gray-100 text-gray-600 rounded">Inactive</span>
                                @endif
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <p class="text-sm text-gray-500">No plugins found.</p>
        @endif
    </div>
</div>
@endsection
