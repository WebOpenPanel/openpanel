<?php

namespace App\Http\Controllers;

use App\Services\FileService;
use Illuminate\Http\Request;

class LogController extends Controller
{
    public function index(Request $request)
    {
        $logDir = $request->get('dir', '/var/log');
        $files = FileService::getLogFiles($logDir);
        return view('logs.index', compact('files', 'logDir'));
    }

    public function view(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $lines = (int) $request->get('lines', 200);
        $content = FileService::tailLog($request->path, $lines);
        $path = $request->path;
        return view('logs.view', compact('content', 'path'));
    }

    public function search(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'pattern' => 'required|string|min:1',
        ]);
        $results = FileService::searchLog($request->path, $request->pattern);
        return view('logs.search', compact('results', 'path'));
    }
}
