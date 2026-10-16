{{-- File: resources/views/filing-system/_list.blade.php --}}
@php
    $currentFolder = $folder ?? ($filters['folder'] ?? 'my_drive');
    $formatSize = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2).' GB';
        if ($bytes >= 1048576) return number_format($bytes / 1048576, 2).' MB';
        if ($bytes >= 1024) return number_format($bytes / 1024, 2).' KB';
        return $bytes.' bytes';
    };
    $groupId = $groupId ?? null;
    $pageTitle = $pageTitleOverride ?? \App\Support\FilingSystem\FilingFolders::title($currentFolder);
    $isTrash = $currentFolder === 'trash';
    $viewMode = $viewMode ?? 'list';
    $canRemoveFromGroup = fn ($file) => $currentFolder === 'group'
        && $groupId !== null
        && (($canManage ?? false) || (int) ($file['uploaded_by'] ?? 0) === (int) auth_user_id());
    $queryFor = fn (array $overrides = []) => array_merge(request()->query(), $overrides);

    $formatStyles = function (string $ext) {
        return match ($ext) {
            'PDF' => ['label' => 'PDF', 'class' => 'bg-red-50 text-red-600'],
            'XLS', 'XLSX', 'CSV' => ['label' => 'XLS', 'class' => 'bg-green-50 text-green-600'],
            'JPG', 'JPEG', 'PNG', 'GIF', 'WEBP', 'IMG' => ['label' => in_array($ext, ['JPG', 'JPEG', 'PNG', 'GIF', 'WEBP'], true) ? $ext : 'IMG', 'class' => 'bg-orange-50 text-orange-600'],
            'DOC', 'DOCX', 'PPT', 'PPTX', 'TXT' => ['label' => in_array($ext, ['PPT', 'PPTX'], true) ? 'PPT' : (in_array($ext, ['DOC', 'DOCX'], true) ? 'DOC' : 'TXT'), 'class' => 'bg-blue-50 text-blue-600'],
            'ZIP', 'RAR', '7Z', 'TAR', 'GZ' => ['label' => 'ZIP', 'class' => 'bg-amber-50 text-amber-600'],
            default => ['label' => $ext !== '' ? mb_substr($ext, 0, 4) : 'FILE', 'class' => 'bg-gray-100 text-gray-500'],
        };
    };

    $getInitials = function (?string $name): string {
        $name = trim((string) $name);
        if ($name === '') return '?';
        $words = preg_split('/\s+/', $name);
        if (count($words) >= 2) {
            return strtoupper(mb_substr($words[0], 0, 1).mb_substr($words[1], 0, 1));
        }
        return strtoupper(mb_substr($name, 0, min(2, mb_strlen($name))));
    };

    $getAvatarBg = function (?string $name): string {
        $colors = ['bg-slate-700', 'bg-indigo-700', 'bg-purple-700', 'bg-blue-700', 'bg-teal-700', 'bg-gray-700'];
        $clean = trim((string) $name);
        if ($clean === '') return 'bg-gray-500';
        return $colors[abs(crc32($clean)) % count($colors)];
    };

    $getShareInfo = function (array $file): array {
        $sharedToCodes = $file['shared_to_codes'] ?? [];
        $sharedDivisi = $sharedToCodes['divisi'] ?? [];
        $sharedCompany = $sharedToCodes['company'] ?? [];
        $sharedGroups = $file['shared_to_groups'] ?? [];

        $parts = [];
        if (! empty($sharedDivisi)) {
            $parts[] = 'Divisi: '.implode(', ', $sharedDivisi);
        }
        if (! empty($sharedCompany)) {
            $parts[] = 'Company: '.implode(', ', $sharedCompany);
        }
        if (! empty($sharedGroups)) {
            $names = array_column($sharedGroups, 'label');
            $parts[] = 'Grup: '.implode(', ', $names);
        }

        return [
            'is_shared' => ! empty($parts),
            'summary' => implode(' | ', $parts),
        ];
    };
@endphp

