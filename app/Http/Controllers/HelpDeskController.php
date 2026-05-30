<?php

namespace App\Http\Controllers;

use App\Services\HelpDeskService;
use Illuminate\Http\Request;

class HelpDeskController extends Controller
{
    public function index()
    {
        $config = HelpDeskService::getConfig();
        $software = HelpDeskService::getAvailableSoftware();
        return view('helpdesk.index', compact('config', 'software'));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'software' => 'required|string',
            'domain' => 'required|string',
            'admin_email' => 'nullable|email',
            'doc_root' => 'nullable|string',
        ]);
        $result = HelpDeskService::saveConfig(array_merge(HelpDeskService::getConfig(), $data));
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function install(Request $request)
    {
        $request->validate(['software' => 'required|string']);
        $software = $request->input('software');
        $method = match ($software) {
            'phpMyFAQ' => 'installPhpMyFAQ',
            'osTicket' => 'installOSTicket',
            'UVdesk' => 'installUVDesk',
            default => null,
        };
        if (!$method) {
            return back()->with('error', 'Unknown software.');
        }
        $result = HelpDeskService::$method();
        if ($result['success']) {
            $config = HelpDeskService::getConfig();
            $config['installed'] = true;
            HelpDeskService::saveConfig($config);
        }
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
