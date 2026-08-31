<?php

require_once __DIR__.'/../../includes/layout_header.php';

$notificationService = new Notification($pdo_run);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;
$totalNotifications = $notificationService->getTotalCount($user_id);
$totalPages = max(1, (int) ceil($totalNotifications / $perPage));
$allNotifications = $notificationService->getAll($user_id, $perPage, $offset);
$markAllUrl = rtrim(BASE_URL, '/').'/modules/notifications/mark_all_read.php?redirect='.urlencode(rtrim(BASE_URL, '/').'/modules/notifications/index.php');
?>

<div class="max-w-4xl mx-auto space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-gray-900">Notifikasi</h1>
            <p class="text-sm text-gray-500 mt-1">Semua notifikasi akun Anda.</p>
        </div>
        <?php if ($notificationService->getUnreadCount($user_id) > 0) { ?>
            <a href="<?php echo htmlspecialchars($markAllUrl) ?>" class="inline-flex items-center justify-center gap-2 rounded-lg bg-brand-primary px-4 py-2 text-sm font-semibold text-white hover:bg-brand-primaryHover transition">
                <i class="fas fa-check-double text-xs"></i>
                Tandai semua dibaca
            </a>
        <?php } ?>
    </div>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <?php if (empty($allNotifications)) { ?>
            <div class="px-6 py-16 text-center text-gray-400">
                <i class="far fa-bell-slash text-4xl mb-3"></i>
                <p class="text-sm font-medium">Belum ada notifikasi.</p>
            </div>
        <?php } else { ?>
            <div class="divide-y divide-gray-100">
                <?php foreach ($allNotifications as $notification) { ?>
                    <?php
                        $isUnread = (int) ($notification['is_read'] ?? 0) === 0;
                    $notificationUrl = rtrim(BASE_URL, '/').'/modules/notifications/read.php?id='.(int) $notification['rec_id'];
                    ?>
                    <a href="<?php echo htmlspecialchars($notificationUrl) ?>" class="block px-5 py-4 hover:bg-gray-50 transition <?php echo $isUnread ? 'bg-blue-50/60' : 'bg-white' ?>">
                        <div class="flex gap-4">
                            <div class="w-11 h-11 rounded-full <?php echo $isUnread ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-500' ?> flex items-center justify-center shrink-0">
                                <i class="fas fa-user-check text-sm"></i>
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars($notification['title'] ?? 'Notifikasi') ?></p>
                                    <span class="text-[10px] font-bold px-2 py-0.5 rounded-full <?php echo $isUnread ? 'bg-blue-100 text-blue-700' : 'bg-gray-100 text-gray-500' ?>">
                                        <?php echo $isUnread ? 'Baru' : 'Dibaca' ?>
                                    </span>
                                </div>
                                <p class="text-sm text-gray-500 mt-1 leading-relaxed"><?php echo htmlspecialchars($notification['message'] ?? '') ?></p>
                                <p class="text-xs text-gray-400 mt-2 font-medium"><?php echo htmlspecialchars(date('d M Y H:i', strtotime((string) $notification['created_at']))) ?></p>
                            </div>
                        </div>
                    </a>
                <?php } ?>
            </div>
        <?php } ?>
    </div>

    <?php if ($totalPages > 1) { ?>
        <div class="flex items-center justify-between gap-3 text-sm">
            <a href="<?php echo htmlspecialchars(rtrim(BASE_URL, '/').'/modules/notifications/index.php?page='.max(1, $page - 1)) ?>" class="px-4 py-2 rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 <?php echo $page <= 1 ? 'pointer-events-none opacity-50' : '' ?>">Sebelumnya</a>
            <span class="text-gray-500">Halaman <?php echo (int) $page ?> dari <?php echo (int) $totalPages ?></span>
            <a href="<?php echo htmlspecialchars(rtrim(BASE_URL, '/').'/modules/notifications/index.php?page='.min($totalPages, $page + 1)) ?>" class="px-4 py-2 rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 <?php echo $page >= $totalPages ? 'pointer-events-none opacity-50' : '' ?>">Berikutnya</a>
        </div>
    <?php } ?>
</div>

<?php require_once __DIR__.'/../../includes/layout_footer.php'; ?>
