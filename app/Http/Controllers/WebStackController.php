<?php

namespace App\Http\Controllers;

use App\Services\WebStackService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WebStackController extends Controller
{
    protected WebStackService $stackService;

    public function __construct(WebStackService $stackService)
    {
        $this->stackService = $stackService;
        $this->stackService->initializeSettings();
    }

    public function index()
    {
        $stack = $this->stackService->getActiveStack();
        $config = $this->stackService->getStackConfig();
        $components = $this->stackService->detectInstalledComponents();
        $available = $this->stackService->getAvailableStacks();
        $health = $this->stackService->getStackHealth();
        $history = $this->stackService->getSwitchHistory();

        return view('web-stack.index', compact(
            'stack', 'config', 'components', 'available', 'health', 'history'
        ));
    }

    public function install(Request $request)
    {
        $request->validate(['stack' => 'required|string']);
        $stack = $request->input('stack');

        if (!isset(WebStackService::STACKS[$stack])) {
            return back()->with('error', 'Invalid stack.');
        }

        $result = $this->stackService->installStackDependencies($stack);

        if (!empty($result['failed'])) {
            $failedPkgs = array_column($result['failed'], 'package');
            return back()->with('error', 'Failed to install: ' . implode(', ', $failedPkgs));
        }

        return back()->with('success', 'Dependencies installed: ' . implode(', ', $result['installed']));
    }

    public function validate(Request $request)
    {
        $request->validate(['stack' => 'required|string']);
        $stack = $request->input('stack');

        $result = $this->stackService->validateStack($stack);

        if ($result['valid']) {
            return back()->with('success', "Stack '{$stack}' validation passed.");
        }

        return back()->with('error', 'Validation failed: ' . implode('; ', $result['errors']));
    }

    public function switchStack(Request $request)
    {
        $request->validate(['stack' => 'required|string']);
        $stack = $request->input('stack');

        $result = $this->stackService->switchStack($stack, Auth::user()?->username ?? 'admin');

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function rollback()
    {
        $result = $this->stackService->rollbackStack();

        if ($result['success']) {
            return back()->with('success', $result['message']);
        }

        return back()->with('error', $result['message']);
    }

    public function health()
    {
        $health = $this->stackService->getStackHealth();
        return response()->json($health);
    }

    public function testDomain(Request $request)
    {
        $request->validate([
            'domain' => 'required|string',
            'stack' => 'nullable|string',
        ]);

        $stack = $request->input('stack', $this->stackService->getActiveStack());
        $result = $this->stackService->testStackWithDomain($stack, $request->input('domain'));

        return response()->json($result);
    }
}
