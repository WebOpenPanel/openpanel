<?php

namespace App\Http\Controllers;

use App\Services\ApiService;

class ApiController extends Controller
{
    public function index()
    {
        $apiKey = ApiService::getApiKey();
        $isActive = ApiService::isActive();
        return view('api.index', compact('apiKey', 'isActive'));
    }

    public function generate()
    {
        $key = ApiService::generateApiKey();
        return back()->with('success', 'API key generated.')->with('key', $key);
    }

    public function destroy()
    {
        ApiService::deleteApiKey();
        return back()->with('success', 'API key deleted.');
    }
}
