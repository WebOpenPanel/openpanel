<?php

namespace App\Http\Controllers;

use App\Services\WebServerWizardService;
use Illuminate\Http\Request;

class WebServerWizardController extends Controller
{
    public function index()
    {
        $state = WebServerWizardService::getState();
        $servers = WebServerWizardService::getAvailableServers();
        $phpVersions = WebServerWizardService::getAvailablePhpVersions();
        $mysqlVersions = WebServerWizardService::getAvailableMysqlVersions();

        return view('webserver-wizard.index', compact('state', 'servers', 'phpVersions', 'mysqlVersions'));
    }

    public function step(Request $request, int $step)
    {
        $data = $request->all();

        $result = match ($step) {
            1 => WebServerWizardService::applyStep1($data),
            2 => WebServerWizardService::applyStep2($data),
            3 => WebServerWizardService::applyStep3($data),
            4 => WebServerWizardService::applyStep4($data),
            default => ['success' => false, 'message' => 'Invalid step.'],
        };

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function finish()
    {
        $result = WebServerWizardService::finishWizard();

        if ($result['success']) {
            return redirect()->route('webserver-wizard.index')->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function reset()
    {
        $result = WebServerWizardService::resetWizard();

        return redirect()->route('webserver-wizard.index')->with('success', $result['message']);
    }
}
