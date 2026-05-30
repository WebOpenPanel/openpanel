<?php

namespace App\Http\Controllers;

use App\Services\CgtopService;

class CgtopController extends Controller
{
    public function index()
    {
        $top = CgtopService::getTop();
        $status = CgtopService::getCgroupStatus();
        return view('cgtop.index', compact('top', 'status'));
    }

    public function cpu()
    {
        $top = CgtopService::getTopCpu();
        return view('cgtop.cpu', compact('top'));
    }

    public function memory()
    {
        $top = CgtopService::getTopMemory();
        return view('cgtop.memory', compact('top'));
    }

    public function restart()
    {
        CgtopService::restartCgroups();
        return back()->with('success', 'Cgroups restarted.');
    }
}
