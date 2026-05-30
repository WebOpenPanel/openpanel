<?php

namespace App\Http\Controllers;

use App\Services\BandwidthService;
use Illuminate\Http\Request;

class BandwidthController extends Controller
{
    public function index()
    {
        $period = request('period', 'today');
        $usage = BandwidthService::getAllUsage($period);
        $interfaces = BandwidthService::getInterfaces();
        $topUsers = BandwidthService::getTopUsers($period === 'today' ? 'month' : $period, 10);
        return view('bandwidth.index', compact('usage', 'period', 'interfaces', 'topUsers'));
    }

    public function user(Request $request)
    {
        $request->validate(['user' => 'required|string']);
        $user = $request->input('user');
        $period = request('period', 'today');
        $usage = BandwidthService::getUserUsage($user, $period);
        $history = BandwidthService::getHistory($user, 30);
        return view('bandwidth.user', compact('user', 'usage', 'history', 'period'));
    }

    public function interface(Request $request)
    {
        $request->validate(['interface' => 'required|string']);
        $iface = $request->input('interface');
        $usage = BandwidthService::getInterfaceUsage($iface);
        return view('bandwidth.interface', compact('iface', 'usage'));
    }
}
