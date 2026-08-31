<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class MenuManagementController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless($this->isSuperadmin($request), 403);

        $menus = $this->getAllMenus();

        return view('admin.system-access.menu-management', [
            'menuTree' => $this->buildTree($menus),
            'parentOptions' => $menus,
            'adminMenuIds' => $this->getAdminMenuIds(),
            'defaultSections' => $this->defaultSections(),
            'sectionPositions' => $this->sectionPositions(),
            'menuPositions' => $this->menuPositions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $validated = $this->validateMenu($request);

        DB::connection('run')->table('sys_menus')->insert($validated);

        return back()->with('success_msg', 'Menu berhasil ditambahkan.');
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $validated = $request->validate(['rec_id' => ['required', 'integer', 'min:1']]) + $this->validateMenu($request);

        DB::connection('run')->table('sys_menus')->where('rec_id', $validated['rec_id'])->update([
            'mst_id' => $validated['mst_id'],
            'title' => $validated['title'],
            'url' => $validated['url'],
            'icon' => $validated['icon'],
            'section_key' => $validated['section_key'],
            'section_label' => $validated['section_label'],
            'section_sort' => $validated['section_sort'],
            'is_global' => $validated['is_global'],
            'is_active' => $validated['is_active'],
            'sort_order' => $validated['sort_order'],
        ]);

        return back()->with('success_msg', 'Menu berhasil diperbarui.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $validated = $request->validate(['rec_id' => ['required', 'integer', 'min:1']]);
        $menuId = (int) $validated['rec_id'];

        if (in_array($menuId, $this->getAdminMenuIds(), true)) {
            return back()->with('error_msg', 'Menu admin dilindungi dan tidak bisa dihapus.');
        }

        DB::connection('run')->transaction(function () use ($menuId): void {
            DB::connection('run')->table('sys_menu_access')->where('menu_id', $menuId)->delete();
            DB::connection('run')->table('sys_menus')->where('mst_id', $menuId)->update(['mst_id' => 0]);
            DB::connection('run')->table('sys_menus')->where('rec_id', $menuId)->delete();
        });

        return back()->with('success_msg', 'Menu berhasil dihapus.');
    }

    public function reorder(Request $request): JsonResponse
    {
        abort_unless($this->isSuperadmin($request), 403);

        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.rec_id' => ['required', 'integer', 'min:1'],
            'items.*.mst_id' => ['required', 'integer', 'min:0'],
            'items.*.sort_order' => ['required', 'integer', 'min:1'],
        ]);

        $items = collect($validated['items'])
            ->map(fn ($item) => [
                'rec_id' => (int) $item['rec_id'],
                'mst_id' => (int) $item['mst_id'],
                'sort_order' => (int) $item['sort_order'],
            ])
            ->unique('rec_id')
            ->values();

        if ($items->isEmpty()) {
            return response()->json(['success' => false, 'message' => 'Data urutan menu kosong.'], 422);
        }

        $parentMap = DB::connection('run')
            ->table('sys_menus')
            ->pluck('mst_id', 'rec_id')
            ->map(fn ($parentId) => (int) $parentId)
            ->all();

        foreach ($items as $item) {
            if (! array_key_exists($item['rec_id'], $parentMap) || ($item['mst_id'] > 0 && ! array_key_exists($item['mst_id'], $parentMap))) {
                return response()->json(['success' => false, 'message' => 'Menu atau parent menu tidak ditemukan.'], 422);
            }
        }

        foreach ($items as $item) {
            $parentMap[$item['rec_id']] = $item['mst_id'];
        }

        foreach ($items as $item) {
            if ($this->hasMenuCycle($item['rec_id'], $parentMap)) {
                return response()->json(['success' => false, 'message' => 'Struktur menu tidak valid. Menu tidak boleh menjadi child dari dirinya sendiri.'], 422);
            }
        }

        DB::connection('run')->transaction(function () use ($items): void {
            foreach ($items as $item) {
                DB::connection('run')->table('sys_menus')
                    ->where('rec_id', $item['rec_id'])
                    ->update([
                        'mst_id' => $item['mst_id'],
                        'sort_order' => $item['sort_order'],
                    ]);
            }
        });

        return response()->json(['success' => true, 'message' => 'Urutan menu berhasil disimpan.']);
    }

    public function legacyAction(Request $request): RedirectResponse
    {
        return match ($request->input('action')) {
            'add_menu' => $this->store($request),
            'update_menu' => $this->update($request),
            'delete_menu' => $this->destroy($request),
            default => back()->with('error_msg', 'Action menu tidak valid.'),
        };
    }

    private function validateMenu(Request $request): array
    {
        $validated = $request->validate([
            'mst_id' => ['nullable', 'integer', 'min:0'],
            'title' => ['required', 'string', 'max:150'],
            'url' => ['nullable', 'string', 'max:255'],
            'icon' => ['nullable', 'string', 'max:100'],
            'section_choice' => ['nullable', 'string', 'max:80'],
            'section_key' => ['nullable', 'string', 'max:80'],
            'section_label' => ['nullable', 'string', 'max:120'],
            'section_position' => ['nullable', 'string', 'max:80'],
            'section_sort' => ['nullable', 'integer', 'min:0'],
            'is_global' => ['nullable', 'integer', 'in:0,1'],
            'is_active' => ['nullable', 'integer', 'in:0,1'],
            'menu_position' => ['nullable', 'string', 'max:80'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $section = $this->resolveSection($validated);

        return [
            'mst_id' => (int) ($validated['mst_id'] ?? 0),
            'title' => trim($validated['title']),
            'url' => trim((string) ($validated['url'] ?? '#')) ?: '#',
            'icon' => trim((string) ($validated['icon'] ?? 'fa-solid fa-link')) ?: 'fa-solid fa-link',
            'section_key' => $section['key'],
            'section_label' => $section['label'],
            'section_sort' => $section['sort'],
            'is_global' => (int) ($validated['is_global'] ?? 0),
            'is_active' => (int) ($validated['is_active'] ?? 0),
            'sort_order' => $this->resolveMenuSort($validated),
        ];
    }

    private function resolveMenuSort(array $validated): int
    {
        $sort = (int) ($validated['sort_order'] ?? 0);
        if ($sort > 0) {
            return $sort;
        }

        $position = trim((string) ($validated['menu_position'] ?? 'normal'));
        $positions = $this->menuPositions();

        return $positions[$position]['sort'] ?? 50;
    }

    private function resolveSection(array $validated): array
    {
        $choice = trim((string) ($validated['section_choice'] ?? 'main')) ?: 'main';
        $defaults = $this->defaultSections();

        $key = trim((string) ($validated['section_key'] ?? ''));
        $label = trim((string) ($validated['section_label'] ?? ''));

        if (($key === '' || $label === '') && $choice !== 'custom' && isset($defaults[$choice])) {
            return $defaults[$choice];
        }

        return [
            'key' => $key ?: 'main',
            'label' => $label ?: 'Main',
            'sort' => $this->resolveSectionSort($validated),
        ];
    }

    private function resolveSectionSort(array $validated): int
    {
        $sort = (int) ($validated['section_sort'] ?? 0);
        if ($sort > 0) {
            return $sort;
        }

        $position = trim((string) ($validated['section_position'] ?? ''));
        $positions = $this->sectionPositions();
        if ($position !== '' && isset($positions[$position])) {
            return $positions[$position]['sort'];
        }

        return (int) ($validated['section_sort'] ?? 10);
    }

    private function defaultSections(): array
    {
        $sections = [
            'main' => ['key' => 'main', 'label' => 'Main', 'sort' => 10],
            'test_operation' => ['key' => 'test_operation', 'label' => 'Test Operation', 'sort' => 20],
            'management' => ['key' => 'management', 'label' => 'Management', 'sort' => 50],
            'account' => ['key' => 'account', 'label' => 'Account', 'sort' => 80],
            'administration' => ['key' => 'administration', 'label' => 'Administration', 'sort' => 90],
        ];

        try {
            $existingSections = DB::connection('run')
                ->table('sys_menus')
                ->select('section_key', 'section_label', 'section_sort')
                ->whereNotNull('section_key')
                ->where('section_key', '!=', '')
                ->orderBy('section_sort')
                ->orderBy('section_label')
                ->get();

            foreach ($existingSections as $section) {
                $key = trim((string) ($section->section_key ?? ''));
                if ($key === '' || isset($sections[$key])) {
                    continue;
                }

                $label = trim((string) ($section->section_label ?? '')) ?: ucfirst(str_replace('_', ' ', $key));
                $sections[$key] = [
                    'key' => $key,
                    'label' => $label,
                    'sort' => (int) ($section->section_sort ?? 10),
                ];
            }
        } catch (Throwable) {
            // Keep the form usable even if the legacy menu table is temporarily unavailable.
        }

        uasort($sections, fn ($a, $b) => ($a['sort'] <=> $b['sort']) ?: strcmp($a['label'], $b['label']));

        return $sections;
    }

    private function sectionPositions(): array
    {
        $positions = [
            'first' => ['label' => 'First', 'sort' => 5],
            'main' => ['label' => 'Main Position', 'sort' => 10],
            'after_main' => ['label' => 'After Main', 'sort' => 15],
            'test_operation' => ['label' => 'Test Operation Position', 'sort' => 20],
            'after_test_operation' => ['label' => 'After Test Operation', 'sort' => 30],
            'management' => ['label' => 'Management Position', 'sort' => 50],
            'after_management' => ['label' => 'After Management', 'sort' => 60],
            'account' => ['label' => 'Account Position', 'sort' => 80],
            'after_account' => ['label' => 'After Account', 'sort' => 85],
            'administration' => ['label' => 'Administration Position', 'sort' => 90],
            'after_administration' => ['label' => 'After Administration', 'sort' => 95],
            'last' => ['label' => 'Last', 'sort' => 100],
        ];

        foreach ($this->defaultSections() as $section) {
            $key = $section['key'];
            if (isset($positions[$key])) {
                continue;
            }

            $positions[$key] = [
                'label' => $section['label'].' Position',
                'sort' => $section['sort'],
            ];
        }

        uasort($positions, fn ($a, $b) => ($a['sort'] <=> $b['sort']) ?: strcmp($a['label'], $b['label']));

        return $positions;
    }

    private function menuPositions(): array
    {
        return [
            'first' => ['label' => 'First', 'sort' => 5],
            'early' => ['label' => 'Early', 'sort' => 20],
            'normal' => ['label' => 'Normal', 'sort' => 50],
            'later' => ['label' => 'Later', 'sort' => 80],
            'last' => ['label' => 'Last', 'sort' => 100],
        ];
    }

    private function getAllMenus(): array
    {
        return DB::connection('run')
            ->table('sys_menus')
            ->select('rec_id', 'mst_id', 'title', 'url', 'icon', 'section_key', 'section_label', 'section_sort', 'is_global', 'is_active', 'sort_order')
            ->orderBy('mst_id')
            ->orderBy('sort_order')
            ->orderBy('rec_id')
            ->get()
            ->map(fn ($row) => (array) $row + ['children' => []])
            ->all();
    }

    private function buildTree(array $menus, int $parentId = 0): array
    {
        $branch = [];
        foreach ($menus as $menu) {
            if ((int) $menu['mst_id'] === $parentId) {
                $menu['children'] = $this->buildTree($menus, (int) $menu['rec_id']);
                $branch[] = $menu;
            }
        }

        return $branch;
    }

    private function hasMenuCycle(int $menuId, array $parentMap): bool
    {
        $visited = [];
        $current = $menuId;

        while (! empty($parentMap[$current])) {
            $parentId = (int) $parentMap[$current];
            if ($parentId === $menuId || isset($visited[$parentId])) {
                return true;
            }

            $visited[$parentId] = true;
            $current = $parentId;
        }

        return false;
    }

    private function getAdminMenuIds(): array
    {
        return DB::connection('run')
            ->table('sys_menus')
            ->where('is_active', 1)
            ->where(function ($q): void {
                $q->where('url', 'like', '/modules/admin/%')->orWhere('url', 'like', 'modules/admin/%');
            })
            ->pluck('rec_id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function isSuperadmin(Request $request): bool
    {
        $userId = (int) $request->session()->get('user_id', 0);
        if ($userId <= 0) {
            return false;
        }

        return DB::connection('run')
            ->table('sysitc_usracc as ua')
            ->join('sysitc_grpacc as g', function ($join): void {
                $join->on('g.grpaccess', '=', 'ua.access_code')->on('g.grpacc', '=', 'ua.access_account');
            })
            ->where('ua.user_rec_id', $userId)
            ->where('g.grpaccess', '03')
            ->where('g.grpacc', '999')
            ->where('g.grpdesc', 'like', '%SUPER%ADMIN%')
            ->exists();
    }
}
