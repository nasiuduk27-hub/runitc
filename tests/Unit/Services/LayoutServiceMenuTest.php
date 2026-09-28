<?php

namespace Tests\Unit\Services;

use App\Services\LayoutService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class LayoutServiceMenuTest extends TestCase
{
    public function test_flatten_group_children_promotes_grandchildren(): void
    {
        $service = new LayoutService;
        $method = new ReflectionMethod($service, 'flattenGroupChildren');
        $method->setAccessible(true);

        $children = [
            ['title' => 'Dashboard', 'url' => 'http://x/dashboard', 'children' => []],
            [
                'title' => 'Keanggotaan',
                'url' => '#',
                'children' => [
                    ['title' => 'Anggota', 'url' => 'http://x/members', 'children' => []],
                    ['title' => 'Simpanan', 'url' => 'http://x/savings', 'children' => []],
                ],
            ],
            ['title' => 'Pengaturan', 'url' => 'http://x/settings', 'children' => []],
        ];

        $flat = $method->invoke($service, $children);

        $this->assertSame(['Dashboard', 'Anggota', 'Simpanan', 'Pengaturan'], array_column($flat, 'title'));
        $this->assertSame('http://x/settings', $flat[3]['url']);
    }
}
