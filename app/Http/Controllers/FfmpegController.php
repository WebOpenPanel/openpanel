<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Process\Factory as ProcessFactory;

class FfmpegController extends Controller
{
    protected function process(): ProcessFactory
    {
        return app(ProcessFactory::class);
    }

    public function index()
    {
        $installed = $this->process()->run("which ffmpeg 2>/dev/null")->successful();
        $version = $installed ? $this->process()->run("ffmpeg -version 2>/dev/null | head -1")->output() : null;
        return view('ffmpeg.index', compact('installed', 'version'));
    }

    public function install()
    {
        $result = $this->process()->run("dnf -y install epel-release 2>&1 && dnf -y install --nogpgcheck https://mirrors.rpmfusion.org/free/el/rpmfusion-free-release-9.noarch.rpm 2>&1 && dnf -y install ffmpeg 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', $result->output());
    }

    public function uninstall()
    {
        $result = $this->process()->run("dnf -y remove ffmpeg 2>&1");
        return back()->with($result->successful() ? 'success' : 'error', $result->output());
    }
}
