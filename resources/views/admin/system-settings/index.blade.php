@extends('layouts.app')

@section('title', 'RUN-ITC | System Settings')

@section('content')
<div class="mx-auto max-w-4xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">System Settings</h1>
        <p class="mt-0.5 text-sm text-gray-500">Kelola konfigurasi aplikasi.</p>
    </div>

    @forelse ($groups as $group => $settings)
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
            <div class="border-b border-gray-100 bg-gray-50 px-6 py-4">
                <h2 class="font-bold text-gray-900">{{ $groupLabels[$group] ?? ucfirst($group) }}</h2>
            </div>
            <div class="divide-y divide-gray-100">
                @foreach ($settings as $setting)
                    <div class="p-5" data-key="{{ $setting['setting_key'] }}">
                        <label class="block text-sm font-semibold text-gray-800">{{ ucwords(str_replace('_', ' ', $setting['setting_key'])) }}</label>
                        <p class="mb-3 mt-0.5 text-xs text-gray-500">{{ $setting['description'] }}</p>

                        @if ($setting['setting_type'] === 'bool')
                            <label class="inline-flex cursor-pointer items-center gap-3">
                                <input type="checkbox" class="setting-toggle h-5 w-5 rounded border-gray-300 text-brand-primary focus:ring-brand-primary" data-key="{{ $setting['setting_key'] }}" @checked($setting['setting_value'])>
                                <span class="text-sm text-gray-600">{{ $setting['setting_value'] ? 'Aktif' : 'Nonaktif' }}</span>
                            </label>
                        @else
                            <div class="flex items-center gap-2">
                                <input type="{{ $setting['setting_type'] === 'int' ? 'number' : 'text' }}" class="setting-input flex-1 rounded-lg border border-gray-300 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-slate-200" data-key="{{ $setting['setting_key'] }}" value="{{ $setting['setting_value'] }}" @if($setting['setting_type'] === 'int') min="0" @endif>
                                <button type="button" onclick="resetSetting('{{ $setting['setting_key'] }}')" class="rounded-lg bg-gray-100 px-3 py-2 text-xs font-semibold text-gray-500 transition hover:bg-gray-200">Default</button>
                            </div>
                        @endif
                        <p class="setting-feedback mt-1 text-xs text-gray-400" id="fb_{{ $setting['setting_key'] }}"></p>
                    </div>
                @endforeach
            </div>
        </div>
    @empty
        <div class="rounded-2xl border border-gray-200 bg-white p-12 text-center text-gray-400 shadow-sm">
            <i class="fa-solid fa-sliders mb-3 block text-4xl"></i>
            <p>Belum ada pengaturan yang tersedia.</p>
        </div>
    @endforelse
</div>

<script>
document.querySelectorAll('.setting-input').forEach(input => {
    input.addEventListener('change', function () { saveSetting(this.dataset.key, this.value); });
});

document.querySelectorAll('.setting-toggle').forEach(cb => {
    cb.addEventListener('change', function () {
        const label = this.closest('label').querySelector('span');
        label.textContent = this.checked ? 'Aktif' : 'Nonaktif';
        saveSetting(this.dataset.key, this.checked ? '1' : '0');
    });
});

function saveSetting(key, value) {
    const fb = document.getElementById('fb_' + key);
    fb.innerHTML = '<span class="text-amber-500">Menyimpan...</span>';
    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('action', 'update_setting');
    formData.append('key', key);
    formData.append('value', value);

    fetch('{{ route('admin.system-settings.api') }}', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            fb.innerHTML = data.success ? '<span class="text-green-600">✓ ' + data.message + '</span>' : '<span class="text-red-600">✗ ' + (data.error || 'Gagal') + '</span>';
            if (data.success) setTimeout(() => fb.innerHTML = '', 3000);
        })
        .catch(() => fb.innerHTML = '<span class="text-red-600">✗ Network error</span>');
}

function resetSetting(key) {
    if (!confirm('Reset "' + key.replace(/_/g, ' ') + '" ke nilai default?')) return;
    const fb = document.getElementById('fb_' + key);
    fb.innerHTML = '<span class="text-amber-500">Mereset...</span>';
    const formData = new FormData();
    formData.append('_token', '{{ csrf_token() }}');
    formData.append('action', 'reset_setting');
    formData.append('key', key);

    fetch('{{ route('admin.system-settings.api') }}', { method: 'POST', body: formData })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                fb.innerHTML = '<span class="text-green-600">✓ Reset ke default</span>';
                setTimeout(() => location.reload(), 800);
            } else {
                fb.innerHTML = '<span class="text-red-600">✗ ' + (data.error || 'Gagal') + '</span>';
            }
        })
        .catch(() => fb.innerHTML = '<span class="text-red-600">✗ Network error</span>');
}
</script>
@endsection
