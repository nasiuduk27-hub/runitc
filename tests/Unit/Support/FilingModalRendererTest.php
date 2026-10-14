<?php

namespace Tests\Unit\Support;

use App\Support\FilingSystem\FilingModalRenderer;
use Tests\TestCase;

/**
 * Fase 2B: modal bagikan memakai satu konstanta endpoint absolut, sehingga
 * pola str_replace share.php di renderer sudah dicabut. Test ini menjaga DUA
 * arah: share relatif tidak boleh muncul lagi, dan penggantian non-share
 * (upload/permission/inspect) harus tetap berjalan.
 */
class FilingModalRendererTest extends TestCase
{
    public function test_modal_share_memakai_endpoint_absolut_bukan_fetch_relatif(): void
    {
        $html = FilingModalRenderer::html();

        $this->assertStringNotContainsString("fetch('share.php", $html);
        $this->assertStringContainsString(
            'const shareEndpoint = '.json_encode(url('/modules/cbt_ops/filing_system/share.php')),
            $html
        );
        $this->assertStringContainsString('fetch(shareEndpoint', $html);
    }

    public function test_penggantian_non_share_masih_berjalan(): void
    {
        $html = FilingModalRenderer::html();
        $base = url('/modules/cbt_ops/filing_system');

        $this->assertStringContainsString("fetch('{$base}/upload.php?action=access_options')", $html);
        $this->assertStringContainsString('fetch(`'.$base.'/permission.php?action=get&filing_id=${filingId}`)', $html);
        $this->assertStringContainsString("fetch('{$base}/inspect.php?id=' + id)", $html);

        $this->assertStringNotContainsString("fetch('upload.php", $html);
        $this->assertStringNotContainsString("fetch('permission.php", $html);
        $this->assertStringNotContainsString("fetch('inspect.php", $html);
    }
}