<div id="fileListContainer" class="flex-1 overflow-hidden">
    <div class="filing-scroll h-full overflow-auto">
        <div class="sticky top-0 z-20 flex h-12 items-center justify-between border-b border-gray-200 bg-white px-4 sm:px-6">
            <div class="flex items-baseline gap-2.5 min-w-0">
                <h1 class="truncate text-base sm:text-lg font-bold text-gray-900">{{ $pageTitle }}</h1>
                <span class="shrink-0 text-xs sm:text-sm text-gray-400 font-normal">{{ number_format((int) ($pagination['total_items'] ?? 0)) }} file</span>
            </div>
            <div class="ml-auto flex items-center rounded-lg border border-gray-200 bg-white p-0.5 shadow-sm">
                <button type="button" onclick="switchFilingView('list')" class="flex h-7 w-8 items-center justify-center rounded-md text-xs transition {{ $viewMode === 'list' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-gray-500 hover:text-gray-900' }}" title="Tampilan Daftar">
                    <i class="fas fa-list"></i>
                </button>
                <button type="button" onclick="switchFilingView('grid')" class="flex h-7 w-8 items-center justify-center rounded-md text-xs transition {{ $viewMode === 'grid' ? 'bg-blue-600 text-white font-semibold shadow-sm' : 'text-gray-500 hover:text-gray-900' }}" title="Tampilan Petak">
                    <i class="fas fa-th-large"></i>
                </button>
            </div>
        </div>

        @if ($viewMode === 'grid')
            <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-2 bg-white">
                <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                <label for="selectAllCheckbox" class="text-xs font-medium text-gray-500">Pilih semua</label>
            </div>
            <div class="grid grid-cols-2 gap-3.5 p-4 sm:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5">
                @forelse ($items as $file)
                    @php
                        $id = (int) ($file['rec_id'] ?? 0);
                        $isLink = ($file['source_type'] ?? '') === 'link';
                        $linkId = (int) ($file['link_id'] ?? 0);
                        $security = $file['security_level'] ?? 'normal';
                        $permissions = $file['permissions'] ?? [];
                        $canManage = ! empty($permissions['can_manage']);
                        $canShare = ! empty($permissions['can_share']);
                        $canDownload = ! empty($permissions['can_download']);
                        $ext = strtoupper($file['file_type'] ?? '');
                        $previewableExt = in_array($ext, ['PDF', 'JPG', 'JPEG', 'PNG', 'GIF', 'WEBP'], true);
                        $officeExt = in_array($ext, ['DOC', 'DOCX', 'XLS', 'XLSX', 'PPT', 'PPTX'], true);
                        $showThumb = ! $isTrash && ! $isLink && $canDownload && ($previewableExt || $officeExt);
                        $fmtStyle = $formatStyles($ext);
                        $shareInfo = $getShareInfo($file);

                        $gridTypeCode = trim((string) ($file['file_type_code'] ?? ''));
                        $gridTypeName = trim((string) ($file['file_type_name'] ?? ''));
                        $metaTypeOrFmt = ($gridTypeCode !== '' && $gridTypeName !== '' && $gridTypeName !== '-') ? $gridTypeName : $fmtStyle['label'];
                    @endphp
                    <div class="group relative flex flex-col rounded-xl border border-gray-200 bg-white overflow-hidden shadow-sm transition hover:border-blue-200">
                        <div class="relative flex {{ $showThumb ? 'h-36' : 'h-28' }} items-center justify-center bg-gray-50/80 border-b border-gray-100">
                            @if ($isLink)
                                <input type="checkbox" disabled class="row-checkbox absolute left-2.5 top-2.5 z-10 h-4 w-4 rounded border-gray-200 text-gray-300 cursor-not-allowed opacity-50" title="File dari tautan tidak mendukung aksi massal">
                            @else
                                <input type="checkbox" class="row-checkbox absolute left-2.5 top-2.5 z-10 h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" value="{{ $id }}" onchange="updateBulkUI()">
                            @endif
                            @if ($isLink)
                                <a href="{{ route('filing-system.link.open-internal', ['linkId' => $linkId]) }}" class="flex h-full w-full items-center justify-center" title="Buka File">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-xl font-bold text-xs {{ $fmtStyle['class'] }}">
                                        {{ $fmtStyle['label'] }}
                                    </span>
                                </a>
                            @elseif (! $isTrash && $canDownload && $previewableExt)
                                <a href="{{ url('/modules/cbt_ops/filing_system/preview.php?id='.$id) }}" target="_blank" class="flex h-full w-full items-center justify-center" title="Preview">
                                    <span class="flex h-11 w-11 items-center justify-center rounded-xl font-bold text-xs {{ $fmtStyle['class'] }}">
                                        {{ $fmtStyle['label'] }}
                                    </span>
                                    @if ($showThumb)
                                        <img src="{{ route('filing-system.thumbnail', ['id' => $id]) }}" loading="lazy" alt="{{ $file['display_name'] ?? '' }}" class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
                                    @endif
                                </a>
                            @else
                                <span class="flex h-11 w-11 items-center justify-center rounded-xl font-bold text-xs {{ $fmtStyle['class'] }}">
                                    {{ $fmtStyle['label'] }}
                                </span>
                                @if ($showThumb)
                                    <img src="{{ route('filing-system.thumbnail', ['id' => $id]) }}" loading="lazy" alt="{{ $file['display_name'] ?? '' }}" class="absolute inset-0 h-full w-full object-cover" onerror="this.remove()">
                                @endif
                            @endif
                            <details class="dropdown-container absolute right-1.5 top-1.5 z-10 text-left">
                                <summary class="flex h-7 w-7 cursor-pointer list-none items-center justify-center rounded-full text-gray-400 hover:bg-white/80 hover:text-gray-700 [&::-webkit-details-marker]:hidden">
                                    <i class="fas fa-ellipsis-v text-xs"></i>
                                </summary>
                                <div class="absolute right-0 top-full z-40 mt-1 w-52 divide-y divide-gray-100 rounded-xl bg-white py-1 text-left shadow-lg ring-1 ring-black/5">
                                    @if ($isLink)
                                        <a href="{{ route('filing-system.link.open-internal', ['linkId' => $linkId]) }}" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            <i class="fas fa-external-link-alt w-4 text-blue-500"></i> Buka File
                                        </a>
                                    @else
                                        <button type="button" onclick="openInfoDrawer({{ $id }})" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                            <i class="fas fa-info-circle w-4 text-blue-400"></i> Informasi File
                                        </button>
                                        @if ($canManage && ! $isTrash)
                                            <button type="button" onclick="openPermissionModal({{ $id }})" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                <i class="fas fa-user-shield w-4 text-yellow-500"></i> Atur Hak Akses
                                            </button>
                                            <button type="button" onclick="openEditModal({{ $id }})" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                <i class="fas fa-edit w-4 text-indigo-400"></i> Edit Metadata
                                            </button>
                                        @endif
                                        @if ($canManage && in_array($currentFolder, ['my_drive', 'group', 'archive'], true) && ! $isTrash)
                                            @if ($currentFolder === 'my_drive' && ($file['status'] ?? 'active') !== 'archived')
                                                <button type="button" onclick="executeFilingAction({{ $id }}, 'archive')" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                    <i class="fas fa-archive w-4 text-gray-400"></i> Arsipkan
                                                </button>
                                            @endif
                                            @if (in_array($currentFolder, ['my_drive', 'archive'], true) && ($file['status'] ?? 'active') === 'archived')
                                                <button type="button" onclick="executeFilingAction({{ $id }}, 'restore_archive')" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                    <i class="fas fa-box-open w-4 text-amber-500"></i> Buka Arsip
                                                </button>
                                            @endif
                                            <button type="button" onclick="executeFilingAction({{ $id }}, 'move_trash')" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                                <i class="fas fa-trash w-4 text-red-400"></i> Pindah ke Sampah
                                            </button>
                                        @endif
                                        @if ($canRemoveFromGroup($file))
                                            <form method="POST" action="{{ route('filing-system.groups.files.remove', $groupId) }}" data-confirm="Keluarkan file ini dari grup?">
                                                @csrf
                                                <input type="hidden" name="filing_id" value="{{ $id }}">
                                                <button type="submit" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                                    <i class="fas fa-folder-minus w-4 text-red-400"></i> Keluarkan dari Grup
                                                </button>
                                            </form>
                                        @endif
                                        @if ($isTrash && $canManage)
                                            <button type="button" onclick="executeFilingAction({{ $id }}, 'restore')" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-green-600 hover:bg-green-50">
                                                <i class="fas fa-trash-restore w-4 text-green-500"></i> Restore
                                            </button>
                                            <button type="button" onclick="executeFilingAction({{ $id }}, 'permanent_delete')" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-50">
                                                <i class="fas fa-times-circle w-4 text-red-500"></i> Hapus Permanen
                                            </button>
                                        @endif
                                    @endif
                                </div>
                            </details>
                        </div>
                        <div class="flex flex-1 flex-col p-3">
                            <div class="flex items-start justify-between gap-1">
                                <p class="line-clamp-2 text-sm font-semibold text-gray-900 leading-snug {{ $isTrash ? 'line-through text-gray-400' : '' }}" title="{{ $file['display_name'] ?? '-' }}">
                                    {{ $file['display_name'] ?? '-' }}
                                </p>
                                @if (($file['status'] ?? 'active') === 'archived')
                                    <span class="inline-flex shrink-0 items-center gap-0.5 rounded bg-amber-50 px-1 py-0.5 text-[9px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20" title="File diarsipkan">
                                        <i class="fas fa-archive text-[8px]"></i>
                                    </span>
                                @endif
                            </div>
                            <div class="mt-1.5 flex items-center gap-1.5 text-xs text-gray-400 truncate">
                                @if ($shareInfo['is_shared'])
                                    <i class="fas fa-user-group text-[11px] shrink-0 text-gray-400" title="{{ $shareInfo['summary'] }}"></i>
                                @endif
                                <span class="truncate">{{ $metaTypeOrFmt }}</span>
                                <span class="shrink-0 text-gray-300">·</span>
                                <span class="shrink-0">{{ $formatSize($file['zip_size'] ?? 0) }}</span>
                                <span class="shrink-0 text-gray-300">·</span>
                                <span class="shrink-0">{{ ! empty($file['created_at']) ? \Carbon\Carbon::parse($file['created_at'])->format('d M') : '-' }}</span>
                            </div>
                        </div>
                        @if ($isLink)
                            <div class="mt-auto flex items-center justify-end gap-1 border-t border-gray-100 bg-gray-50/50 px-2 py-1.5">
                                <a href="{{ route('filing-system.link.open-internal', ['linkId' => $linkId]) }}" class="inline-flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-semibold text-blue-600 hover:bg-white" title="Buka File">
                                    <i class="fas fa-external-link-alt text-[10px]"></i> Buka
                                </a>
                            </div>
                        @elseif (! $isTrash && ($canShare || $canDownload))
                            <div class="mt-auto flex items-center justify-end gap-1 border-t border-gray-100 bg-gray-50/50 px-2 py-1.5">
                                @if ($canShare)
                                    <button type="button" onclick="openShareModal({{ $id }}, {{ $security === 'restricted' ? 'true' : 'false' }})" class="flex h-7 w-7 items-center justify-center rounded-md text-gray-400 hover:bg-white hover:text-green-600 text-xs" title="Bagikan">
                                        <i class="fas fa-share-alt"></i>
                                    </button>
                                @endif
                                @if ($canDownload)
                                    <a href="{{ url('/modules/cbt_ops/filing_system/download.php?id='.$id) }}" class="flex h-7 w-7 items-center justify-center rounded-md text-gray-400 hover:bg-white hover:text-blue-600 text-xs" title="Download">
                                        <i class="fas fa-download"></i>
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="col-span-full px-6 py-16 text-center">
                        <div class="mx-auto flex max-w-sm flex-col items-center">
                            <div class="mb-3 flex h-16 w-16 items-center justify-center rounded-2xl border border-gray-100 bg-gray-50 text-gray-300">
                                <i class="fas fa-folder-open text-2xl"></i>
                            </div>
                            <h3 class="text-sm font-semibold text-gray-700">Tidak ada file</h3>
                            <p class="mt-0.5 text-xs text-gray-400">Folder ini kosong.</p>
                        </div>
                    </div>
                @endforelse
            </div>
        @else
            <div class="p-4">
                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[860px] text-left text-sm">
                            <thead class="border-b border-gray-200 bg-[#fcfcfd]">
                                <tr class="text-[11px] font-bold uppercase tracking-wider text-gray-400">
                                    <th class="w-12 px-4 py-3 text-center">
                                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                                    </th>
                                    <th class="px-4 py-3">Nama</th>
                                    <th class="px-4 py-3">Tipe</th>
                                    <th class="px-4 py-3">Pemilik</th>
                                    <th class="px-4 py-3">Diubah</th>
                                    <th class="px-4 py-3">Ukuran</th>
                                    <th class="w-24 px-4 py-3 text-right"></th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse ($items as $file)
                                    @php
                                        $id = (int) ($file['rec_id'] ?? 0);
                                        $isLink = ($file['source_type'] ?? '') === 'link';
                                        $linkId = (int) ($file['link_id'] ?? 0);
                                        $security = $file['security_level'] ?? 'normal';
                                        $permissions = $file['permissions'] ?? [];
                                        $canManage = ! empty($permissions['can_manage']);
                                        $canShare = ! empty($permissions['can_share']);
                                        $canDownload = ! empty($permissions['can_download']);
                                        $ext = strtoupper($file['file_type'] ?? '');
                                        $previewableExt = in_array($ext, ['PDF', 'JPG', 'JPEG', 'PNG', 'GIF', 'WEBP'], true);
                                        $fmtStyle = $formatStyles($ext);
                                        $shareInfo = $getShareInfo($file);
                                    @endphp
                                    <tr class="group transition-colors hover:bg-gray-50/70">
                                        <td class="px-4 py-3.5 text-center">
                                            @if ($isLink)
                                                <input type="checkbox" disabled class="row-checkbox h-4 w-4 rounded border-gray-200 text-gray-300 cursor-not-allowed opacity-50" title="File dari tautan tidak mendukung aksi massal">
                                            @else
                                                <input type="checkbox" class="row-checkbox h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" value="{{ $id }}" onchange="updateBulkUI()">
                                            @endif
                                        </td>
                                        <td class="px-4 py-3.5">
                                            <div class="flex items-center gap-3 min-w-0">
                                                <span class="flex h-[34px] w-[34px] shrink-0 items-center justify-center rounded-lg font-bold text-[10px] {{ $fmtStyle['class'] }}">
                                                    {{ $fmtStyle['label'] }}
                                                </span>
                                                <div class="flex min-w-0 flex-1 items-center gap-2 flex-wrap sm:flex-nowrap">
                                                    @if ($isLink)
                                                        <a href="{{ route('filing-system.link.open-internal', ['linkId' => $linkId]) }}" class="truncate font-semibold text-gray-900 hover:text-blue-600 hover:underline" title="{{ $file['display_name'] ?? '-' }}">
                                                            {{ $file['display_name'] ?? '-' }}
                                                        </a>
                                                    @else
                                                        <span class="truncate font-semibold text-gray-900 {{ $isTrash ? 'line-through text-gray-400' : '' }}" title="{{ $file['display_name'] ?? '-' }}">
                                                            {{ $file['display_name'] ?? '-' }}
                                                        </span>
                                                    @endif
                                                    <span class="shrink-0 text-xs text-gray-400 font-normal">
                                                        {{ $file['file_code'] ?? '-' }}
                                                    </span>
                                                    @if ($shareInfo['is_shared'])
                                                        <i class="fas fa-user-group text-xs text-gray-400 shrink-0" title="{{ $shareInfo['summary'] }}"></i>
                                                    @endif
                                                    @if (($file['status'] ?? 'active') === 'archived')
                                                        <span class="inline-flex shrink-0 items-center gap-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700 ring-1 ring-inset ring-amber-600/20" title="File diarsipkan">
                                                            <i class="fas fa-archive text-[8px]"></i>Arsip
                                                        </span>
                                                    @endif
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3.5 text-sm">
                                            @php
                                                $listTypeCode = trim((string) ($file['file_type_code'] ?? ''));
                                                $listTypeName = trim((string) ($file['file_type_name'] ?? ''));
                                            @endphp
                                            @if ($listTypeCode !== '' && $listTypeName !== '' && $listTypeName !== '-')
                                                <span class="text-gray-600">{{ $listTypeName }}</span>
                                            @else
                                                <span class="text-gray-300">—</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3.5 text-sm">
                                            <div class="flex items-center gap-2 min-w-0">
                                                <span class="flex h-[26px] w-[26px] shrink-0 items-center justify-center rounded-full {{ $getAvatarBg($file['owner_name'] ?? '') }} text-[10px] font-semibold text-white">
                                                    {{ $getInitials($file['owner_name'] ?? '') }}
                                                </span>
                                                <span class="truncate text-gray-600" title="{{ $file['owner_name'] ?? '-' }}">
                                                    {{ $file['owner_name'] ?? '-' }}
                                                </span>
                                            </div>
                                        </td>
                                        <td class="px-4 py-3.5 text-sm text-gray-500 whitespace-nowrap">
                                            {{ ! empty($file['created_at']) ? \Carbon\Carbon::parse($file['created_at'])->format('d M Y') : '-' }}
                                        </td>
                                        <td class="px-4 py-3.5 text-sm text-gray-500 whitespace-nowrap">
                                            {{ $formatSize($file['zip_size'] ?? 0) }}
                                        </td>
                                        <td class="w-24 px-4 py-3.5 text-right">
                                            <div class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                                @if ($isLink)
                                                    <a href="{{ route('filing-system.link.open-internal', ['linkId' => $linkId]) }}" class="inline-flex items-center gap-1.5 rounded-lg border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-blue-600 shadow-sm hover:bg-gray-50" title="Buka File">
                                                        <i class="fas fa-external-link-alt text-[10px]"></i> Buka
                                                    </a>
                                                    <details class="dropdown-container relative inline-block text-left">
                                                        <summary class="flex h-8 w-8 cursor-pointer list-none items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 [&::-webkit-details-marker]:hidden">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </summary>
                                                        <div class="absolute right-0 top-full z-40 mt-1 w-44 divide-y divide-gray-100 rounded-xl bg-white py-1 text-left shadow-lg ring-1 ring-black/5">
                                                            <a href="{{ route('filing-system.link.open-internal', ['linkId' => $linkId]) }}" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                                <i class="fas fa-external-link-alt w-4 text-blue-500"></i> Buka File
                                                            </a>
                                                        </div>
                                                    </details>
                                                @else
                                                    @if (! $isTrash && $canShare)
                                                        <button type="button" onclick="openShareModal({{ $id }}, {{ $security === 'restricted' ? 'true' : 'false' }})" class="flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-green-600" title="Bagikan">
                                                            <i class="fas fa-share-alt"></i>
                                                        </button>
                                                    @endif
                                                    @if (! $isTrash && $canDownload)
                                                        @if ($previewableExt)
                                                            <a href="{{ url('/modules/cbt_ops/filing_system/preview.php?id='.$id) }}" target="_blank" class="flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-teal-600" title="Preview">
                                                                <i class="fas fa-eye"></i>
                                                            </a>
                                                        @endif
                                                        <a href="{{ url('/modules/cbt_ops/filing_system/download.php?id='.$id) }}" class="flex h-8 w-8 items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 hover:text-blue-600" title="Download">
                                                            <i class="fas fa-download"></i>
                                                        </a>
                                                    @endif
                                                    <details class="dropdown-container relative inline-block text-left">
                                                        <summary class="flex h-8 w-8 cursor-pointer list-none items-center justify-center rounded-full text-gray-500 hover:bg-gray-100 [&::-webkit-details-marker]:hidden">
                                                            <i class="fas fa-ellipsis-v"></i>
                                                        </summary>
                                                        <div class="absolute right-0 top-full z-40 mt-1 w-52 divide-y divide-gray-100 rounded-xl bg-white py-1 text-left shadow-lg ring-1 ring-black/5">
                                                            <button type="button" onclick="openInfoDrawer({{ $id }})" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                                <i class="fas fa-info-circle w-4 text-blue-400"></i> Informasi File
                                                            </button>
                                                            @if ($canManage && ! $isTrash)
                                                                <button type="button" onclick="openPermissionModal({{ $id }})" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                                    <i class="fas fa-user-shield w-4 text-yellow-500"></i> Atur Hak Akses
                                                                </button>
                                                                <button type="button" onclick="openEditModal({{ $id }})" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                                    <i class="fas fa-edit w-4 text-indigo-400"></i> Edit Metadata
                                                                </button>
                                                            @endif
                                                            @if ($canManage && in_array($currentFolder, ['my_drive', 'group', 'archive'], true) && ! $isTrash)
                                                                @if ($currentFolder === 'my_drive' && ($file['status'] ?? 'active') !== 'archived')
                                                                    <button type="button" onclick="executeFilingAction({{ $id }}, 'archive')" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                                        <i class="fas fa-archive w-4 text-gray-400"></i> Arsipkan
                                                                    </button>
                                                                @endif
                                                                @if (in_array($currentFolder, ['my_drive', 'archive'], true) && ($file['status'] ?? 'active') === 'archived')
                                                                    <button type="button" onclick="executeFilingAction({{ $id }}, 'restore_archive')" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                                        <i class="fas fa-box-open w-4 text-amber-500"></i> Buka Arsip
                                                                    </button>
                                                                @endif
                                                                <button type="button" onclick="executeFilingAction({{ $id }}, 'move_trash')" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                                                    <i class="fas fa-trash w-4 text-red-400"></i> Pindah ke Sampah
                                                                </button>
                                                            @endif
                                                            @if ($canRemoveFromGroup($file))
                                                                <form method="POST" action="{{ route('filing-system.groups.files.remove', $groupId) }}" data-confirm="Keluarkan file ini dari grup?">
                                                                    @csrf
                                                                    <input type="hidden" name="filing_id" value="{{ $id }}">
                                                                    <button type="submit" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                                                        <i class="fas fa-folder-minus w-4 text-red-400"></i> Keluarkan dari Grup
                                                                    </button>
                                                                </form>
                                                            @endif
                                                            @if ($isTrash && $canManage)
                                                                <button type="button" onclick="executeFilingAction({{ $id }}, 'restore')" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-green-600 hover:bg-green-50">
                                                                    <i class="fas fa-trash-restore w-4 text-green-500"></i> Restore
                                                                </button>
                                                                <button type="button" onclick="executeFilingAction({{ $id }}, 'permanent_delete')" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-bold text-red-600 hover:bg-red-50">
                                                                    <i class="fas fa-times-circle w-4 text-red-500"></i> Hapus Permanen
                                                                </button>
                                                            @endif
                                                        </div>
                                                    </details>
                                                @endif
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="7" class="px-6 py-16 text-center">
                                            <div class="mx-auto flex max-w-sm flex-col items-center">
                                                <div class="mb-3 flex h-16 w-16 items-center justify-center rounded-2xl border border-gray-100 bg-gray-50 text-gray-300">
                                                    <i class="fas fa-folder-open text-2xl"></i>
                                                </div>
                                                <h3 class="text-sm font-semibold text-gray-700">Tidak ada file</h3>
                                                <p class="mt-0.5 text-xs text-gray-400">Folder ini kosong.</p>
                                            </div>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>

    @if (($pagination['total_pages'] ?? 1) > 1)
        <div class="flex flex-col gap-3 border-t border-gray-100 px-4 py-3 text-xs font-semibold text-gray-500 sm:flex-row sm:items-center sm:justify-between">
            <span>Halaman {{ $pagination['current_page'] ?? 1 }} dari {{ $pagination['total_pages'] ?? 1 }}</span>
            <div class="flex gap-2">
                @if (($pagination['current_page'] ?? 1) > 1)
                    <a href="{{ request()->fullUrlWithQuery(['page' => ($pagination['current_page'] ?? 1) - 1]) }}" class="rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50">Sebelumnya</a>
                @endif
                @if (($pagination['current_page'] ?? 1) < ($pagination['total_pages'] ?? 1))
                    <a href="{{ request()->fullUrlWithQuery(['page' => ($pagination['current_page'] ?? 1) + 1]) }}" class="rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50">Berikutnya</a>
                @endif
            </div>
        </div>
    @endif
</div>
