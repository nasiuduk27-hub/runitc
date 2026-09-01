<?php

namespace App\Http\Controllers\Notifications;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        $userId = (int) auth_user_id();
        $page = max(1, (int) $request->query('page', 1));
        $perPage = 20;
        $offset = ($page - 1) * $perPage;

        $baseQuery = DB::connection('run')
            ->table('sys_notifications')
            ->where('recipient_user_id', $userId);

        $total = (clone $baseQuery)->count();
        $notifications = (clone $baseQuery)
            ->select('rec_id', 'type', 'title', 'message', 'target_url', 'is_read', 'created_at', 'read_at')
            ->orderByDesc('created_at')
            ->orderByDesc('rec_id')
            ->limit($perPage)
            ->offset($offset)
            ->get();

        return view('notifications.index', [
            'notifications' => $notifications,
            'unreadCount' => (clone $baseQuery)->where('is_read', 0)->count(),
            'page' => $page,
            'totalPages' => max(1, (int) ceil($total / $perPage)),
        ]);
    }

    public function read(Request $request): RedirectResponse
    {
        $userId = (int) auth_user_id();
        $id = (int) $request->query('id', 0);

        $notification = DB::connection('run')
            ->table('sys_notifications')
            ->where('rec_id', $id)
            ->where('recipient_user_id', $userId)
            ->first();

        if (! $notification) {
            return redirect()->route('notifications.index');
        }

        DB::connection('run')
            ->table('sys_notifications')
            ->where('rec_id', $id)
            ->where('recipient_user_id', $userId)
            ->update(['is_read' => 1, 'read_at' => DB::raw('IFNULL(read_at, NOW())')]);

        $targetUrl = trim((string) ($notification->target_url ?? ''));

        return $targetUrl !== '' ? redirect($targetUrl) : redirect()->route('notifications.index');
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        DB::connection('run')
            ->table('sys_notifications')
            ->where('recipient_user_id', (int) auth_user_id())
            ->where('is_read', 0)
            ->update(['is_read' => 1, 'read_at' => DB::raw('IFNULL(read_at, NOW())')]);

        return redirect($request->query('redirect', route('notifications.index')));
    }

    public function fetch(Request $request): JsonResponse
    {
        $userId = (int) auth_user_id();

        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $notifications = DB::connection('run')
            ->table('sys_notifications')
            ->select('rec_id', 'title', 'message', 'is_read', 'created_at')
            ->where('recipient_user_id', $userId)
            ->orderByDesc('created_at')
            ->orderByDesc('rec_id')
            ->limit(8)
            ->get();

        return response()->json([
            'success' => true,
            'unread_count' => DB::connection('run')
                ->table('sys_notifications')
                ->where('recipient_user_id', $userId)
                ->where('is_read', 0)
                ->count(),
            'latest_id' => (int) ($notifications->first()->rec_id ?? 0),
            'notifications' => $notifications->map(fn ($notification) => [
                'rec_id' => (int) $notification->rec_id,
                'title' => (string) ($notification->title ?? 'Notifikasi'),
                'message' => (string) ($notification->message ?? ''),
                'is_read' => (int) ($notification->is_read ?? 0),
                'created_at' => ! empty($notification->created_at) ? date('d M Y H:i', strtotime((string) $notification->created_at)) : '-',
            ])->all(),
        ]);
    }
}
