<?php

namespace App\Http\Controllers;

use App\Services\FileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;

class FileManagerController extends Controller
{
    public function index(Request $request)
    {
        $path = $request->input('path', '/home');
        $items = FileService::listDirectory($path);
        $breadcrumbs = $this->getBreadcrumbs($path);
        return view('filemanager.index', compact('items', 'path', 'breadcrumbs'));
    }

    public function view(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $content = FileService::readFile($request->path);
        $path = $request->path;
        return view('filemanager.view', compact('content', 'path'));
    }

    public function edit(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $content = FileService::readFile($request->path);
        $path = $request->path;
        return view('filemanager.edit', compact('content', 'path'));
    }

    public function save(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'content' => 'required|string',
        ]);
        FileService::writeFile($request->path, $request->content);
        return back()->with('success', 'File saved successfully.');
    }

    public function delete(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        FileService::deleteFile($request->path);
        return back()->with('success', 'File/directory deleted successfully.');
    }

    public function rename(Request $request)
    {
        $request->validate([
            'old_path' => 'required|string',
            'new_name' => 'required|string|max:255',
        ]);
        $newPath = dirname($request->old_path) . '/' . $request->new_name;
        FileService::renameFile($request->old_path, $newPath);
        return back()->with('success', 'Renamed successfully.');
    }

    public function createDirectory(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'name' => 'required|string|max:255',
        ]);
        FileService::createDirectory($request->path . '/' . $request->name);
        return back()->with('success', 'Directory created successfully.');
    }

    public function upload(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'file' => 'required|file|max:102400',
        ]);
        $file = $request->file('file');
        $result = FileService::upload($request->path, $file->getPathname(), $file->getClientOriginalName());
        if ($result['success']) {
            return back()->with('success', 'File uploaded successfully.');
        }
        return back()->with('error', $result['error'] ?? 'Upload failed.');
    }

    public function permissions(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'permissions' => 'required|string|max:4',
        ]);
        FileService::changePermissions($request->path, $request->permissions);
        return back()->with('success', 'Permissions changed successfully.');
    }

    public function compress(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        FileService::compress($request->path);
        return back()->with('success', 'Compressed successfully.');
    }

    public function extract(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        FileService::extract($request->path);
        return back()->with('success', 'Extracted successfully.');
    }

    public function download(Request $request)
    {
        $request->validate(['path' => 'required|string']);
        $filePath = FileService::download($request->path);
        if ($filePath === null || $filePath === false) return back()->with('error', 'File not found.');
        return Response::download($filePath);
    }

    public function search(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
            'query' => 'required|string|min:2',
        ]);
        $path = $request->input('path', '');
        $query = $request->input('query', '');
        $results = FileService::searchFiles($path, $query);
        return view('filemanager.search', compact('results', 'path'));
    }

    public function diskUsage()
    {
        $usage = FileService::getDiskUsage('/home');
        $details = FileService::getDiskDetails('/');
        $inodes = FileService::getInodeUsage();
        return view('filemanager.disk-usage', compact('usage', 'details', 'inodes'));
    }

    private function getBreadcrumbs(string $path): array
    {
        $parts = explode('/', trim($path, '/'));
        $breadcrumbs = [];
        $current = '';
        foreach ($parts as $part) {
            $current .= '/' . $part;
            $breadcrumbs[] = ['name' => $part, 'path' => $current];
        }
        return $breadcrumbs;
    }
}
