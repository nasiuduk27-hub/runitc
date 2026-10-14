<?php

namespace App\Support\FilingSystem;

final class FilingFolders
{
    public const MY_DRIVE = 'my_drive';

    public const ARCHIVE = 'archive';

    public const SHARED = 'shared';

    public const DEPARTMENT = 'department';

    public const COMPANY = 'company';

    public const TRASH = 'trash';

    /**
     * @var array<string, array{label: string, title: string, icon: string, color: string, gated: bool, drive: bool}>
     */
    private const DEFS = [
        self::MY_DRIVE => ['label' => 'My Drive', 'title' => 'My Drive', 'icon' => 'fa-folder-open', 'color' => 'text-blue-600', 'gated' => false, 'drive' => true],
        self::ARCHIVE => ['label' => 'Arsip', 'title' => 'Arsip', 'icon' => 'fa-box-archive', 'color' => 'text-amber-600', 'gated' => true, 'drive' => true],
        self::SHARED => ['label' => 'Shared', 'title' => 'Share With Me', 'icon' => 'fa-share-alt', 'color' => 'text-green-600', 'gated' => false, 'drive' => true],
        self::DEPARTMENT => ['label' => 'Divisi', 'title' => 'Divisi / Departemen', 'icon' => 'fa-building', 'color' => 'text-indigo-600', 'gated' => false, 'drive' => true],
        self::COMPANY => ['label' => 'Company', 'title' => 'Company / Staff', 'icon' => 'fa-users', 'color' => 'text-purple-600', 'gated' => false, 'drive' => true],
        self::TRASH => ['label' => 'Sampah', 'title' => 'Sampah', 'icon' => 'fa-trash', 'color' => 'text-gray-400', 'gated' => false, 'drive' => false],
    ];

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_keys(self::DEFS);
    }

    /**
     * @return list<string>
     */
    public static function allowed(bool $includeGated): array
    {
        return array_keys(array_filter(
            self::DEFS,
            static fn (array $def): bool => $includeGated || ! $def['gated']
        ));
    }

    public static function defaultKey(): string
    {
        return self::MY_DRIVE;
    }

    public static function title(string $key): string
    {
        return self::DEFS[$key]['title'] ?? self::DEFS[self::MY_DRIVE]['title'];
    }

    /**
     * @return array<string, array{label: string, title: string, icon: string, color: string, gated: bool, drive: bool}>
     */
    public static function driveDefs(): array
    {
        return array_filter(self::DEFS, static fn (array $def): bool => $def['drive']);
    }

    /**
     * @return array{label: string, title: string, icon: string, color: string, gated: bool, drive: bool}
     */
    public static function trashDef(): array
    {
        return self::DEFS[self::TRASH];
    }
}
