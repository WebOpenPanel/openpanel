@extends('layouts.app')

@section('title', 'Web Server Wizard')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h2 class="text-2xl font-bold text-gray-800">Web Server Setup Wizard</h2>
            <p class="text-gray-600 mt-1">Configure your web server stack step by step</p>
        </div>
        @if($state['completed'])
            <form method="POST" action="{{ route('webserver-wizard.reset') }}">
                @csrf
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                    <i class="fas fa-redo mr-2"></i>Reset Wizard
                </button>
            </form>
        @endif
    </div>

    @if($state['completed'])
        <div class="bg-green-50 border border-green-200 rounded-xl p-6">
            <div class="flex items-center">
                <i class="fas fa-check-circle text-green-500 text-2xl mr-4"></i>
                <div>
                    <h3 class="text-lg font-semibold text-green-800">Wizard Completed</h3>
                    <p class="text-green-700">Configuration applied on {{ $state['completed_at'] ?? 'N/A' }}</p>
                </div>
            </div>
            @if(!empty($state['config']))
                <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($state['config'] as $key => $value)
                        @if(!is_array($value))
                            <div class="bg-white rounded-lg p-3 border border-green-100">
                                <span class="text-xs text-gray-500 uppercase">{{ str_replace('_', ' ', $key) }}</span>
                                <p class="text-sm font-medium text-gray-800">{{ $value }}</p>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    @else
        @php $currentStep = $state['step'] ?? 1; @endphp

        <div class="flex items-center space-x-2 mb-6">
            @for($i = 1; $i <= 4; $i++)
                <div class="flex items-center">
                    <div class="w-8 h-8 rounded-full flex items-center justify-center text-sm font-medium {{ $i <= $currentStep ? 'bg-indigo-600 text-white' : 'bg-gray-200 text-gray-500' }}">
                        {{ $i }}
                    </div>
                    @if($i < 4)
                        <div class="w-12 h-1 {{ $i < $currentStep ? 'bg-indigo-600' : 'bg-gray-200' }}"></div>
                    @endif
                </div>
            @endfor
        </div>

        @if($currentStep == 1)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-server mr-2 text-indigo-600"></i>Step 1: Hostname & DNS</h3>
                <form method="POST" action="{{ route('webserver-wizard.step', 1) }}">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Hostname</label>
                            <input type="text" name="hostname" value="{{ $state['config']['hostname'] ?? gethostname() }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nameserver 1</label>
                            <input type="text" name="nameserver1" value="{{ $state['config']['nameserver1'] ?? '8.8.8.8' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nameserver 2</label>
                            <input type="text" name="nameserver2" value="{{ $state['config']['nameserver2'] ?? '8.8.4.4' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="mt-4">
                        <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Next <i class="fas fa-arrow-right ml-2"></i></button>
                    </div>
                </form>
            </div>
        @endif

        @if($currentStep == 2)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-sitemap mr-2 text-indigo-600"></i>Step 2: Web Server Stack</h3>
                <form method="POST" action="{{ route('webserver-wizard.step', 2) }}">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                        @foreach($servers as $key => $server)
                            <label class="relative cursor-pointer">
                                <input type="radio" name="web_server" value="{{ $key }}" class="peer sr-only" {{ ($state['config']['web_server'] ?? '') === $key ? 'checked' : '' }}>
                                <div class="border-2 border-gray-200 rounded-xl p-4 peer-checked:border-indigo-600 peer-checked:bg-indigo-50 hover:border-gray-300 transition-colors">
                                    <h4 class="font-semibold text-gray-800">{{ $server['name'] }}</h4>
                                    <p class="text-sm text-gray-600 mt-1">{{ $server['description'] }}</p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                    <div class="mt-4 flex space-x-3">
                        <a href="{{ route('webserver-wizard.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors"><i class="fas fa-arrow-left mr-2"></i>Back</a>
                        <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Next <i class="fas fa-arrow-right ml-2"></i></button>
                    </div>
                </form>
            </div>
        @endif

        @if($currentStep == 3)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fab fa-php mr-2 text-indigo-600"></i>Step 3: PHP Configuration</h3>
                <form method="POST" action="{{ route('webserver-wizard.step', 3) }}">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Default PHP Version</label>
                            <select name="php_version" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                @forelse($phpVersions as $v)
                                    <option value="{{ $v }}" {{ ($state['config']['php_version'] ?? '') === $v ? 'selected' : '' }}>{{ $v }}</option>
                                @empty
                                    <option value="8.3">PHP 8.3</option>
                                    <option value="8.2">PHP 8.2</option>
                                    <option value="8.1">PHP 8.1</option>
                                @endforelse
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Memory Limit</label>
                            <select name="php_settings[memory_limit]" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                <option value="128M">128M</option>
                                <option value="256M" selected>256M</option>
                                <option value="512M">512M</option>
                                <option value="1G">1G</option>
                            </select>
                        </div>
                    </div>
                    <div class="mt-4 flex space-x-3">
                        <a href="{{ route('webserver-wizard.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors"><i class="fas fa-arrow-left mr-2"></i>Back</a>
                        <button type="submit" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">Next <i class="fas fa-arrow-right ml-2"></i></button>
                    </div>
                </form>
            </div>
        @endif

        @if($currentStep == 4)
            <div class="bg-white rounded-xl shadow-sm border border-gray-200 p-6">
                <h3 class="text-lg font-semibold text-gray-800 mb-4"><i class="fas fa-database mr-2 text-indigo-600"></i>Step 4: MySQL / MariaDB</h3>
                <form method="POST" action="{{ route('webserver-wizard.step', 4) }}">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Database Version</label>
                            <select name="mysql_version" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500">
                                @forelse($mysqlVersions as $mv)
                                    <option value="{{ $mv['version'] }}">{{ $mv['type'] }} {{ $mv['version'] }}</option>
                                @empty
                                    <option value="10.11">MariaDB 10.11</option>
                                    <option value="8.0">MySQL 8.0</option>
                                @endforelse
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Root Password</label>
                            <input type="password" name="mysql_root_password" value="{{ $state['config']['mysql_root_password'] ?? '' }}" class="w-full px-3 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500" placeholder="Enter MySQL root password">
                        </div>
                    </div>
                    <div class="mt-4 flex space-x-3">
                        <a href="{{ route('webserver-wizard.index') }}" class="px-6 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors"><i class="fas fa-arrow-left mr-2"></i>Back</a>
                        <form method="POST" action="{{ route('webserver-wizard.finish') }}" class="inline">
                            @csrf
                            <button type="submit" class="px-6 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors"><i class="fas fa-check mr-2"></i>Finish Setup</button>
                        </form>
                    </div>
                </form>
            </div>
        @endif
    @endif
</div>
@endsection
