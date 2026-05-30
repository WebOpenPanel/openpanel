<?php

namespace App\Http\Controllers;

use App\Services\PolicydService;
use Illuminate\Http\Request;

class PolicydController extends Controller
{
    public function index()
    {
        $status = PolicydService::getStatus();
        $policies = $status['installed'] ? PolicydService::getPolicies() : [];
        $rateLimits = $status['installed'] ? PolicydService::getRateLimits() : [];
        return view('policyd.index', compact('status', 'policies', 'rateLimits'));
    }

    public function install()
    {
        $result = PolicydService::install();
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function addRateLimit(Request $request)
    {
        $data = $request->validate([
            'policy_id' => 'required|integer',
            'track' => 'required|string',
            'period' => 'required|integer',
            'max_messages' => 'required|integer',
            'max_size' => 'nullable|integer',
        ]);
        $result = PolicydService::addRateLimit($data);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function removeRateLimit(int $id)
    {
        $result = PolicydService::removeRateLimit($id);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function togglePolicy(int $id, Request $request)
    {
        $disabled = $request->boolean('disabled') ? 0 : 1;
        $result = PolicydService::togglePolicy($id, $disabled);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function restart()
    {
        PolicydService::restart();
        return back()->with('success', 'Policyd restarted.');
    }
}
