<?php

namespace App\Http\Controllers;

use App\Services\WebServerTemplateService;
use Illuminate\Http\Request;

class WebServerTemplateController extends Controller
{
    public function index()
    {
        $templates = WebServerTemplateService::getTemplates();
        $types = WebServerTemplateService::getAvailableTypes();
        return view('webserver-templates.index', compact('templates', 'types'));
    }

    public function edit(string $name)
    {
        $content = WebServerTemplateService::getTemplate($name);
        if ($content === null) {
            return back()->with('error', 'Template not found.');
        }
        return view('webserver-templates.edit', compact('name', 'content'));
    }

    public function save(Request $request)
    {
        $request->validate(['name' => 'required|string', 'content' => 'required|string']);
        $result = WebServerTemplateService::saveTemplate($request->input('name'), $request->input('content'));
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(string $name)
    {
        $result = WebServerTemplateService::deleteTemplate($name);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function create()
    {
        $types = WebServerTemplateService::getAvailableTypes();
        return view('webserver-templates.create', compact('types'));
    }

    public function generate(Request $request)
    {
        $request->validate(['type' => 'required|string', 'domain' => 'required|string']);
        $content = WebServerTemplateService::generateVhost($request->input('type'), $request->all());
        return view('webserver-templates.edit', ['name' => $request->input('domain'), 'content' => $content]);
    }
}
