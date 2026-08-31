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
    $folderTitles = [
        'my_drive' => 'My Drive',
        'shared' => 'Share With Me',
        'department' => 'Divisi / Departemen',
        'company' => 'Company / Staff',
        'trash' => 'Sampah',
    ];
    $pageTitle = $folderTitles[$currentFolder] ?? 'My Drive';
    $isTrash = $currentFolder === 'trash';
    $queryFor = fn (array $overrides = []) => array_merge(request()->query(), $overrides);
@endphp

<div id="fileListContainer" class="flex-1 overflow-hidden">
    <div class="filing-scroll h-full overflow-auto">
        <table class="w-full min-w-[860px] text-left text-sm">
            <thead class="sticky top-0 z-10 border-b border-gray-200 bg-white">
                <tr>
                    <th colspan="6" class="border-b border-gray-200 px-4 py-3">
                        <div class="flex items-center gap-2">
                            <i class="fas fa-folder text-blue-500"></i>
                            <span class="text-sm font-bold text-gray-800">{{ $pageTitle }}</span>
                            <span class="text-xs text-gray-400">· {{ number_format((int) ($pagination['total_items'] ?? 0)) }} file</span>
                        </div>
                    </th>
                </tr>
                <tr class="text-xs text-gray-500">
                    <th class="w-12 px-4 py-3 text-center font-medium">
                        <input type="checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)" class="h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                    </th>
                    <th class="px-4 py-3 font-medium">Nama</th>
                    <th class="px-4 py-3 font-medium">Pemilik</th>
                    <th class="px-4 py-3 font-medium">Diubah</th>
                    <th class="px-4 py-3 font-medium">Ukuran</th>
                    <th class="w-24 px-4 py-3 text-right font-medium">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
                @forelse ($items as $file)
                    @php
                        $id = (int) ($file['rec_id'] ?? 0);
                        $security = $file['security_level'] ?? 'normal';
                        $permissions = $file['permissions'] ?? [];
                        $canManage = ! empty($permissions['can_manage']);
                        $canShare = ! empty($permissions['can_share']);
                        $canDownload = ! empty($permissions['can_download']);
                        $ext = strtoupper($file['file_type'] ?? '');
                        $previewableExt = in_array($ext, ['PDF', 'JPG', 'JPEG', 'PNG', 'GIF', 'WEBP'], true);
                        $sharedToCodes = $file['shared_to_codes'] ?? [];
                        $sharedDivisi = $sharedToCodes['divisi'] ?? [];
                        $sharedCompany = $sharedToCodes['company'] ?? [];
                        $typeIcon = match ($ext) {
                            'PDF' => ['fa-file-pdf', 'text-red-500'],
                            'DOC', 'DOCX' => ['fa-file-word', 'text-blue-500'],
                            'XLS', 'XLSX', 'CSV' => ['fa-file-excel', 'text-green-600'],
                            'PPT', 'PPTX' => ['fa-file-powerpoint', 'text-orange-500'],
                            'ZIP', 'RAR', '7Z' => ['fa-file-archive', 'text-amber-500'],
                            'JPG', 'JPEG', 'PNG', 'GIF', 'WEBP', 'IMG' => ['fa-file-image', 'text-purple-500'],
                            default => ['fa-file', 'text-gray-400'],
                        };
                    @endphp
                    <tr class="group hover:bg-blue-50/40">
                        <td class="px-4 py-3 text-center">
                            <input type="checkbox" class="row-checkbox h-4 w-4 rounded border-gray-300 text-blue-600 focus:ring-blue-500" value="{{ $id }}" onchange="updateBulkUI()">
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-3">
                                <i class="fas {{ $typeIcon[0] }} {{ $typeIcon[1] }} text-xl"></i>
                                <div class="min-w-0">
                                    <p class="truncate font-medium text-gray-800 {{ $isTrash ? 'line-through text-gray-400' : '' }}" title="{{ $file['display_name'] ?? '-' }}">{{ $file['display_name'] ?? '-' }}</p>
                                    <p class="mt-0.5 text-xs text-gray-400">{{ $file['file_code'] ?? '-' }}</p>
                                    @if ($sharedDivisi || $sharedCompany)
                                        <div class="mt-1 flex flex-wrap gap-1">
                                            @foreach ($sharedDivisi as $code)
                                                <span class="inline-flex items-center gap-1 rounded bg-indigo-50 px-1.5 py-0.5 text-[9px] font-bold text-indigo-600" title="Dibagikan ke Divisi"><i class="fas fa-building text-[8px]"></i>{{ $code }}</span>
                                            @endforeach
                                            @foreach ($sharedCompany as $code)
                                                <span class="inline-flex items-center gap-1 rounded bg-purple-50 px-1.5 py-0.5 text-[9px] font-bold text-purple-600" title="Dibagikan ke Company"><i class="fas fa-building-columns text-[8px]"></i>{{ $code }}</span>
                                            @endforeach
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-gray-600">{{ $file['owner_name'] ?? '-' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ ! empty($file['created_at']) ? \Carbon\Carbon::parse($file['created_at'])->format('d M Y') : '-' }}</td>
                        <td class="px-4 py-3 text-gray-500">{{ $formatSize($file['zip_size'] ?? 0) }}</td>
                        <td class="px-4 py-3">
                            <div class="flex items-center justify-end gap-1 opacity-0 transition-opacity group-hover:opacity-100">
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
                                        @if ($canManage && $currentFolder === 'my_drive' && ! $isTrash)
                                            <button type="button" onclick="executeFilingAction({{ $id }}, 'archive')" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                                                <i class="fas fa-archive w-4 text-gray-400"></i> Arsipkan
                                            </button>
                                            <button type="button" onclick="executeFilingAction({{ $id }}, 'move_trash')" class="flex w-full items-center gap-3 px-4 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                                                <i class="fas fa-trash w-4 text-red-400"></i> Pindah ke Sampah
                                            </button>
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
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-20 text-center">
                            <div class="mx-auto flex max-w-sm flex-col items-center">
                                <div class="mb-4 flex h-20 w-20 items-center justify-center rounded-full bg-gray-50 text-gray-300">
                                    <i class="fas fa-folder-open text-3xl"></i>
                                </div>
                                <h3 class="font-bold text-gray-800">Tidak ada file</h3>
                                <p class="mt-1 text-sm text-gray-400">Folder ini kosong.</p>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if (($pagination['total_pages'] ?? 1) > 1)
        <div class="flex flex-col gap-3 border-t border-gray-100 px-4 py-3 text-xs font-semibold text-gray-500 sm:flex-row sm:items-center sm:justify-between">
            <span>Halaman {{ $pagination['current_page'] ?? 1 }} dari {{ $pagination['total_pages'] ?? 1 }}</span>
            <div class="flex gap-2">
                @if (($pagination['current_page'] ?? 1) > 1)
                    <a href="{{ route('filing-system.index', $queryFor(['page' => ($pagination['current_page'] ?? 1) - 1])) }}" class="rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50">Sebelumnya</a>
                @endif
                @if (($pagination['current_page'] ?? 1) < ($pagination['total_pages'] ?? 1))
                    <a href="{{ route('filing-system.index', $queryFor(['page' => ($pagination['current_page'] ?? 1) + 1])) }}" class="rounded-lg border border-gray-200 px-3 py-2 hover:bg-gray-50">Berikutnya</a>
                @endif
            </div>
        </div>
    @endif
</div>
