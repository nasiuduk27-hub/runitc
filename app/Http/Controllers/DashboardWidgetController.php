<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardWidgetController extends Controller
{
    public function index(): JsonResponse
    {
        $userId = (int) auth_user_id();

        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $widgets = DB::connection('run')
            ->table('dashboard_widgets')
            ->where('user_id', $userId)
            ->orderBy('sort_order')
            ->orderBy('rec_id')
            ->get()
            ->map(fn ($widget) => [
                'widget_key' => (string) $widget->widget_key,
                'widget_type' => (string) $widget->widget_type,
                'title' => (string) ($widget->title ?? ''),
                'settings' => json_decode((string) ($widget->settings ?? '{}'), true) ?: [],
                'sort_order' => (int) $widget->sort_order,
                'is_hidden' => (bool) $widget->is_hidden,
            ])
            ->all();

        return response()->json(['success' => true, 'widgets' => $widgets]);
    }

    public function update(Request $request): JsonResponse
    {
        $userId = (int) auth_user_id();

        if ($userId <= 0) {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
        }

        $widgets = $request->input('widgets', []);
        if (! is_array($widgets)) {
            return response()->json(['success' => false, 'message' => 'Invalid widgets payload'], 422);
        }

        DB::connection('run')->transaction(function () use ($widgets, $userId): void {
            $keys = [];

            foreach (array_values($widgets) as $index => $widget) {
                if (! is_array($widget)) {
                    continue;
                }

                $key = trim((string) ($widget['widget_key'] ?? ''));
                if ($key === '') {
                    continue;
                }

                $keys[] = $key;
                $payload = [
                    'user_id' => $userId,
                    'widget_key' => mb_substr($key, 0, 120),
                    'widget_type' => mb_substr((string) ($widget['widget_type'] ?? 'system'), 0, 30),
                    'title' => mb_substr((string) ($widget['title'] ?? ''), 0, 150),
                    'settings' => json_encode($widget['settings'] ?? [], JSON_UNESCAPED_UNICODE),
                    'sort_order' => (int) ($widget['sort_order'] ?? $index),
                    'is_hidden' => ! empty($widget['is_hidden']) ? 1 : 0,
                    'updated_at' => now(),
                ];

                $exists = DB::connection('run')
                    ->table('dashboard_widgets')
                    ->where('user_id', $userId)
                    ->where('widget_key', $key)
                    ->exists();

                if ($exists) {
                    DB::connection('run')
                        ->table('dashboard_widgets')
                        ->where('user_id', $userId)
                        ->where('widget_key', $key)
                        ->update($payload);
                } else {
                    $payload['created_at'] = now();
                    DB::connection('run')->table('dashboard_widgets')->insert($payload);
                }
            }

            DB::connection('run')
                ->table('dashboard_widgets')
                ->where('user_id', $userId)
                ->when($keys !== [], fn ($query) => $query->whereNotIn('widget_key', $keys))
                ->delete();
        });

        return response()->json(['success' => true]);
    }
}
