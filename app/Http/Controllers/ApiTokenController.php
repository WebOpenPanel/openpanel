<?php

namespace App\Http\Controllers;

use App\Models\ApiToken;
use App\Services\ApiTokenService;
use Illuminate\Http\Request;

class ApiTokenController extends Controller
{
    public function index()
    {
        $tokens = ApiTokenService::list();
        $logs = ApiTokenService::getRecentLogs(30);
        $scopes = ApiToken::availableScopes();
        return view('api.tokens', compact('tokens', 'logs', 'scopes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:100',
            'scopes' => 'required|array',
            'scopes.*' => 'string',
            'allowed_ips' => 'nullable|string',
            'expires_at' => 'nullable|date|after:now',
        ]);

        $ips = null;
        if ($request->filled('allowed_ips')) {
            $ips = array_map('trim', explode(',', $request->allowed_ips));
        }

        $result = ApiTokenService::create([
            'name' => $request->name,
            'scopes' => $request->scopes,
            'allowed_ips' => $ips,
            'expires_at' => $request->expires_at,
        ]);

        if ($result['success']) {
            return back()
                ->with('success', 'Token created. Copy it now — shown only once.')
                ->with('new_token', $result['plain_token']);
        }

        return back()->with('error', $result['message'] ?? 'Failed');
    }

    public function revoke($id)
    {
        $result = ApiTokenService::revoke($id);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function reactivate($id)
    {
        $result = ApiTokenService::reactivate($id);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroy($id)
    {
        $result = ApiTokenService::delete($id);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
