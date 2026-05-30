<?php

namespace App\Http\Controllers;

use App\Services\NotificationService;
use Illuminate\Support\Facades\Response;

class NotificationController extends Controller
{
    public function index()
    {
        $notifications = NotificationService::getNotifications();
        $unreadCount = NotificationService::getUnreadCount();
        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    public function markRead(int $id)
    {
        NotificationService::markAsRead($id);
        return back()->with('success', 'Marked as read.');
    }

    public function markAllRead()
    {
        NotificationService::markAllRead();
        return back()->with('success', 'All marked as read.');
    }

    public function destroy(int $id)
    {
        NotificationService::deleteNotification($id);
        return back()->with('success', 'Notification deleted.');
    }

    public function clear()
    {
        NotificationService::clearAll();
        return back()->with('success', 'All notifications cleared.');
    }

    public function count()
    {
        return Response::json(['count' => NotificationService::getUnreadCount()]);
    }
}
