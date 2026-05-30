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
    <style>
        [x-cloak] { display: none !important; }
        .sidebar-link.active { background-color: rgba(99, 102, 241, 0.1); color: #6366f1; border-right: 3px solid #6366f1; }
    </style>
    @stack('styles')
</head>
<body class="h-full bg-gray-50" x-data="{ sidebarOpen: true, mobileMenuOpen: false }">
    <div class="flex h-full">
        <!-- Sidebar -->
        <aside class="hidden lg:flex lg:flex-col w-64 bg-white border-r border-gray-200 fixed inset-y-0 left-0 z-30 transition-all duration-300" :class="{ 'w-64': sidebarOpen, 'w-16': !sidebarOpen }">
            <!-- Logo -->
            <div class="flex items-center h-16 px-4 border-b border-gray-200">
                <div class="flex items-center space-x-3">
                    <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                        <i class="fas fa-server text-white text-sm"></i>
                    </div>
                    <span class="font-bold text-gray-800" x-show="sidebarOpen" x-cloak>OpenPanel</span>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 overflow-y-auto py-4" x-show="sidebarOpen" x-cloak>
                <div class="px-3 space-y-1">
                    <a href="{{ route('dashboard') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('dashboard') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-tachometer-alt w-5 text-center mr-3"></i>
                        <span>Dashboard</span>
                    </a>

                    <!-- User Accounts -->
                    <div x-data="{ open: {{ request()->is('accounts*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-users w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">User Accounts</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('accounts.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('accounts.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">List Accounts</a>
                            <a href="{{ route('accounts.create') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('accounts.create') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">New Account</a>
                        </div>
                    </div>

                    <!-- Domains -->
                    <div x-data="{ open: {{ request()->is('domains*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-globe w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">Domains</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('domains.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('domains.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">List Domains</a>
                            <a href="{{ route('domains.create') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('domains.create') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Add Domain</a>
                        </div>
                    </div>

                    <!-- DNS -->
                    <div x-data="{ open: {{ request()->is('dns*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-layer-group w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">DNS Functions</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('dns.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('dns.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">DNS Zones</a>
                            <a href="{{ route('dns.create') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('dns.create') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Add DNS Zone</a>
                            <a href="{{ route('dns.nameservers') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('dns.nameservers') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Nameservers</a>
                            <a href="{{ route('dns.templates') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('dns.templates') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Zone Templates</a>
                        </div>
                    </div>

                    <!-- MySQL -->
                    <div x-data="{ open: {{ request()->is('mysql*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-database w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">SQL Services</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('mysql.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('mysql.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">MySQL Manager</a>
                            <a href="{{ route('mysql.status') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('mysql.status') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">MySQL Status</a>
                            <a href="{{ route('mysql.processes') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('mysql.processes') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Processes</a>
                            <a href="{{ route('mysql.config') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('mysql.config') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Config</a>
                            <a href="{{ route('mysql.postgresql') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('mysql.postgresql') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">PostgreSQL</a>
                            <a href="{{ route('mysql.mongodb') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('mysql.mongodb') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">MongoDB</a>
                        </div>
                    </div>

                    <!-- Email -->
                    <div x-data="{ open: {{ request()->is('email*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-envelope w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">Email</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('email.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('email.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Email Accounts</a>
                            <a href="{{ route('email.forwarders') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('email.forwarders') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Forwarders</a>
                            <a href="{{ route('email.autoresponders') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('email.autoresponders') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Autoresponders</a>
                            <a href="{{ route('email.queue') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('email.queue') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Mail Queue</a>
                            <a href="{{ route('email.dkim') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('email.dkim') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">DKIM Manager</a>
                            <a href="{{ route('email.mx') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('email.mx') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">MX Routing</a>
                            <a href="{{ route('email.mail-log') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('email.mail-log') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Mail Log</a>
                            <a href="{{ route('email.postfix-config') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('email.postfix-config') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Postfix Config</a>
                        </div>
                    </div>

                    <!-- Packages -->
                    <a href="{{ route('packages.index') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('packages.*') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-box w-5 text-center mr-3"></i>
                        <span>Packages</span>
                    </a>

                    <!-- Cron Jobs -->
                    <div x-data="{ open: {{ request()->is('cron*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-clock w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">Cron Jobs</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('cron.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('cron.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Cron Jobs</a>
                            <a href="{{ route('cron.system') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('cron.system') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">System Cron</a>
                            <a href="{{ route('cron.log') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('cron.log') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Cron Log</a>
                        </div>
                    </div>

                    <!-- File Manager -->
                    <div x-data="{ open: {{ request()->is('files*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-folder-open w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">File Manager</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('files.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('files.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Browse Files</a>
                            <a href="{{ route('files.disk-usage') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('files.disk-usage') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Disk Usage</a>
                        </div>
                    </div>

                    <!-- FTP -->
                    <a href="{{ route('ftp.index') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('ftp.*') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-file-import w-5 text-center mr-3"></i>
                        <span>FTP Accounts</span>
                    </a>

                    <!-- IP Manager -->
                    <div x-data="{ open: {{ request()->is('ip*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-network-wired w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">IP Manager</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('ip.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('ip.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">IP List</a>
                            <a href="{{ route('ip.nat') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('ip.nat') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">NAT Config</a>
                            <a href="{{ route('ip.dns-resolvers') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('ip.dns-resolvers') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">DNS Resolvers</a>
                        </div>
                    </div>

                    <!-- Logs -->
                    <a href="{{ route('logs.index') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('logs.*') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-scroll w-5 text-center mr-3"></i>
                        <span>Log Viewer</span>
                    </a>

                    <!-- Terminal -->
                    <a href="{{ route('server.terminal') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('server.terminal') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-terminal w-5 text-center mr-3"></i>
                        <span>Terminal</span>
                    </a>

                    <!-- SSL -->
                    <div x-data="{ open: {{ request()->is('ssl*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-lock w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">SSL Certificates</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('ssl.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('ssl.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">SSL Manager</a>
                            <a href="{{ route('ssl.generate') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('ssl.generate') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Generate SSL</a>
                        </div>
                    </div>

                    <!-- Security -->
                    <div x-data="{ open: {{ request()->is('security*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-shield-alt w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">Security</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('security.firewall') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('security.firewall') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Firewall</a>
                            <a href="{{ route('security.csf') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('security.csf') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">CSF Firewall</a>
                            <a href="{{ route('security.blocked-ips') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('security.blocked-ips') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Blocked IPs</a>
                            <a href="{{ route('security.allowed-ips') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('security.allowed-ips') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Allowed IPs</a>
                            <a href="{{ route('security.iptables') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('security.iptables') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">IPTables</a>
                            <a href="{{ route('security.mod-security') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('security.mod-security') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">ModSecurity</a>
                            <a href="{{ route('security.maldet') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('security.maldet') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Maldet</a>
                            <a href="{{ route('security.rkhunter') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('security.rkhunter') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">RKHunter</a>
                            <a href="{{ route('security.lynis') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('security.lynis') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Lynis</a>
                            <a href="{{ route('security.cgroups') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('security.cgroups') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Cgroups</a>
                            <a href="{{ route('security.login-security') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('security.login-security') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Login Security</a>
                            <a href="{{ route('security.kernel') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('security.kernel') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Kernel</a>
                        </div>
                    </div>

                    <!-- Services -->
                    <a href="{{ route('services.index') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('services.*') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-cogs w-5 text-center mr-3"></i>
                        <span>Services</span>
                    </a>

                    <!-- Backups -->
                    <div x-data="{ open: {{ request()->is('backups*') ? 'true' : 'false' }} }">
                        <button @click="open = !open" class="flex items-center w-full px-3 py-2.5 text-sm font-medium text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                            <i class="fas fa-hdd w-5 text-center mr-3"></i>
                            <span class="flex-1 text-left">Backups</span>
                            <i class="fas fa-chevron-down text-xs transition-transform" :class="{ 'rotate-180': open }"></i>
                        </button>
                        <div x-show="open" x-collapse class="ml-5 mt-1 space-y-1">
                            <a href="{{ route('backups.index') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('backups.index') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Backup Manager</a>
                            <a href="{{ route('backups.config') }}" class="sidebar-link flex items-center px-3 py-2 text-sm rounded-lg {{ request()->routeIs('backups.config') ? 'active' : 'text-gray-600 hover:bg-gray-50' }}">Backup Config</a>
                        </div>
                    </div>

                    <!-- Settings -->
                    <a href="{{ route('settings.index') }}" class="sidebar-link flex items-center px-3 py-2.5 text-sm font-medium rounded-lg transition-colors {{ request()->routeIs('settings.*') ? 'active' : 'text-gray-700 hover:bg-gray-100' }}">
                        <i class="fas fa-cog w-5 text-center mr-3"></i>
                        <span>OpenPanel Settings</span>
                    </a>
                </div>
            </nav>

            <!-- Collapse button -->
            <div class="border-t border-gray-200 p-3">
                <button @click="sidebarOpen = !sidebarOpen" class="w-full flex items-center justify-center px-3 py-2 text-sm text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100 transition-colors">
                    <i class="fas" :class="sidebarOpen ? 'fa-chevron-left' : 'fa-chevron-right'"></i>
                </button>
            </div>
        </aside>

        <!-- Collapsed Sidebar Icons -->
        <aside class="hidden lg:flex lg:flex-col w-16 bg-white border-r border-gray-200 fixed inset-y-0 left-0 z-30" x-show="!sidebarOpen" x-cloak>
            <div class="flex items-center justify-center h-16 border-b border-gray-200">
                <div class="w-8 h-8 bg-indigo-600 rounded-lg flex items-center justify-center">
                    <i class="fas fa-server text-white text-sm"></i>
                </div>
            </div>
            <nav class="flex-1 overflow-y-auto py-4">
                <div class="px-2 space-y-2">
                    <a href="{{ route('dashboard') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Dashboard"><i class="fas fa-tachometer-alt"></i></a>
                    <a href="{{ route('accounts.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Accounts"><i class="fas fa-users"></i></a>
                    <a href="{{ route('domains.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Domains"><i class="fas fa-globe"></i></a>
                    <a href="{{ route('dns.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="DNS"><i class="fas fa-layer-group"></i></a>
                    <a href="{{ route('mysql.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="MySQL"><i class="fas fa-database"></i></a>
                    <a href="{{ route('email.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Email"><i class="fas fa-envelope"></i></a>
                    <a href="{{ route('packages.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Packages"><i class="fas fa-box"></i></a>
                    <a href="{{ route('files.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Files"><i class="fas fa-folder-open"></i></a>
                    <a href="{{ route('ftp.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="FTP"><i class="fas fa-file-import"></i></a>
                    <a href="{{ route('ip.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="IP Manager"><i class="fas fa-network-wired"></i></a>
                    <a href="{{ route('ssl.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="SSL"><i class="fas fa-lock"></i></a>
                    <a href="{{ route('security.firewall') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Security"><i class="fas fa-shield-alt"></i></a>
                    <a href="{{ route('services.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Services"><i class="fas fa-cogs"></i></a>
                    <a href="{{ route('backups.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Backups"><i class="fas fa-hdd"></i></a>
                    <a href="{{ route('settings.index') }}" class="flex items-center justify-center p-2.5 text-gray-700 rounded-lg hover:bg-gray-100" title="Settings"><i class="fas fa-cog"></i></a>
                </div>
            </nav>
            <div class="border-t border-gray-200 p-3">
                <button @click="sidebarOpen = true" class="w-full flex items-center justify-center p-2 text-gray-500 hover:text-gray-700 rounded-lg hover:bg-gray-100">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col transition-all duration-300" :class="{ 'lg:ml-64': sidebarOpen, 'lg:ml-16': !sidebarOpen }">
            <!-- Top Header -->
            <header class="bg-white border-b border-gray-200 sticky top-0 z-20">
                <div class="flex items-center justify-between h-16 px-4 lg:px-6">
                    <div class="flex items-center">
                        <button @click="mobileMenuOpen = !mobileMenuOpen" class="lg:hidden p-2 text-gray-500 hover:text-gray-700">
                            <i class="fas fa-bars text-xl"></i>
                        </button>
                        <h1 class="ml-3 text-xl font-semibold text-gray-800">@yield('title', 'Dashboard')</h1>
                    </div>
                    <div class="flex items-center space-x-4">
                        <span class="hidden sm:inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-green-100 text-green-800">
                            <i class="fas fa-circle text-green-400 mr-1.5 text-[6px]"></i> System Online
                        </span>
                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="flex items-center space-x-2 p-2 rounded-lg hover:bg-gray-100">
                                <div class="w-8 h-8 bg-indigo-100 rounded-full flex items-center justify-center">
                                    <i class="fas fa-user text-indigo-600 text-sm"></i>
                                </div>
                                <span class="hidden sm:block text-sm font-medium text-gray-700">{{ auth()->user()->username }}</span>
                                <i class="fas fa-chevron-down text-xs text-gray-400"></i>
                            </button>
                            <div x-show="open" @click.away="open = false" x-cloak class="absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 py-1 z-50">
                                <a href="{{ route('settings.index') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50"><i class="fas fa-cog mr-2"></i>Settings</a>
                                <hr class="my-1">
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

            <!-- Page Content -->
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

    <!-- Mobile Sidebar Overlay -->
    <div x-show="mobileMenuOpen" @click="mobileMenuOpen = false" class="fixed inset-0 bg-black/50 z-20 lg:hidden" x-transition.opacity x-cloak></div>

    @stack('scripts')
</body>
</html>
