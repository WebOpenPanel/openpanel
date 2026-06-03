<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Dashboard') - OpenPanel</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/@alpinejs/collapse@3.x.x/dist/cdn.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>[x-cloak] { display: none !important; } .sidebar-link.active { background-color: rgba(99, 102, 241, 0.1); color: #6366f1; border-right: 3px solid #6366f1; }</style>
    @stack('styles')
</head>
<body class="h-full bg-gray-50" x-data="{ sidebarOpen: true, mobileMenuOpen: false }">
    <div class="flex h-full">
        <aside class="hidden lg:flex lg:flex-col w-64 bg-white border-r border-gray-200 fixed inset-y-0 left-0 z-30 transition-all duration-300" :class="{ 'w-64': sidebarOpen, 'w-16': !sidebarOpen }">
            <div class="flex items-center h-16 px-4 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-user text-white text-sm"></i>
                    </div>
                    <span class="font-bold text-gray-800" x-show="sidebarOpen" x-cloak>OpenPanel</span>
                </div>
            </div>

            <nav class="flex-1 overflow-y-auto py-4" x-show="sidebarOpen" x-cloak>
                <div class="px-3 space-y-1">
                    <a href="{{ route('user.dashboard') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('user.dashboard') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-tachometer-alt w-5 text-center mr-3"></i>
                        <span>Dashboard</span>
                    </a>

                    <div x-data="{ open: {{ request()->is('user/domains*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-globe w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">Domains</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('user.domains.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.domains.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">My Domains</a>
                            <a href="{{ route('user.domains.subdomains') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.domains.subdomains') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Subdomains</a>
                            <a href="{{ route('user.domains.aliases') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.domains.aliases') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Aliases</a>
                        </div>
                    </div>

                    <div x-data="{ open: {{ request()->is('user/email*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-envelope w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">Email</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('user.email.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.email.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Email Accounts</a>
                            <a href="{{ route('user.email.forwarders') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.email.forwarders') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Forwarders</a>
                            <a href="{{ route('user.email.autoresponders') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.email.autoresponders') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Autoresponders</a>
                        </div>
                    </div>

                    <div x-data="{ open: {{ request()->is('user/mysql*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-database w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">Databases</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('user.mysql.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.mysql.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">MySQL Manager</a>
                            <a href="{{ route('user.mysql.phpmyadmin') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.mysql.phpmyadmin') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">phpMyAdmin</a>
                        </div>
                    </div>

                    <a href="{{ route('user.files.index') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('user.files.*') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-folder-open w-5 text-center mr-3"></i>
                        <span>File Manager</span>
                    </a>

                    <a href="{{ route('user.ftp.index') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('user.ftp.*') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-file-import w-5 text-center mr-3"></i>
                        <span>FTP Accounts</span>
                    </a>

                    <a href="{{ route('user.cron.index') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('user.cron.*') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-clock w-5 text-center mr-3"></i>
                        <span>Cron Jobs</span>
                    </a>

                    <div x-data="{ open: {{ request()->is('user/ssl*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-lock w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">SSL Certificates</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('user.ssl.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.ssl.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">SSL Manager</a>
                            <a href="{{ route('user.ssl.generate') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.ssl.generate') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Let's Encrypt</a>
                        </div>
                    </div>

                    <div x-data="{ open: {{ request()->is('user/dns*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-layer-group w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">DNS Zone</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('user.dns.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('user.dns.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">DNS Manager</a>
                        </div>
                    </div>

                    <a href="{{ route('user.stats.index') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('user.stats.*') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-chart-line w-5 text-center mr-3"></i>
                        <span>Statistics</span>
                    </a>

                    <a href="{{ route('user.backups.index') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('user.backups.*') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-hdd w-5 text-center mr-3"></i>
                        <span>Backups</span>
                    </a>

                    <a href="{{ route('user.wordpress.index') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('user.wordpress.*') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fab fa-wordpress w-5 text-center mr-3"></i>
                        <span>WordPress</span>
                    </a>

                    @if(auth()->user()->isReseller())
                    <hr class="my-2 border-gray-200">
                    <p class="px-3 py-1 text-[10px] font-semibold text-gray-400 uppercase tracking-wider">Reseller</p>
                    <a href="{{ route('reseller.dashboard') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('reseller.dashboard') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-users-cog w-5 text-center mr-3 text-purple-600"></i>
                        <span>Reseller Dashboard</span>
                    </a>
                    <a href="{{ route('reseller.accounts') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('reseller.accounts*') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-users w-5 text-center mr-3 text-purple-600"></i>
                        <span>Manage Accounts</span>
                    </a>
                    @endif
                </div>
            </nav>

            <div class="border-t border-gray-200 p-3">
                <button @click="sidebarOpen = !sidebarOpen" class="w-full flex items-center justify-center px-3 py-2 text-sm text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                    <i class="fas" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
                </button>
            </div>
        </aside>

        <aside class="hidden lg:flex lg:flex-col w-16 bg-white border-r border-gray-200 fixed inset-y-0 left-0 z-30" x-show="!sidebarOpen" x-cloak>
            <div class="flex items-center justify-center h-16 border-b border-gray-200">
                <div class="w-8 h-8 bg-emerald-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-user text-white text-sm"></i>
                </div>
            </div>
            <nav class="flex-1 overflow-y-auto py-4">
                <div class="px-2 space-y-2">
                    <a href="{{ route('user.dashboard') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Dashboard"><i class="fas fa-tachometer-alt"></i></a>
                    <a href="{{ route('user.domains.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Domains"><i class="fas fa-globe"></i></a>
                    <a href="{{ route('user.email.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Email"><i class="fas fa-envelope"></i></a>
                    <a href="{{ route('user.mysql.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Databases"><i class="fas fa-database"></i></a>
                    <a href="{{ route('user.files.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Files"><i class="fas fa-folder-open"></i></a>
                    <a href="{{ route('user.ftp.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="FTP"><i class="fas fa-file-import"></i></a>
                    <a href="{{ route('user.ssl.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="SSL"><i class="fas fa-lock"></i></a>
                    <a href="{{ route('user.backups.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Backups"><i class="fas fa-hdd"></i></a>
                </div>
            </nav>
            <div class="border-t border-gray-200 p-3">
                <button @click="sidebarOpen = true" class="w-full flex items-center justify-center p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </aside>

        <div class="flex-1 flex flex-col transition-all duration-300" :class="{ 'lg:ml-64': sidebarOpen, 'lg:ml-16': !sidebarOpen }">
            <header class="bg-white border-b border-gray-200 sticky top-0 z-20">
                <div class="flex items-center justify-between h-16 px-4 lg:px-6">
                    <div class="flex items-center">
                        <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden p-2 text-gray-500 hover:text-gray-700">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <h1 class="ml-3 text-xl font-semibold text-gray-800">@yield('title', 'Dashboard')</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="hidden sm:inline-flex items-center px-3 py-1 rounded-full text-xs font-medium {{ auth()->user()->isReseller() ? 'bg-purple-100 text-purple-800' : 'bg-emerald-100 text-emerald-800' }}">
                            <i class="fas fa-circle {{ auth()->user()->isReseller() ? 'text-purple-400' : 'text-emerald-400' }} mr-1.5 text-[6px]"></i> {{ auth()->user()->isReseller() ? 'Reseller Panel' : 'User Panel' }}
                        </span>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100">
                                <div class="w-8 h-8 bg-emerald-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-emerald-600 text-sm"></i>
                                </div>
                                <span class="hidden sm:block text-sm font-medium text-gray-700">{{ auth()->user()->username }}</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                                @if(auth()->user()->isAdmin())
                                    <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-cog mr-2"></i>Admin Panel</a>
                                    <hr class="my-1">
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                        <i class="fas fa-sign-out-alt mr-2"></i>Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 overflow-auto p-4 lg:p-6">
                @if(session('success'))
                    <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)" x-transition class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg flex items-center">
                        <i class="fas fa-check-circle text-green-500 mr-3"></i>
                        <span class="text-green-800 text-sm flex-1">{{ session('success') }}</span>
                        <button @click="show = false" class="text-green-400 hover:text-green-600"><i class="fas fa-times"></i></button>
                    </div>
                @endif

                @if(session('error'))
                    <div x-data="{ show: true }" x-show="show" x-transition class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg flex items-center">
                        <i class="fas fa-exclamation-circle text-red-500 mr-3"></i>
                        <span class="text-red-800 text-sm flex-1">{{ session('error') }}</span>
                        <button @click="show = false" class="text-red-400 hover:text-red-600"><i class="fas fa-times"></i></button>
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-4 p-4 bg-red-50 border border-red-200 rounded-lg">
                        <ul class="text-sm text-red-700 space-y-1">
                            @foreach($errors->all() as $error)
                                <li><i class="fas fa-times-circle mr-1"></i> {{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        </div>
    </div>

    <div x-show="mobileMenuOpen" @click="mobileMenuOpen = false" class="fixed inset-0 bg-black/50 z-20 lg:hidden" x-transition.opacity x-cloak></div>
    @stack('scripts')
</body>
</html>
