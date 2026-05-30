<?php

namespace App\Http\Controllers;

use App\Services\WhmcsService;
use Illuminate\Http\Request;

class WhmcsController extends Controller
{
    public function index()
    {
        $settings = WhmcsService::getSettings();
        return view('whmcs.index', compact('settings'));
    }

    public function save(Request $request)
    {
        WhmcsService::saveSettings($request->except(['_token']));
        return back()->with('success', 'WHMCS settings saved.');
    }

    public function test()
    {
        $result = WhmcsService::testConnection();
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
