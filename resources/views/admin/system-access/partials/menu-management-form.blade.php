@php
    $isEdit = $mode === 'edit';
    $modalId = $isEdit ? 'editMenuModal' : 'addMenuModal';
    $action = $isEdit ? route('admin.menu-management.update') : route('admin.menu-management.store');
@endphp

<div id="{{ $modalId }}" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/40 p-4" onclick="if(event.target===this)closeModal('{{ $modalId }}')">
    <div class="max-h-[90vh] w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl">
        <div class="sticky top-0 z-10 flex items-center justify-between border-b border-gray-100 bg-white p-6">
            <h2 class="text-lg font-bold text-gray-900">{{ $isEdit ? 'Edit Menu' : 'Tambah Menu' }}</h2>
            <button type="button" onclick="closeModal('{{ $modalId }}')" class="flex h-9 w-9 items-center justify-center rounded-full bg-gray-100 text-xl leading-none text-gray-500 hover:bg-gray-200 hover:text-gray-700">&times;</button>
        </div>
        <form method="POST" action="{{ $action }}" class="max-h-[calc(90vh-88px)] space-y-4 overflow-y-auto p-6">
            @csrf
            @if ($isEdit)<input type="hidden" name="rec_id" id="edit_rec_id">@endif
            <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Nama Menu <span class="text-red-500">*</span></label>
                <input type="text" name="title" id="{{ $isEdit ? 'edit_title' : 'add_title' }}" required class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">URL</label>
                <input type="text" name="url" id="{{ $isEdit ? 'edit_url' : 'add_url' }}" placeholder="#" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Icon</label>
                <input type="text" name="icon" id="{{ $isEdit ? 'edit_icon' : 'add_icon' }}" placeholder="fa-solid fa-link" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Section</label>
                <select name="section_choice" id="{{ $isEdit ? 'edit_section_choice' : 'add_section_choice' }}" onchange="applySectionPreset('{{ $isEdit ? 'edit' : 'add' }}')" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                    @foreach (($defaultSections ?? []) as $section)
                        <option value="{{ $section['key'] }}" data-key="{{ $section['key'] }}" data-label="{{ $section['label'] }}" data-sort="{{ $section['sort'] }}">{{ $section['label'] }}</option>
                    @endforeach
                    <option value="custom">Custom...</option>
                </select>
            </div>
            <div id="{{ $isEdit ? 'edit_section_fields' : 'add_section_fields' }}" class="grid grid-cols-1 gap-3 rounded-xl border border-gray-200 bg-gray-50 p-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Section Key</label>
                    <input type="text" name="section_key" id="{{ $isEdit ? 'edit_section_key' : 'add_section_key' }}" value="main" placeholder="main" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Section Label</label>
                    <input type="text" name="section_label" id="{{ $isEdit ? 'edit_section_label' : 'add_section_label' }}" value="Main" placeholder="Main" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-semibold text-gray-700">Section Position</label>
                    <select name="section_position" id="{{ $isEdit ? 'edit_section_position' : 'add_section_position' }}" onchange="applySectionPosition('{{ $isEdit ? 'edit' : 'add' }}')" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                        @foreach (($sectionPositions ?? []) as $key => $position)
                            <option value="{{ $key }}" data-sort="{{ $position['sort'] }}">{{ $position['label'] }}</option>
                        @endforeach
                    </select>
                    <input type="hidden" name="section_sort" id="{{ $isEdit ? 'edit_section_sort' : 'add_section_sort' }}" value="10">
                </div>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Parent Menu</label>
                <select name="mst_id" id="{{ $isEdit ? 'edit_mst_id' : 'add_mst_id' }}" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                    <option value="0">-- Root (menu utama) --</option>
                    @foreach ($parentOptions as $parent)
                        <option value="{{ $parent['rec_id'] }}">{{ $parent['title'] }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-center gap-4">
                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_global" value="0">
                    <input type="checkbox" name="is_global" id="{{ $isEdit ? 'edit_is_global' : 'add_is_global' }}" value="1" class="h-4 w-4 rounded border-gray-300 text-brand-primary focus:ring-brand-primary">
                    <span class="text-sm font-medium text-gray-700">Global</span>
                </label>
                <label class="flex items-center gap-2">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" id="{{ $isEdit ? 'edit_is_active' : 'add_is_active' }}" value="1" {{ $isEdit ? '' : 'checked' }} class="h-4 w-4 rounded border-gray-300 text-brand-primary focus:ring-brand-primary">
                    <span class="text-sm font-medium text-gray-700">Aktif</span>
                </label>
            </div>
            <div>
                <label class="mb-1 block text-sm font-semibold text-gray-700">Menu Position</label>
                <select name="menu_position" id="{{ $isEdit ? 'edit_menu_position' : 'add_menu_position' }}" onchange="applyMenuPosition('{{ $isEdit ? 'edit' : 'add' }}')" class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200">
                    @foreach (($menuPositions ?? []) as $key => $position)
                        <option value="{{ $key }}" data-sort="{{ $position['sort'] }}" @selected($key === 'normal')>{{ $position['label'] }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="sort_order" id="{{ $isEdit ? 'edit_sort_order' : 'add_sort_order' }}" value="50">
            </div>
            <div class="flex justify-end gap-3 pt-2">
                <button type="button" onclick="closeModal('{{ $modalId }}')" class="rounded-lg bg-gray-100 px-5 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-200">Batal</button>
                <button type="submit" class="rounded-lg bg-brand-primary px-5 py-2 text-sm font-semibold text-white shadow-md transition hover:bg-brand-primaryHover">{{ $isEdit ? 'Simpan Perubahan' : 'Simpan' }}</button>
            </div>
        </form>
    </div>
</div>
