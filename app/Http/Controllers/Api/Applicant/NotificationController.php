<?php

namespace App\Http\Controllers\Api\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    /**
     * Get all notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $user = Auth::user();
        
        $notifications = $user->notifications()
            ->latest()
            ->paginate($request->get('per_page', 20));

        return response()->json($notifications);
    }

    /**
     * Get unread notifications count.
     */
    public function unreadCount(): JsonResponse
    {
        $user = Auth::user();
        $count = $user->unreadNotifications()->count();

        return response()->json(['count' => $count]);
    }

    /**
     * Mark notification as read.
     */
    public function markAsRead(string $notificationId): JsonResponse
    {
        $notification = \Illuminate\Notifications\DatabaseNotification::findOrFail($notificationId);
        
        // Ensure user can only mark their own notifications
        if ($notification->notifiable_id !== Auth::id() || $notification->notifiable_type !== User::class) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->markAsRead();

        return response()->json(['message' => 'Notification marked as read']);
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(): JsonResponse
    {
        $user = Auth::user();
        
        $user->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    /**
     * Delete a notification.
     */
    public function destroy(string $notificationId): JsonResponse
    {
        $notification = \Illuminate\Notifications\DatabaseNotification::findOrFail($notificationId);
        
        // Ensure user can only delete their own notifications
        if ($notification->notifiable_id !== Auth::id() || $notification->notifiable_type !== User::class) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $notification->delete();

        return response()->json(['message' => 'Notification deleted']);
    }

    /**
     * Create a notification for application status change.
     */
    public static function createApplicationNotification(User $user, Application $application, string $type, string $message): void
    {
        $user->notify(new \App\Notifications\ApplicationStatusChanged($application, $type, $message));
    }
}
