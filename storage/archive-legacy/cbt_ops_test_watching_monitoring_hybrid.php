<?php

// Tampilan khusus Monitoring Hybrid.
// User bisa memilih nomor admin hybrid langsung dari menu ini.

define('MONITORING_HYBRID_VIEW', true);
$_GET['monitoring_mode'] = 'hybrid';

require __DIR__.'/monitoring.php';
