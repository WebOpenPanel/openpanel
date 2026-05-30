<?php

namespace App\Http\Controllers;

use App\Services\ObjectStorageService;
use Illuminate\Http\Request;

class ObjectStorageController extends Controller
{
    public function index()
    {
        $config = ObjectStorageService::getConfig();
        return view('object-storage.index', compact('config'));
    }

    public function save(Request $request)
    {
        $data = $request->validate([
            'provider' => 'required|in:s3,minio,other',
            'endpoint' => 'nullable|string',
            'access_key' => 'required|string',
            'secret_key' => 'required|string',
            'region' => 'nullable|string',
            'use_ssl' => 'nullable|boolean',
            'bucket' => 'nullable|string',
        ]);
        $data['use_ssl'] = $request->boolean('use_ssl');
        $result = ObjectStorageService::saveConfig($data);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function test(Request $request)
    {
        $config = ObjectStorageService::getConfig();
        $result = ObjectStorageService::testConnection($config);
        return back()->with($result['success'] ? 'success' : 'error', $result['output'] ?? $result['message'] ?? 'Test completed.');
    }

    public function listBuckets()
    {
        $config = ObjectStorageService::getConfig();
        $result = ObjectStorageService::listBuckets($config);
        return back()->with('buckets', $result['buckets'] ?? []);
    }
}
