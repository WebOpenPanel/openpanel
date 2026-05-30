<?php

namespace App\Http\Controllers;

use App\Services\SnuffleupagusService;
use Illuminate\Http\Request;

class SnuffleupagusController extends Controller
{
    public function index()
    {
        $installed = SnuffleupagusService::isInstalled();
        $config = SnuffleupagusService::getConfig();
        $rules = SnuffleupagusService::getRules();
        return view('snuffleupagus.index', compact('installed', 'config', 'rules'));
    }

    public function install()
    {
        $result = SnuffleupagusService::install();
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function saveConfig(Request $request)
    {
        $data = $request->validate([
            'cookie_encrypt' => 'nullable|boolean',
            'disable_xxe' => 'nullable|boolean',
            'disable_eval' => 'nullable|boolean',
            'disable_exec' => 'nullable|boolean',
            'global_strict' => 'nullable|boolean',
            'allow_broken' => 'nullable|boolean',
            'custom_rules' => 'nullable|string',
        ]);
        $data['cookie_encrypt'] = $request->boolean('cookie_encrypt');
        $data['disable_xxe'] = $request->boolean('disable_xxe');
        $data['disable_eval'] = $request->boolean('disable_eval');
        $data['disable_exec'] = $request->boolean('disable_exec');
        $data['global_strict'] = $request->boolean('global_strict');
        $data['allow_broken'] = $request->boolean('allow_broken');
        $result = SnuffleupagusService::saveConfig($data);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function saveRules(Request $request)
    {
        $request->validate(['rules' => 'required|string']);
        $result = SnuffleupagusService::saveRules($request->input('rules'));
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
