@extends('layouts.app')

@section('title', 'RUN-ITC | Akses Share Code')

@section('content')
<div class="py-8 md:py-12">
    <div class="mx-auto max-w-3xl px-4 sm:px-6 lg:px-8">
        <div class="mb-6 flex items-center justify-between gap-4">
            <div>
                <h2 class="flex items-center gap-3 text-3xl font-extrabold tracking-tight text-gray-900">
                    <i class="fas fa-key text-green-600"></i>
                    Akses Share Code
                </h2>
                <p class="mt-1 text-sm text-gray-500">Masukkan kode yang diberikan untuk membuka file yang dibagikan.</p>
            </div>
            <a href="{{ url('/filing-system') }}" class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-bold text-gray-700 shadow-sm transition hover:bg-gray-50">
                <i class="fas fa-arrow-left text-gray-400"></i> Kembali
            </a>
        </div>

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gradient-to-br from-green-50 to-white p-6 md:p-8">
                <form id="formShareAccess" class="space-y-5">
                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-gray-700">Share Code</label>
                        <input
                            type="text"
                            name="share_code"
                            id="shareCodeInput"
                            required
                            placeholder="RFS-XXXX-XXXX"
                            autocomplete="off"
                            class="block w-full rounded-xl border border-gray-300 bg-white p-4 font-mono text-lg uppercase tracking-widest text-gray-900 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        >
                        <p class="mt-2 text-xs text-gray-400">Format kode: <span class="font-mono">RFS-AB12-CD34</span></p>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-bold uppercase tracking-wide text-gray-700">Password Share</label>
                        <input
                            type="password"
                            name="share_password"
                            id="sharePasswordInput"
                            placeholder="Kosongkan jika kode tidak memakai password"
                            class="block w-full rounded-xl border border-gray-300 bg-white p-3 text-sm text-gray-900 shadow-sm focus:border-green-500 focus:ring-2 focus:ring-green-500"
                        >
                    </div>

                    <div class="flex justify-end">
                        <button type="submit" id="btnValidateShare" class="flex items-center gap-2 rounded-xl bg-green-600 px-6 py-3 text-sm font-bold text-white shadow-lg shadow-green-100 transition hover:bg-green-700">
                            <i class="fas fa-unlock-alt"></i> Buka File
                        </button>
                    </div>
                </form>
            </div>

            <div id="shareMessage" class="mx-6 mt-6 hidden rounded-xl p-4 text-sm font-medium md:mx-8"></div>

            <div id="shareResult" class="hidden p-6 md:p-8">
                <div class="rounded-2xl border border-green-100 bg-green-50/60 p-5">
                    <div class="flex flex-col justify-between gap-5 md:flex-row md:items-center">
                        <div class="min-w-0">
                            <div class="mb-2 flex items-center gap-2 text-xs font-bold uppercase tracking-wide text-green-700">
                                <i class="fas fa-check-circle"></i>
                                Share Code Valid
                            </div>
                            <h3 id="fileName" class="truncate text-xl font-extrabold text-gray-900"></h3>
                            <div class="mt-2 flex flex-wrap gap-3 text-xs text-gray-500">
                                <span id="fileSize"></span>
                                <span id="fileCreated"></span>
                            </div>
                        </div>

                        <a id="btnDownloadShare" href="#" class="hidden shrink-0 items-center justify-center gap-2 rounded-xl bg-blue-600 px-5 py-3 text-sm font-bold text-white shadow-lg shadow-blue-100 transition hover:bg-blue-700">
                            <i class="fas fa-download"></i> Download File
                        </a>
                    </div>

                    <p id="downloadNotAllowed" class="mt-4 hidden rounded-lg border border-amber-100 bg-amber-50 p-3 text-xs text-amber-700">
                        Share Code ini hanya mengizinkan akses informasi file. Download tidak diaktifkan oleh pembuat share.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const shareEndpoint = @json(url('/modules/cbt_ops/filing_system/share.php'));
    const downloadBase = @json(url('/modules/cbt_ops/filing_system/download.php'));

    function formatBytes(bytes) {
        const size = Number(bytes || 0);
        if (size >= 1073741824) return (size / 1073741824).toFixed(2) + ' GB';
        if (size >= 1048576) return (size / 1048576).toFixed(2) + ' MB';
        if (size >= 1024) return (size / 1024).toFixed(2) + ' KB';
        return size + ' bytes';
    }

    function showShareMessage(type, text) {
        const box = document.getElementById('shareMessage');
        box.className = 'mx-6 md:mx-8 mt-6 p-4 rounded-xl text-sm font-medium ' + (type === 'error' ? 'bg-red-50 text-red-700 border border-red-100' : 'bg-green-50 text-green-700 border border-green-100');
        box.textContent = text;
        box.classList.remove('hidden');
    }

    document.getElementById('shareCodeInput').addEventListener('input', function () {
        this.value = this.value.toUpperCase();
    });

    document.getElementById('formShareAccess').addEventListener('submit', function (e) {
        e.preventDefault();

        const btn = document.getElementById('btnValidateShare');
        const originalHtml = btn.innerHTML;
        const result = document.getElementById('shareResult');
        const message = document.getElementById('shareMessage');

        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Memeriksa...';
        result.classList.add('hidden');
        message.classList.add('hidden');

        const fd = new FormData(this);
        fd.append('action', 'validate');

        fetch(shareEndpoint, { method: 'POST', body: fd })
            .then(r => r.json())
            .then(res => {
                if (!res.success) {
                    showShareMessage('error', res.message || 'Share Code tidak valid.');
                    return;
                }

                const file = res.file_info || {};
                document.getElementById('fileName').textContent = file.display_name || 'File tanpa nama';
                document.getElementById('fileSize').textContent = 'Ukuran: ' + formatBytes(file.zip_size);
                document.getElementById('fileCreated').textContent = file.created_at ? 'Diupload: ' + file.created_at : '';

                const downloadBtn = document.getElementById('btnDownloadShare');
                const downloadNote = document.getElementById('downloadNotAllowed');

                if (res.allow_download) {
                    downloadBtn.href = downloadBase + '?id=' + encodeURIComponent(res.filing_id) + '&share_hash=' + encodeURIComponent(res.share_hash);
                    downloadBtn.classList.remove('hidden');
                    downloadBtn.classList.add('flex');
                    downloadNote.classList.add('hidden');
                } else {
                    downloadBtn.classList.add('hidden');
                    downloadBtn.classList.remove('flex');
                    downloadNote.classList.remove('hidden');
                }

                result.classList.remove('hidden');
                showShareMessage('success', 'Share Code berhasil diverifikasi.');
            })
            .catch(() => {
                showShareMessage('error', 'Terjadi kesalahan jaringan atau server.');
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = originalHtml;
            });
    });
</script>
@endsection
