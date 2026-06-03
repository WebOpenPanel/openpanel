<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class TerminalController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        return view('terminal.index');
    }

    public function execute(Request $request)
    {
        $request->validate(['command' => 'required|string|max:1000']);
        $cmd = $request->command;

        $forbidden = ['rm -rf /', 'mkfs', 'dd if=', ':(){', 'fork bomb'];
        foreach ($forbidden as $bad) {
            if (stripos($cmd, $bad) !== false) {
                return new JsonResponse(['output' => 'BLOCKED: Dangerous command detected.', 'exit_code' => 1]);
            }
        }

        $result = $this->process()->timeout(30)->run("{$cmd} 2>&1");

        $output = (string) $result->output();
        $errorOutput = (string) $result->errorOutput();

        return new JsonResponse([
            'output' => $output !== '' ? $output : $errorOutput,
            'exit_code' => $result->exitCode(),
        ]);
    }

    public function history()
    {
        $result = $this->process()->run("cat /root/.bash_history 2>/dev/null | tail -50");
        $lines = array_filter(explode("\n", trim((string) $result->output())));
        return new JsonResponse(['history' => $lines]);
    }
}
