<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use Illuminate\Http\Request;
use Carbon\Carbon;

class NotificationController extends Controller
{
    public function index(Request $request)
    {
        return response()->json(
            AppNotification::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->get()
                ->map(fn(AppNotification $notification) => [
                    'id' => (string) $notification->id,
                    'title' => $notification->title,
                    'message' => $notification->message,
                    'time' => $notification->created_at->diffForHumans(),
                    'read' => $notification->read_at !== null,
                    'kind' => $notification->kind,
                    'profileId' => $notification->profile_id ? (string) $notification->profile_id : null,
                    'conversationId' => $notification->conversation_id ? (string) $notification->conversation_id : null,
                    'avatar' => $notification->avatar,
                ])
        );
    }

    public function unreadCount(Request $request)
    {
        return response()->json(
            AppNotification::query()
                ->where('user_id', $request->user()->id)
                ->whereNull('read_at')
                ->count()
        );
    }

    public function markAsRead(Request $request, AppNotification $notification)
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Notification introuvable.'
            ], 404);
        }

        if ($notification->read_at === null) {
            $notification->update([
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue.',
        ]);
    }

    public function markAllAsRead(Request $request)
    {
        AppNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications ont été marquées comme lues.',
        ]);
    }
}
