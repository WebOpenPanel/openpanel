<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class PhpSelectorController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $installed = $this->getInstalledVersions();
        $current = $this->getCurrentVersion();
        $available = $this->getAvailableVersions();

        return view('php-selector.index', compact('installed', 'current', 'available'));
    }

    public function switchVersion(Request $request)
    {
        $request->validate(['version' => 'required|string']);
        $version = preg_replace('/[^0-9.]/', '', $request->version);

        $result = $this->process()->run("alternatives --set php /usr/bin/php-{$version} 2>&1");
        if ($result->failed()) {
            return back()->with('error', 'Switch failed: ' . $result->errorOutput());
        }
        $this->process()->run("systemctl restart php-fpm 2>/dev/null");
        return back()->with('success', "PHP switched to {$version}.");
    }

    public function installVersion(Request $request)
    {
        $request->validate(['version' => 'required|string']);
        $version = preg_replace('/[^0-9.]/', '', $request->version);
        $major = substr($version, 0, 3);

        $result = $this->process()->run("dnf -y install php{$major}-php php{$major}-php-mysqlnd php{$major}-php-fpm php{$major}-php-gd php{$major}-php-mbstring php{$major}-php-xml php{$major}-php-curl php{$major}-php-zip php{$major}-php-json php{$major}-php-bcmath php{$major}-php-intl php{$major}-php-opcache php{$major}-php-redis php{$major}-php-pdo 2>&1");

        if ($result->failed()) {
            return back()->with('error', 'Install failed: ' . $result->errorOutput());
        }
        return back()->with('success', "PHP {$version} installed.");
    }

    public function removeVersion(Request $request)
    {
        $request->validate(['version' => 'required|string']);
        $version = preg_replace('/[^0-9.]/', '', $request->version);
        $major = substr($version, 0, 3);

        $result = $this->process()->run("dnf -y remove 'php{$major}-php*' 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', $result->successful() ? "PHP {$version} removed." : $result->errorOutput());
    }

    public function getModules(Request $request)
    {
        $request->validate(['version' => 'required|string']);
        $version = preg_replace('/[^0-9.]/', '', $request->version);

        $result = $this->process()->run("php-{$version} -m 2>/dev/null || php{$version} -m 2>/dev/null");
        return new JsonResponse(['modules' => array_filter(explode("\n", trim((string) $result->output())))]);
    }

    protected function getCurrentVersion(): string
    {
        $result = $this->process()->run("php -v | head -1 | awk '{print $2}'");
        return trim((string) $result->output());
    }

    protected function getInstalledVersions(): array
    {
        $versions = [];
        $result = $this->process()->run("alternatives --display php 2>/dev/null | grep php-");
        foreach (explode("\n", trim((string) $result->output())) as $line) {
            if (preg_match('/php-([\d.]+)/', $line, $m)) {
                $versions[] = $m[1];
            }
        }
        if (empty($versions)) {
            $v = $this->getCurrentVersion();
            if ($v) $versions[] = $v;
        }
        return array_unique($versions);
    }

    protected function getAvailableVersions(): array
    {
        return ['8.1', '8.2', '8.3', '8.4'];
    }
}
