<?php

namespace App\Http\Controllers;

use App\Services\RblService;
use Illuminate\Http\Request;

class RblController extends Controller
{
    public function index()
    {
        $blacklists = RblService::getBlacklists();
        $customBlacklists = RblService::getCustomBlacklists();
        return view('rbl.index', compact('blacklists', 'customBlacklists'));
    }

    public function check(Request $request)
    {
        $request->validate(['ip' => 'required|ip']);
        $results = RblService::checkIp($request->input('ip'));
        return view('rbl.results', compact('results'));
    }

    public function checkAll()
    {
        $results = RblService::checkAllIps();
        return view('rbl.results-all', compact('results'));
    }

    public function addBlacklist(Request $request)
    {
        $request->validate(['domain' => 'required|string']);
        $result = RblService::addBlacklist($request->input('domain'));
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function removeBlacklist(Request $request)
    {
        $request->validate(['domain' => 'required|string']);
        $result = RblService::removeBlacklist($request->input('domain'));
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
