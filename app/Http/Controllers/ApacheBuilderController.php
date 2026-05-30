<?php

namespace App\Http\Controllers;

use App\Services\ApacheBuilderService;
use Illuminate\Http\Request;

class ApacheBuilderController extends Controller
{
    public function index()
    {
        $currentVersion = ApacheBuilderService::getCurrentVersion();
        $modules = ApacheBuilderService::getLoadedModules();
        $versions = ApacheBuilderService::getAvailableVersions();
        $defaultConfigure = ApacheBuilderService::getDefaultConfigure();
        return view('apache_builder.index', compact('currentVersion', 'modules', 'versions', 'defaultConfigure'));
    }

    public function build(Request $request)
    {
        $request->validate(['version' => 'required|string', 'addons' => 'required|string']);
        $output = ApacheBuilderService::startBuild($request->version, $request->addons, $request->boolean('mod_h264'));
        return back()->with('success', $output);
    }

    public function log()
    {
        $log = ApacheBuilderService::getBuildLog();
        return view('apache_builder.log', compact('log'));
    }
}
