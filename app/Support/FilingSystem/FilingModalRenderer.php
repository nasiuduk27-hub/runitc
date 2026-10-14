<?php

namespace App\Support\FilingSystem;

class FilingModalRenderer
{
    public static function html(): string
    {
        $partials = [
            'filing-system.partials.upload-modal',
            'filing-system.partials.share-modal',
            'filing-system.partials.edit-metadata-modal',
            'filing-system.partials.permission-modal',
            'filing-system.partials.info-drawer',
            'filing-system.partials.zip-inspection-modal',
        ];

        $html = '';
        foreach ($partials as $partial) {
            $html .= "\n".view($partial)->render();
        }

        $base = url('/modules/cbt_ops/filing_system');

        return str_replace([
            "fetch('upload.php?action=access_options')",
            "xhr.open('POST', 'upload.php', true)",
            "fetch('info.php?id=' + id)",
            'fetch(`permission.php?action=get&filing_id=${filingId}`)',
            "fetch('permission.php', { method: 'POST', body: fd })",
            "fetch('action.php', { method: 'POST', body: fd })",
            "fetch('inspect.php?id=' + id)",
        ], [
            "fetch('{$base}/upload.php?action=access_options')",
            "xhr.open('POST', '{$base}/upload.php', true)",
            "fetch('{$base}/info.php?id=' + id)",
            'fetch(`'.$base.'/permission.php?action=get&filing_id=${filingId}`)',
            "fetch('{$base}/permission.php', { method: 'POST', body: fd })",
            "fetch('{$base}/action.php', { method: 'POST', body: fd })",
            "fetch('{$base}/inspect.php?id=' + id)",
        ], $html);
    }
}
