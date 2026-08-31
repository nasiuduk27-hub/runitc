@extends('layouts.app')

@section('title', 'RUN-ITC | Menu Management')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between gap-4">
        <div>
            <div class="mb-1 text-sm font-semibold text-gray-400">System Management / Menu</div>
            <h1 class="text-2xl font-bold text-gray-900">Menu Management</h1>
            <p class="mt-1 text-sm text-gray-500">Kelola struktur menu sidebar.</p>
        </div>
        <button onclick="openAddModal()" class="rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white shadow-md transition hover:bg-brand-primaryHover">
            <i class="fa-solid fa-plus mr-2"></i>Tambah Menu
        </button>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b p-5">
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
                <div class="relative max-w-md flex-1">
                    <i class="fa-solid fa-search absolute left-3 top-1/2 -translate-y-1/2 text-sm text-gray-400"></i>
                    <input type="text" id="menuSearchInput" placeholder="Cari menu..." oninput="filterMenuTree()" class="w-full rounded-xl border border-gray-300 py-2.5 pl-9 pr-4 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                </div>
                <div id="menuOrderStatus" class="text-xs font-semibold text-gray-400">Geser ikon grip untuk mengatur posisi menu.</div>
            </div>
        </div>

        <div class="space-y-3 p-5">
            @if (! empty($menuTree))
                <ol id="menuTreeRoot" class="menu-sortable space-y-2" data-parent-id="0">
                    @foreach ($menuTree as $menu)
                        @include('admin.system-access.partials.management-menu-item', ['menu' => $menu, 'adminMenuIds' => $adminMenuIds, 'level' => 0])
                    @endforeach
                </ol>
            @else
                <p class="py-8 text-center text-gray-400">Belum ada menu.</p>
            @endif
        </div>
    </div>
</div>

@include('admin.system-access.partials.menu-management-form', ['mode' => 'add', 'parentOptions' => $parentOptions, 'defaultSections' => $defaultSections, 'sectionPositions' => $sectionPositions, 'menuPositions' => $menuPositions])
@include('admin.system-access.partials.menu-management-form', ['mode' => 'edit', 'parentOptions' => $parentOptions, 'defaultSections' => $defaultSections, 'sectionPositions' => $sectionPositions, 'menuPositions' => $menuPositions])

<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
const menuReorderConfig = {
    url: @json(route('admin.menu-management.reorder')),
    token: @json(csrf_token()),
};

function openAddModal() {
    openModal('addMenuModal');
}

function openEditModal(menu) {
    document.getElementById('edit_rec_id').value = menu.rec_id;
    document.getElementById('edit_title').value = menu.title || '';
    document.getElementById('edit_url').value = menu.url || '';
    document.getElementById('edit_icon').value = menu.icon || '';
    document.getElementById('edit_section_key').value = menu.section_key || 'main';
    document.getElementById('edit_section_label').value = menu.section_label || 'Main';
    document.getElementById('edit_section_sort').value = menu.section_sort || 10;
    setSectionPosition('edit', parseInt(menu.section_sort || 10));
    setSectionChoice('edit', menu.section_key || 'main');
    document.getElementById('edit_mst_id').value = menu.mst_id || 0;
    document.getElementById('edit_is_global').checked = parseInt(menu.is_global) === 1;
    document.getElementById('edit_is_active').checked = parseInt(menu.is_active) !== 0;
    document.getElementById('edit_sort_order').value = menu.sort_order || 50;
    setMenuPosition('edit', parseInt(menu.sort_order || 50));
    openModal('editMenuModal');
}

function openModal(id) {
    const modal = document.getElementById(id);
    modal.classList.remove('hidden');
    modal.classList.add('flex');
}

function closeModal(id) {
    const modal = document.getElementById(id);
    modal.classList.add('hidden');
    modal.classList.remove('flex');
}

function filterMenuTree() {
    const keyword = document.getElementById('menuSearchInput').value.toLowerCase().trim();
    document.querySelectorAll('.menu-item').forEach(item => item.style.display = '');
    if (!keyword) return;

    Array.from(document.querySelectorAll('#menuTreeRoot > .menu-item')).forEach(item => filterMenuItem(item, keyword));
}

function filterMenuItem(item, keyword) {
    const ownMatch = (item.dataset.name || '').includes(keyword);
    let childMatch = false;
    item.querySelectorAll(':scope > .menu-sortable > .menu-item').forEach(child => {
        childMatch = filterMenuItem(child, keyword) || childMatch;
    });

    const visible = ownMatch || childMatch;
    item.style.display = visible ? '' : 'none';

    return visible;
}

