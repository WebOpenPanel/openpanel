<?php

namespace App\Http\Controllers;

use App\Services\MessengerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessengerController extends Controller
{
    public function index()
    {
        $user = Auth::user()->username ?? 'admin';
        $messages = MessengerService::getMessages($user);
        $unreadCount = MessengerService::getUnreadCount($user);
        return view('messenger.index', compact('messages', 'unreadCount'));
    }

    public function send(Request $request)
    {
        $request->validate(['to' => 'required|string', 'message' => 'required|string']);
        $from = Auth::user()->username ?? 'admin';
        MessengerService::sendMessage($from, $request->to, $request->message);
        return back()->with('success', 'Message sent.');
    }

    public function markRead()
    {
        $user = Auth::user()->username ?? 'admin';
        MessengerService::markRead($user);
        return back()->with('success', 'All marked as read.');
    }

    public function destroy(int $index)
    {
        $user = Auth::user()->username ?? 'admin';
        MessengerService::deleteMessage($user, $index);
        return back()->with('success', 'Message deleted.');
    }
}
