<?php

namespace App\Http\Controllers;

use App\Services\WebScanService;
use Illuminate\Http\Request;

class WebScanController extends Controller
{
    public function index()
    {
        $history = WebScanService::getScanHistory();
        return view('webscan.index', compact('history'));
    }

    public function scan(Request $request)
    {
        $request->validate(['domain' => 'required|string', 'doc_root' => 'nullable|string']);
        $result = WebScanService::scanDomain($request->input('domain'), $request->input('doc_root', ''));
        if ($result['success']) {
            return view('webscan.results', ['results' => $result['results']]);
        }
        return back()->with('error', $result['message']);
    }

    public function results(string $domain)
    {
        $results = WebScanService::getScanResults($domain);
        if (!$results) {
            return back()->with('error', 'No scan results found.');
        }
        return view('webscan.results', compact('results'));
    }
}
