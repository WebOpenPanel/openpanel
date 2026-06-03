@extends('layouts.app')

@section('content')
<div class="p-6 max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold mb-6">Billing Integration — Product Mappings</h1>

    @if(session('success'))
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">{{ session('success') }}</div>
    @endif

    {{-- Create Product --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6 mb-6">
        <h2 class="text-lg font-semibold mb-4">Add Product</h2>
        <form method="POST" action="{{ route('admin.billing.store') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-sm font-medium mb-1">Product Name</label>
                    <input type="text" name="product_name" required class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">External Product ID</label>
                    <input type="text" name="external_product_id" class="w-full border rounded px-3 py-2" placeholder="WHMCS product ID">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Account Type</label>
                    <select name="account_type" class="w-full border rounded px-3 py-2">
                        <option value="shared">Shared Hosting</option>
                        <option value="wordpress">Managed WordPress</option>
                        <option value="reseller">Reseller</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Web Stack</label>
                    <select name="default_web_stack" class="w-full border rounded px-3 py-2">
                        <option value="nginx_phpfpm">nginx + PHP-FPM</option>
                        <option value="apache_phpfpm">Apache + PHP-FPM</option>
                        <option value="nginx_apache">nginx + Apache</option>
                        <option value="nginx_varnish_apache">nginx + Varnish + Apache</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">WordPress Profile</label>
                    <select name="wordpress_profile" class="w-full border rounded px-3 py-2">
                        <option value="safe_default">Safe Default</option>
                        <option value="high_traffic">High Traffic</option>
                        <option value="woocommerce">WooCommerce</option>
                        <option value="membership">Membership</option>
                        <option value="development">Development</option>
                    </select>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Package ID</label>
                    <input type="text" name="package_id" class="w-full border rounded px-3 py-2" placeholder="Optional">
                </div>
                <div class="flex flex-wrap gap-3 items-end">
                    <label class="inline-flex items-center"><input type="checkbox" name="auto_install_wordpress" value="1" class="mr-1"> Auto WP</label>
                    <label class="inline-flex items-center"><input type="checkbox" name="redis_enabled" value="1" class="mr-1"> Redis</label>
                    <label class="inline-flex items-center"><input type="checkbox" name="varnish_enabled" value="1" class="mr-1"> Varnish</label>
                    <label class="inline-flex items-center"><input type="checkbox" name="backups_enabled" value="1" checked class="mr-1"> Backups</label>
                    <label class="inline-flex items-center"><input type="checkbox" name="staging_enabled" value="1" class="mr-1"> Staging</label>
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Max Sites</label>
                    <input type="number" name="max_sites" value="1" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Max Storage (MB)</label>
                    <input type="number" name="max_storage" value="1000" class="w-full border rounded px-3 py-2">
                </div>
            </div>
            <button type="submit" class="mt-4 bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Add Product</button>
        </form>
    </div>

    {{-- Product List --}}
    <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
        <h2 class="text-lg font-semibold mb-4">Products</h2>
        @if($products->isEmpty())
            <p class="text-gray-500">No products configured.</p>
        @else
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b">
                        <th class="text-left py-2">Name</th>
                        <th class="text-left py-2">Type</th>
                        <th class="text-left py-2">Stack</th>
                        <th class="text-left py-2">WP Profile</th>
                        <th class="text-left py-2">Features</th>
                        <th class="text-left py-2">Limits</th>
                        <th class="text-left py-2">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($products as $p)
                        <tr class="border-b">
                            <td class="py-2 font-medium">{{ $p->product_name }}</td>
                            <td class="py-2">{{ $p->account_type }}</td>
                            <td class="py-2 text-xs">{{ $p->default_web_stack }}</td>
                            <td class="py-2">{{ $p->wordpress_profile }}</td>
                            <td class="py-2 text-xs">
                                @if($p->auto_install_wordpress) WP @endif
                                @if($p->redis_enabled) Redis @endif
                                @if($p->varnish_enabled) Varnish @endif
                                @if($p->backups_enabled) Backup @endif
                                @if($p->staging_enabled) Staging @endif
                            </td>
                            <td class="py-2 text-xs">{{ $p->max_sites }} sites, {{ $p->max_storage }}MB</td>
                            <td class="py-2">
                                <form method="POST" action="{{ route('admin.billing.destroy', $p->id) }}" class="inline">
                                    @csrf @method('DELETE')
                                    <button class="text-red-600 hover:underline text-xs" onclick="return confirm('Delete?')">Delete</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
@endsection
