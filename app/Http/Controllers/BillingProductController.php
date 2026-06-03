<?php

namespace App\Http\Controllers;

use App\Models\BillingProduct;
use Illuminate\Http\Request;

class BillingProductController extends Controller
{
    public function index()
    {
        $products = BillingProduct::orderBy('id')->get();
        return view('billing.index', compact('products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_name' => 'required|string|max:100',
            'account_type' => 'required|in:shared,wordpress,reseller',
        ]);

        BillingProduct::create(array_merge($request->except(['_token']), [
            'redis_enabled' => $request->boolean('redis_enabled'),
            'varnish_enabled' => $request->boolean('varnish_enabled'),
            'backups_enabled' => $request->boolean('backups_enabled'),
            'staging_enabled' => $request->boolean('staging_enabled'),
            'auto_install_wordpress' => $request->boolean('auto_install_wordpress'),
        ]));

        return back()->with('success', 'Product created.');
    }

    public function update(Request $request, $id)
    {
        $product = BillingProduct::findOrFail($id);
        $product->update(array_merge($request->except(['_token', '_method']), [
            'redis_enabled' => $request->boolean('redis_enabled'),
            'varnish_enabled' => $request->boolean('varnish_enabled'),
            'backups_enabled' => $request->boolean('backups_enabled'),
            'staging_enabled' => $request->boolean('staging_enabled'),
            'auto_install_wordpress' => $request->boolean('auto_install_wordpress'),
        ]));

        return back()->with('success', 'Product updated.');
    }

    public function destroy($id)
    {
        BillingProduct::findOrFail($id)->delete();
        return back()->with('success', 'Product deleted.');
    }
}
