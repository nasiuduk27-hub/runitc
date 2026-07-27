<?php
// Canonical filing system page is main.php. Keep this entry point for direct folder access.
$query = $_SERVER['QUERY_STRING'] ?? '';
$target = 'main.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $target);
exit;