function setSectionChoice(prefix, key) {
    const select = document.getElementById(prefix + '_section_choice');
    if (!select) return;

    const hasDefault = Array.from(select.options).some(option => option.value === key);
    select.value = hasDefault ? key : 'custom';
}

function applySectionPreset(prefix) {
    const select = document.getElementById(prefix + '_section_choice');
    const option = select.options[select.selectedIndex];
    if (select.value !== 'custom') {
        document.getElementById(prefix + '_section_key').value = option.dataset.key || select.value;
        document.getElementById(prefix + '_section_label').value = option.dataset.label || option.textContent;
        document.getElementById(prefix + '_section_sort').value = option.dataset.sort || 10;
        setSectionPosition(prefix, parseInt(option.dataset.sort || 10));
    } else {
        document.getElementById(prefix + '_section_sort').value = document.getElementById(prefix + '_section_position').selectedOptions[0]?.dataset.sort || 10;
    }
}

function setSectionPosition(prefix, sort) {
    const select = document.getElementById(prefix + '_section_position');
    if (!select) return;

    let selected = 'after_main';
    Array.from(select.options).forEach(option => {
        if (parseInt(option.dataset.sort || 0) === sort) selected = option.value;
    });
    select.value = selected;
    document.getElementById(prefix + '_section_sort').value = sort;
}

function applySectionPosition(prefix) {
    const select = document.getElementById(prefix + '_section_position');
    if (!select) return;

    document.getElementById(prefix + '_section_sort').value = select.selectedOptions[0]?.dataset.sort || 10;
}

function setMenuPosition(prefix, sort) {
    const select = document.getElementById(prefix + '_menu_position');
    if (!select) return;

    let selected = 'normal';
    Array.from(select.options).forEach(option => {
        if (parseInt(option.dataset.sort || 0) === sort) selected = option.value;
    });
    select.value = selected;
    document.getElementById(prefix + '_sort_order').value = sort;
}

function applyMenuPosition(prefix) {
    const select = document.getElementById(prefix + '_menu_position');
    if (!select) return;

    document.getElementById(prefix + '_sort_order').value = select.selectedOptions[0]?.dataset.sort || 50;
}

document.addEventListener('DOMContentLoaded', () => {
    applySectionPreset('add');
    setSectionPosition('add', 15);
    setMenuPosition('add', 50);
    initializeMenuSorting();
});

function initializeMenuSorting() {
    if (typeof Sortable === 'undefined') return;

    document.querySelectorAll('.menu-sortable').forEach(container => {
        Sortable.create(container, {
            group: 'menu-management',
            handle: '.drag-handle',
            animation: 150,
            fallbackOnBody: true,
            swapThreshold: 0.65,
            emptyInsertThreshold: 12,
            onEnd: saveMenuOrder,
        });
    });
}

function collectMenuOrder() {
    const items = [];
    document.querySelectorAll('.menu-sortable').forEach(container => {
        const parentId = parseInt(container.dataset.parentId || 0);
        Array.from(container.children).forEach((item, index) => {
            if (!item.classList.contains('menu-item')) return;

            items.push({
                rec_id: parseInt(item.dataset.menuId),
                mst_id: parentId,
                sort_order: index + 1,
            });
        });
    });

    return items.filter(item => item.rec_id > 0);
}

function setMenuOrderStatus(message, className) {
    const status = document.getElementById('menuOrderStatus');
    if (!status) return;

    status.textContent = message;
    status.className = 'text-xs font-semibold ' + className;
}

function saveMenuOrder() {
    setMenuOrderStatus('Menyimpan urutan menu...', 'text-blue-600');

    fetch(menuReorderConfig.url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': menuReorderConfig.token,
        },
        body: JSON.stringify({ items: collectMenuOrder() }),
    })
        .then(response => response.json().then(payload => ({ ok: response.ok, payload })))
        .then(({ ok, payload }) => {
            if (!ok || !payload.success) {
                throw new Error(payload.message || 'Gagal menyimpan urutan menu.');
            }

            setMenuOrderStatus(payload.message || 'Urutan menu berhasil disimpan.', 'text-green-600');
        })
        .catch(error => {
            setMenuOrderStatus(error.message || 'Gagal menyimpan urutan menu.', 'text-red-600');
            setTimeout(() => window.location.reload(), 1200);
        });
}
</script>
@endsection
