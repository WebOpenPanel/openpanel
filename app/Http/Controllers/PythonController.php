<?php

namespace App\Http\Controllers;

use App\Services\PythonService;
use Illuminate\Http\Request;

class PythonController extends Controller
{
    public function index()
    {
        $installed = PythonService::getInstalledVersions();
        $system = PythonService::getSystemVersions();
        $available = PythonService::getAvailablePythons();
        return view('python.index', compact('installed', 'system', 'available'));
    }

    public function install(Request $request)
    {
        $request->validate(['version' => 'required|string']);
        $result = PythonService::installVersion($request->input('version'));
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function remove(Request $request)
    {
        $request->validate(['version' => 'required|string']);
        $result = PythonService::removeVersion($request->input('version'));
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function setUserVersion(Request $request)
    {
        $request->validate(['user' => 'required|string', 'version' => 'required|string']);
        $result = PythonService::setUserVersion($request->input('user'), $request->input('version'));
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
