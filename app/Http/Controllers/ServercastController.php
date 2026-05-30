<?php

namespace App\Http\Controllers;

use App\Services\ServercastService;
use Illuminate\Http\Request;

class ServercastController extends Controller
{
    public function index()
    {
        $casts = ServercastService::listCasts();
        return view('servercast.index', compact('casts'));
    }

    public function store(Request $request)
    {
        $request->validate(['command' => 'required|string', 'description' => 'nullable|string']);
        ServercastService::addCast($request->all());
        return back()->with('success', 'Cast added.');
    }

    public function execute(string $file)
    {
        $output = ServercastService::executeCast($file);
        return back()->with('output', $output);
    }

    public function destroy(string $file)
    {
        ServercastService::deleteCast($file);
        return back()->with('success', 'Cast deleted.');
    }
}
