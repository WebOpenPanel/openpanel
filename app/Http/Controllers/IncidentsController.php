<?php

namespace App\Http\Controllers;

use App\Services\IncidentsService;
use Illuminate\Http\Request;

class IncidentsController extends Controller
{
    public function index()
    {
        $incidents = IncidentsService::getIncidents(100);
        $stats = IncidentsService::getStats();
        return view('incidents.index', compact('incidents', 'stats'));
    }

    public function scan()
    {
        $result = IncidentsService::scanForIncidents();
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function resolve(string $id)
    {
        $result = IncidentsService::resolveIncident($id);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function destroy(string $id)
    {
        $result = IncidentsService::deleteIncident($id);
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function clear()
    {
        $result = IncidentsService::clearAll();
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
