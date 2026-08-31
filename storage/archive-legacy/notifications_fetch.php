<?php

require_once __DIR__.'/../../config.php';

header('Content-Type: application/json; charset=utf-8');

if (! isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int) ($_SESSION['user_id'] ?? 0);

try {
    $notificationService = new Notification($pdo_run);
    $notifications = $notificationService->getLatest($userId, 8);

    echo json_encode([
        'success' => true,
        'unread_count' => $notificationService->getUnreadCount($userId),
        'latest_id' => ! empty($notifications) ? (int) $notifications[0]['rec_id'] : 0,
        'notifications' => array_map(static function (array $notification): array {
            return [
                'rec_id' => (int) $notification['rec_id'],
                'title' => (string) ($notification['title'] ?? 'Notifikasi'),
                'message' => (string) ($notification['message'] ?? ''),
                'is_read' => (int) ($notification['is_read'] ?? 0),
                'created_at' => date('d M Y H:i', strtotime((string) $notification['created_at'])),
            ];
        }, $notifications),
    ]);
} catch (Throwable $e) {
    error_log('Notification fetch failed: '.$e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to fetch notifications']);
}
