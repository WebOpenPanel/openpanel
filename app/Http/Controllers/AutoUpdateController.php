<?php

namespace App\Http\Controllers;

use App\Services\AutoUpdateService;
use Illuminate\Http\Request;

class AutoUpdateController extends Controller
{
    public function index()
    {
        $config = AutoUpdateService::getConfig();
        $updates = AutoUpdateService::checkForUpdates();
        return view('auto_update.index', compact('config', 'updates'));
    }

    public function save(Request $request)
    {
        AutoUpdateService::saveConfig($request->except(['_token']));
        return back()->with('success', 'Auto-update config saved.');
    }

    public function updatePma()
    {
        $output = AutoUpdateService::updatePma();
        return back()->with('output', $output)->with('success', 'phpMyAdmin updated.');
    }

    public function updateRoundcube()
    {
        $output = AutoUpdateService::updateRoundcube();
        return back()->with('output', $output)->with('success', 'Roundcube updated.');
    }
}
