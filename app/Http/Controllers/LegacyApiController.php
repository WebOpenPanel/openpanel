<?php

namespace App\Http\Controllers;

use App\Services\LegacyApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class LegacyApiController extends Controller
{
    public function handle(Request $request)
    {
        $key = $request->input('key', '');
        $action = $request->input('action', '');
        $params = $request->except(['key', 'action']);

        $result = LegacyApiService::handleRequest($key, $action, $params);
        return Response::json($result);
    }
}
