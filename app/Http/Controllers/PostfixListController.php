<?php

namespace App\Http\Controllers;

use App\Services\PostfixListService;
use Illuminate\Http\Request;

class PostfixListController extends Controller
{
    public function index()
    {
        $lists = PostfixListService::getLists();
        return view('postfix-lists.index', compact('lists'));
    }

    public function show(string $type)
    {
        $entries = PostfixListService::getList($type);
        $actions = PostfixListService::getAvailableActions();
        return view('postfix-lists.show', compact('type', 'entries', 'actions'));
    }

    public function add(Request $request)
    {
        $request->validate(['type' => 'required|string', 'pattern' => 'required|string', 'action' => 'nullable|string']);
        $result = PostfixListService::addEntry($request->input('type'), $request->input('pattern'), $request->input('action', 'OK'));
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function remove(Request $request)
    {
        $request->validate(['type' => 'required|string', 'pattern' => 'required|string']);
        $result = PostfixListService::removeEntry($request->input('type'), $request->input('pattern'));
        return back()->with($result['success'] ? 'success' : 'error', $result['message']);
    }
}
