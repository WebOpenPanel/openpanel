@extends('layouts.app')
@section('title', 'Web Server Stack Manager')
@section('content')
<div class="space-y-6" x-data="webStackManager()">

    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-xl font-bold text-gray-900">Web Server Stack Manager</h2>
            <p class="text-sm text-gray-500">Manage and switch between web server stacks</p>
        </div>
        <div class="flex gap-2">
            <form method="POST" action="{{ route('web-stack.rollback') }}" class="inline">
                @csrf
                <button type="submit" class="px-3 py-1.5 text-sm bg-gray-100 text-gray-700 rounded-lg hover:bg-gray-200" onclick="return confirm('Rollback to previous stack?')">
                    <i class="fas fa-undo mr-1"></i> Rollback
                </button>
            </form>
        </div>
    </div>

    @if(session('success'))
        <div class="bg-green-50 border border-green-200 text-green-800 px-4 py-3 rounded-lg text-sm">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-lg text-sm">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-server mr-2 text-indigo-500"></i>Active Stack</h3>
            <div class="text-2xl font-bold text-indigo-600">{{ \App\Services\WebStackService::STACKS[$stack] ?? $stack }}</div>
            @if($config?->last_switch_at)
                <p class="text-xs text-gray-500 mt-1">Last switch: {{ $config->last_switch_at }} ({{ $config->last_switch_status }})</p>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-heartbeat mr-2 text-green-500"></i>Health</h3>
            @if($health['healthy'])
                <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm font-medium">Healthy</span>
            @else
                <span class="px-3 py-1 bg-red-100 text-red-700 rounded-full text-sm font-medium">Issues Found</span>
                <ul class="mt-2 space-y-1">
                    @foreach($health['issues'] as $issue)
                        <li class="text-xs text-red-600">{{ $issue }}</li>
                    @endforeach
                </ul>
            @endif
        </div>

        <div class="bg-white rounded-xl shadow-sm border p-5">
            <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-puzzle-piece mr-2 text-amber-500"></i>Components</h3>
            <div class="space-y-2">
                @foreach($components as $name => $installed)
                    <div class="flex items-center justify-between text-sm">
                        <span class="text-gray-600">{{ ucfirst(str_replace('_', '-', $name)) }}</span>
                        @if($installed)
                            <span class="px-2 py-0.5 bg-green-100 text-green-700 rounded text-xs">Installed</span>
                        @else
                            <span class="px-2 py-0.5 bg-gray-100 text-gray-500 rounded text-xs">Not Found</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-4"><i class="fas fa-exchange-alt mr-2 text-indigo-500"></i>Switch Stack</h3>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            @foreach($available as $key => $info)
                <div class="border rounded-lg p-4 {{ $key === $stack ? 'border-indigo-500 bg-indigo-50' : 'border-gray-200 hover:border-gray-300' }}">
                    <div class="flex items-center justify-between mb-2">
                        <h4 class="font-medium text-gray-900">{{ $info['label'] }}</h4>
                        @if($key === $stack)
                            <span class="px-2 py-0.5 bg-indigo-100 text-indigo-700 rounded text-xs font-medium">Active</span>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-1 mb-3">
                        @foreach($info['required'] as $comp => $needed)
                            @if($needed)
                                @if($components[$comp] ?? false)
                                    <span class="px-1.5 py-0.5 bg-green-50 text-green-600 rounded text-xs">{{ $comp }}</span>
                                @else
                                    <span class="px-1.5 py-0.5 bg-red-50 text-red-600 rounded text-xs">{{ $comp }} ✗</span>
                                @endif
                            @endif
                        @endforeach
                    </div>

                    <div class="flex gap-2">
                        @if(!$info['available'])
                            <form method="POST" action="{{ route('web-stack.install') }}" class="inline">
                                @csrf
                                <input type="hidden" name="stack" value="{{ $key }}">
                                <button class="px-3 py-1 text-xs bg-amber-100 text-amber-700 rounded hover:bg-amber-200">Install Deps</button>
                            </form>
                        @endif

                        <form method="POST" action="{{ route('web-stack.validate') }}" class="inline">
                            @csrf
                            <input type="hidden" name="stack" value="{{ $key }}">
                            <button class="px-3 py-1 text-xs bg-gray-100 text-gray-700 rounded hover:bg-gray-200">Validate</button>
                        </form>

                        @if($key !== $stack && $info['available'])
                            <form method="POST" action="{{ route('web-stack.switch') }}" class="inline" onsubmit="return confirm('Switch to {{ $info['label'] }}? This will restart web services.')">
                                @csrf
                                <input type="hidden" name="stack" value="{{ $key }}">
                                <button class="px-3 py-1 text-xs bg-indigo-600 text-white rounded hover:bg-indigo-700">Switch</button>
                            </form>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-cog mr-2 text-gray-500"></i>Stack Settings</h3>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="text-gray-500">Nginx Port:</span>
                <span class="ml-2 font-medium">{{ $config?->nginx_public_port ?? 80 }}</span>
            </div>
            <div>
                <span class="text-gray-500">Apache Port:</span>
                <span class="ml-2 font-medium">{{ $config?->apache_backend_port ?? 8080 }}</span>
            </div>
            <div>
                <span class="text-gray-500">Varnish Port:</span>
                <span class="ml-2 font-medium">{{ $config?->varnish_port ?? 6081 }}</span>
            </div>
            <div>
                <span class="text-gray-500">PHP-FPM:</span>
                <span class="ml-2 font-medium">{{ $config?->php_fpm_mode ?? 'socket' }}</span>
            </div>
        </div>
    </div>

    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-vial mr-2 text-blue-500"></i>Test Domain</h3>
        <form method="POST" action="{{ route('web-stack.test-domain') }}" class="flex gap-2" x-on:submit.prevent="testDomain">
            @csrf
            <input type="text" name="domain" x-model="testDomainName" placeholder="test.kha.icu" class="flex-1 px-3 py-2 border rounded-lg text-sm" required>
            <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg text-sm hover:bg-blue-700">Test</button>
        </form>
        <div x-show="testResult" class="mt-2 text-sm" x-html="testResult"></div>
    </div>

    @if(count($history) > 0)
    <div class="bg-white rounded-xl shadow-sm border p-5">
        <h3 class="text-sm font-semibold text-gray-700 mb-3"><i class="fas fa-history mr-2 text-gray-500"></i>Switch History</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-gray-500 border-b">
                        <th class="pb-2 pr-4">Date</th>
                        <th class="pb-2 pr-4">From</th>
                        <th class="pb-2 pr-4">To</th>
                        <th class="pb-2 pr-4">Status</th>
                        <th class="pb-2">By</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($history as $entry)
                    <tr class="border-b last:border-0">
                        <td class="py-2 pr-4 text-gray-500">{{ $entry->created_at }}</td>
                        <td class="py-2 pr-4">{{ \App\Services\WebStackService::STACKS[$entry->from_stack] ?? $entry->from_stack }}</td>
                        <td class="py-2 pr-4">{{ \App\Services\WebStackService::STACKS[$entry->to_stack] ?? $entry->to_stack }}</td>
                        <td class="py-2 pr-4">
                            @php $color = match($entry->status) { 'success' => 'green', 'failed', 'error' => 'red', 'rolled_back' => 'yellow', default => 'gray' }; @endphp
                            <span class="px-2 py-0.5 bg-{{ $color }}-100 text-{{ $color }}-700 rounded text-xs">{{ $entry->status }}</span>
                        </td>
                        <td class="py-2">{{ $entry->performed_by ?? '-' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif

</div>

@push('scripts')
<script>
function webStackManager() {
    return {
        testDomainName: 'test.kha.icu',
        testResult: '',
        async testDomain() {
            const form = new URLSearchParams();
            form.append('_token', document.querySelector('meta[name=csrf-token]').content);
            form.append('domain', this.testDomainName);
            try {
                const res = await fetch('{{ route("web-stack.test-domain") }}', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json' },
                    body: form,
                });
                const data = await res.json();
                if (data.success) {
                    this.testResult = `<span class="text-green-600">✓ ${data.domain} → HTTP ${data.http_code} on port ${data.port}</span>`;
                } else {
                    this.testResult = `<span class="text-red-600">✗ ${data.domain} → HTTP ${data.http_code}</span>`;
                }
            } catch (e) {
                this.testResult = `<span class="text-red-600">Error: ${e.message}</span>`;
            }
        }
    };
}
</script>
@endpush
@endsection
