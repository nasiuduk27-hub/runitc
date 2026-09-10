@extends('layouts.app')

@section('title', 'RUN-ITC | Input Loan Manual')

@section('content')
<div class="mx-auto max-w-7xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Input Loan Manual</h1>
        <p class="mt-1 text-sm text-gray-500">Untuk memasukkan pinjaman lama. Tidak melalui proses accepted/reject.</p>
    </div>
    @if (session('success')) <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">{{ session('success') }}</div> @endif
    @if ($errors->any()) <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">{{ $errors->first() }}</div> @endif
    <div class="flex flex-wrap gap-2 rounded-2xl border border-gray-200 bg-white p-3 shadow-sm">
        <a href="{{ route('cooperative.manual-loans.create') }}" class="rounded-xl px-4 py-2 text-sm font-semibold {{ $mode === 'loan' ? 'bg-brand-primary text-white' : 'text-gray-600 hover:bg-gray-50' }}">Loan Baru Manual</a>
        <a href="{{ route('cooperative.manual-loans.create', ['mode' => 'skip']) }}" class="rounded-xl px-4 py-2 text-sm font-semibold {{ $mode === 'skip' ? 'bg-brand-primary text-white' : 'text-gray-600 hover:bg-gray-50' }}">Skip Pokok Manual</a>
        <a href="{{ route('cooperative.manual-loans.create', ['mode' => 'accelerate']) }}" class="rounded-xl px-4 py-2 text-sm font-semibold {{ $mode === 'accelerate' ? 'bg-orange-500 text-white' : 'text-gray-600 hover:bg-gray-50' }}">Percepatan Manual</a>
    </div>
    @if ($mode !== 'loan')
        <form method="POST" action="{{ route('cooperative.manual-loans.adjustment') }}" id="adjustmentForm" class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            @csrf
            <input type="hidden" name="mode" value="{{ $mode }}">
            <div><label for="adjustment_loan" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Loan Manual</label><select id="adjustment_loan" name="loan_rec_id" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"><option value="">-- Pilih Loan Manual --</option>@foreach ($loans as $loan)<option value="{{ $loan->rec_id }}">{{ $loan->trnno }} | {{ $loan->member?->icuno ?? '-' }} | {{ $loan->member?->icunm ?? $loan->manual_member_name ?? $loan->descr }} | {{ $loan->trndt ? date('d/m/Y', strtotime($loan->trndt)) : '-' }} | Rp {{ number_format((int) $loan->totalloan, 0, ',', '.') }} | {{ $loan->isSettledIndicative() ? 'Lunas' : 'Berjalan' }}</option>@endforeach</select><p class="mt-1 text-[11px] text-gray-400">Format: nomor loan | nomor anggota | nama | tanggal | pokok | status.</p></div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2"><div><label for="adjustment_start" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Mulai {{ $mode === 'skip' ? 'Skip' : 'Percepatan' }} (YYYYMM)</label><select id="adjustment_start" name="start_period" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"><option value="">Pilih loan terlebih dahulu</option></select></div><div><label for="adjustment_months" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">{{ $mode === 'skip' ? 'Lama Skip' : 'Percepatan' }} (Bulan)</label><input id="adjustment_months" name="months_count" type="number" min="1" max="12" value="1" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"></div></div>
            <button type="button" id="adjustmentSimulate" class="inline-flex items-center gap-2 rounded-xl border border-brand-primary bg-white px-5 py-2.5 text-sm font-semibold text-brand-primary hover:bg-blue-50"><i class="fas fa-calculator"></i> Simulasikan</button>
            <button type="submit" id="adjustmentSave" disabled class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover disabled:cursor-not-allowed disabled:opacity-50"><i class="fas fa-save"></i> Terapkan Manual</button>
        </form>
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <div id="currentSchedule" class="hidden overflow-hidden rounded-xl border border-blue-100 bg-blue-50/40"><div class="border-b border-blue-100 px-4 py-3"><p class="text-xs font-bold uppercase tracking-wide text-blue-700">Jadwal Loan Terpilih</p><p id="currentScheduleMeta" class="mt-1 text-xs text-blue-600"></p></div><div class="max-h-64 overflow-auto bg-white"><table class="w-full min-w-[620px] text-left text-xs"><thead class="sticky top-0 bg-gray-50 uppercase text-gray-500"><tr><th class="px-3 py-2">#</th><th class="px-3 py-2">Periode</th><th class="px-3 py-2 text-right">Pokok</th><th class="px-3 py-2 text-right">Bunga</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2 text-right">Sisa Pokok</th></tr></thead><tbody id="currentScheduleRows" class="divide-y divide-gray-100"></tbody></table></div></div>
            <div id="adjustmentPreview" class="hidden space-y-3 rounded-2xl border border-blue-200 bg-blue-50/50 p-5"><p class="text-sm font-bold text-blue-800">Preview Jadwal Setelah Penyesuaian</p><div class="max-h-72 overflow-y-auto rounded-xl border border-blue-100 bg-white"><table class="w-full text-left text-xs"><thead class="sticky top-0 bg-gray-50 uppercase text-gray-500"><tr><th class="px-3 py-2">#</th><th class="px-3 py-2">Periode</th><th class="px-3 py-2 text-right">Pokok</th><th class="px-3 py-2 text-right">Bunga</th><th class="px-3 py-2">Status</th></tr></thead><tbody id="adjustmentRows" class="divide-y divide-gray-100"></tbody></table></div><p id="adjustmentError" class="hidden text-xs font-semibold text-red-600"></p></div>
        </div>
    @endif
    @if ($mode === 'loan')
    <form method="POST" action="{{ route('cooperative.manual-loans.store') }}" id="manualLoanForm" class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        @csrf
        <div class="flex items-center justify-between"><h2 class="text-sm font-bold text-gray-800">Input Satu Pinjaman</h2><span class="text-xs text-gray-400">Detail cicilan dibuat otomatis</span></div>
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div><label for="member_rec_id" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Anggota</label><select id="member_rec_id" name="member_rec_id" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"><option value="">Nama manual</option>@foreach ($members as $member)<option value="{{ $member->rec_id }}" data-name="{{ $member->icunm }}" @selected(old('member_rec_id') == $member->rec_id)>{{ $member->icuno }} - {{ $member->icunm }}</option>@endforeach</select><p class="mt-1 text-[11px] text-gray-400">Pilih anggota terdaftar, atau biarkan kosong untuk anggota baru.</p></div>
            <div><label for="member_name" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Nama Anggota</label><input id="member_name" name="member_name" value="{{ old('member_name') }}" @readonly(old('member_rec_id')) required class="w-full rounded-xl border border-gray-200 px-3 py-2.5 text-sm {{ old('member_rec_id') ? 'bg-gray-100 text-gray-500' : 'bg-gray-50' }}"><p class="mt-1 text-[11px] text-gray-400">Terisi & terkunci otomatis saat anggota dipilih. Isi manual untuk anggota baru.</p></div>
            <div><label for="member_status" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Status Anggota</label><label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"><input id="member_status" name="member_status" type="checkbox" value="active" @checked(old('member_status') === 'active') class="h-4 w-4 rounded border-gray-300 text-brand-primary focus:ring-brand-primary"><span class="font-semibold text-gray-700">Aktif</span></label><p class="mt-1 text-[11px] text-gray-400">Dipakai saat membuat anggota baru (tanpa master). Tidak dicentang = Tidak Aktif.</p></div>
            <div><label for="trndt" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tanggal Transaksi</label><input id="trndt" name="trndt" type="date" value="{{ old('trndt', date('Y-m-d')) }}" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"></div>
            <div><label for="startper_display" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Periode</label><input type="hidden" id="startper" name="startper"><div id="startper_display" class="rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm font-bold text-gray-800">Otomatis dari tanggal transaksi</div><p class="mt-1 text-[11px] text-gray-400">Siklus tutup buku: tanggal 21 masuk periode bulan berikutnya.</p></div>
            <div><label for="principal" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Pokok Pinjaman</label><input id="principal" name="principal" type="text" inputmode="numeric" autocomplete="off" min="1" value="{{ old('principal') }}" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"></div>
            <div><label for="term" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tenor (bulan)</label><input id="term" name="term" type="number" min="1" max="120" value="{{ old('term') }}" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"></div>
            <div><label for="annual_rate" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Bunga Tahunan (%)</label><input id="annual_rate" name="annual_rate" type="number" min="0" max="100" step="0.01" value="{{ old('annual_rate', 6) }}" required class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"></div>
            <div><label for="payment_status" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Status Historis</label><select id="payment_status" name="payment_status" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"><option value="paid">Sudah Lunas</option><option value="running">Masih Berjalan</option></select></div>
            <div><label for="admin_fee" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Biaya Admin Bank (Rp)</label><input id="admin_fee" name="admin_fee" type="text" inputmode="numeric" autocomplete="off" value="{{ old('admin_fee') }}" placeholder="0" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"><p class="mt-1 text-[11px] text-gray-400">Diisi manual sesuai biaya admin bank.</p></div>
            <div><label for="admin_fee_type" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">Tipe Biaya Admin</label><select id="admin_fee_type" name="admin_fee_type" class="w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm"><option value="exclude" @selected(old('admin_fee_type', 'exclude') === 'exclude')>Exclude, ditagih ke anggota</option><option value="include" @selected(old('admin_fee_type') === 'include')>Include, dipotong dari pencairan</option></select></div>
            <input type="hidden" name="calculation_method" value="flat">
        </div>
        <div class="flex flex-wrap gap-2"><button type="button" id="simulateButton" class="inline-flex items-center gap-2 rounded-xl border border-brand-primary bg-white px-5 py-2.5 text-sm font-semibold text-brand-primary hover:bg-blue-50"><i class="fas fa-calculator"></i> Simulasikan</button><button type="submit" id="saveButton" disabled class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover disabled:cursor-not-allowed disabled:opacity-50"><i class="fas fa-save"></i> Simpan Loan Manual</button></div>
    </form>
    @endif
    <div id="preview" class="hidden space-y-4 rounded-2xl border border-blue-200 bg-blue-50/50 p-5 shadow-sm"><div class="flex items-center justify-between"><h2 class="text-sm font-bold text-blue-800">Preview Simulasi</h2><span id="previewPeriod" class="text-xs font-semibold text-blue-600"></span></div><div class="grid grid-cols-2 gap-3 sm:grid-cols-4"><div><p class="text-[10px] font-bold uppercase text-blue-500">Cicilan Bulan I</p><p id="previewFirst" class="font-extrabold text-gray-900">-</p></div><div><p class="text-[10px] font-bold uppercase text-blue-500">Total Bunga</p><p id="previewInterest" class="font-extrabold text-gray-900">-</p></div><div><p class="text-[10px] font-bold uppercase text-blue-500">Total Tagihan</p><p id="previewTotal" class="font-extrabold text-gray-900">-</p></div><div><p class="text-[10px] font-bold uppercase text-blue-500">Jumlah Cicilan</p><p id="previewCount" class="font-extrabold text-gray-900">-</p></div></div><div class="max-h-72 overflow-y-auto rounded-xl border border-blue-100 bg-white"><table class="w-full min-w-[640px] text-left text-xs"><thead class="sticky top-0 bg-gray-50 uppercase text-gray-500"><tr><th class="px-3 py-2">#</th><th class="px-3 py-2">Periode</th><th class="px-3 py-2 text-right">Pokok</th><th class="px-3 py-2 text-right">Bunga</th><th class="px-3 py-2 text-right">Total</th><th class="px-3 py-2 text-right">Sisa Pokok</th></tr></thead><tbody id="previewRows" class="divide-y divide-gray-100"></tbody></table></div><p id="previewError" class="hidden text-xs font-semibold text-red-600"></p></div>
    @if ($mode === 'loan')
    @if (false) {{-- Form import di-hide sementara, user belum membutuhkan. --}}
    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-800">
        <p class="font-bold">Format import</p>
        <p class="mt-1">Satu baris Excel adalah satu cicilan. Gunakan <code>source_key</code> yang sama untuk mengelompokkan cicilan dalam satu pinjaman.</p>
        <p class="mt-1">Isi <code>paidst=1</code> untuk cicilan lunas. Baris skip dapat memakai <code>amount=0</code>, dan baris percepatan cukup memakai jadwal final.</p>
    </div>
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('cooperative.manual-loans.template') }}" class="inline-flex items-center gap-2 rounded-xl border border-green-200 bg-white px-5 py-2.5 text-sm font-semibold text-green-700 hover:bg-green-50"><i class="fas fa-file-excel"></i> Download Template Excel</a>
    </div>
    <form method="POST" action="{{ route('cooperative.manual-loans.import') }}" enctype="multipart/form-data" class="space-y-5 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
        @csrf
        <div>
            <label for="file" class="mb-1.5 block text-xs font-bold uppercase tracking-wide text-gray-500">File Excel</label>
            <input id="file" name="file" type="file" accept=".xlsx,.xls,.csv" required class="block w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 text-sm">
            <p class="mt-1 text-xs text-gray-400">Maksimal 20 MB. Pastikan backup database tersedia sebelum import data besar.</p>
        </div>
        <button type="submit" class="inline-flex items-center gap-2 rounded-xl bg-brand-primary px-5 py-2.5 text-sm font-semibold text-white hover:bg-brand-primaryHover"><i class="fas fa-upload"></i> Import Loan</button>
    </form>
    @endif
    @endif
</div>
@endsection

@push('scripts')
<script>
(function () {
    const form = document.getElementById('adjustmentForm'); if (!form) return;
    const preview = document.getElementById('adjustmentPreview'); const error = document.getElementById('adjustmentError'); const save = document.getElementById('adjustmentSave');
    const loanSelect = document.getElementById('adjustment_loan'); const currentSchedule = document.getElementById('currentSchedule');
    const onLoanChange = async () => {
        save.disabled = true; preview.classList.add('hidden'); currentSchedule.classList.add('hidden');
        if (!loanSelect.value) return;
        const response = await fetch(@json(route('cooperative.manual-loans.schedule')), {method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}','Content-Type':'application/json'}, body:JSON.stringify({loan_rec_id:loanSelect.value})});
        const data = await response.json();
        if (!response.ok) return;
        document.getElementById('currentScheduleMeta').textContent = data.loan.trnno+' - '+data.loan.member+' ('+data.loan.status+')';
        const startSelect = document.getElementById('adjustment_start'); const periods = [...new Set(data.rows.map(row => row.periode))];
        startSelect.innerHTML = periods.map(period => '<option value="'+period+'">'+period+'</option>').join('');
        document.getElementById('currentScheduleRows').innerHTML = data.rows.map(row => '<tr><td class="px-3 py-1.5">'+row.seqno+'</td><td class="px-3 py-1.5">'+row.periode+'</td><td class="px-3 py-1.5 text-right">'+Number(row.amount||0).toLocaleString('id-ID')+'</td><td class="px-3 py-1.5 text-right">'+Number(row.int_amt||0).toLocaleString('id-ID')+'</td><td class="px-3 py-1.5 text-right">'+Number((row.amount||0)+(row.int_amt||0)+(row.others||0)).toLocaleString('id-ID')+'</td><td class="px-3 py-1.5 text-right font-semibold">'+Number(row.outstand||0).toLocaleString('id-ID')+'</td></tr>').join('');
        currentSchedule.classList.remove('hidden');
    };
    if (window.jQuery && jQuery.fn.select2) {
        jQuery(loanSelect).select2({ placeholder: 'Cari nomor, nama, atau tanggal loan...', allowClear: true, width: '100%' });
        jQuery(loanSelect).on('change', onLoanChange);
    } else {
        loanSelect.addEventListener('change', onLoanChange);
    }
    document.getElementById('adjustmentSimulate').addEventListener('click', async () => { preview.classList.remove('hidden'); error.classList.add('hidden'); save.disabled = true; try { const response = await fetch(@json(route('cooperative.manual-loans.simulate-adjustment')), {method:'POST', headers:{'Accept':'application/json','X-CSRF-TOKEN':'{{ csrf_token() }}'}, body:new FormData(form)}); const data=await response.json(); if(!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Simulasi gagal.'); document.getElementById('adjustmentRows').innerHTML=data.rows.map(row=>'<tr><td class="px-3 py-1.5">'+row.seqno+'</td><td class="px-3 py-1.5">'+row.periode+'</td><td class="px-3 py-1.5 text-right">'+Number(row.amount||0).toLocaleString('id-ID')+'</td><td class="px-3 py-1.5 text-right">'+Number(row.int_amt||0).toLocaleString('id-ID')+'</td><td class="px-3 py-1.5">'+(row.status || '')+'</td></tr>').join(''); save.disabled=false; } catch(e) { error.textContent=e.message; error.classList.remove('hidden'); } });
})();
(function () {
    const form = document.getElementById('manualLoanForm');
    if (!form) return;
    const date = document.getElementById('trndt');
    const principal = document.getElementById('principal');
    const adminFee = document.getElementById('admin_fee');
    const startper = document.getElementById('startper');
    const startDisplay = document.getElementById('startper_display');
    const memberSelect = document.getElementById('member_rec_id');
    const memberName = document.getElementById('member_name');
    const preview = document.getElementById('preview');
    const button = document.getElementById('simulateButton');
    const save = document.getElementById('saveButton');
    const money = value => 'Rp ' + Number(value || 0).toLocaleString('id-ID');
    const period = value => { const d = new Date(value + 'T00:00:00'); if (d.getDate() > 20) d.setMonth(d.getMonth() + 1); return d.getFullYear() + String(d.getMonth() + 1).padStart(2, '0'); };
    const normalize = () => { principal.value = principal.value.replace(/\D/g, ''); adminFee.value = adminFee.value.replace(/\D/g, ''); };
    const updatePeriod = () => { if (!date.value) return; startper.value = period(date.value); startDisplay.textContent = startper.value; };
    principal.addEventListener('input', () => { principal.value = principal.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); save.disabled = true; });
    adminFee.addEventListener('input', () => { adminFee.value = adminFee.value.replace(/\D/g, '').replace(/\B(?=(\d{3})+(?!\d))/g, '.'); save.disabled = true; });
    date.addEventListener('change', () => { updatePeriod(); save.disabled = true; });
    const lockMemberName = () => {
        const selected = !!memberSelect.value;
        memberName.readOnly = selected;
        memberName.classList.toggle('bg-gray-100', selected);
        memberName.classList.toggle('text-gray-500', selected);
        memberName.classList.toggle('bg-gray-50', !selected);
    };
    const syncMember = () => { memberName.value = memberSelect.selectedOptions[0]?.dataset.name || ''; lockMemberName(); save.disabled = true; };
    memberSelect.addEventListener('change', syncMember);
    if (window.jQuery && jQuery.fn.select2) {
        jQuery(memberSelect).select2({ placeholder: 'Cari nomor atau nama anggota...', allowClear: true, width: '100%' });
        jQuery(memberSelect).on('change', syncMember);
    }
    lockMemberName();
    updatePeriod();
    button.addEventListener('click', async () => {
        normalize(); updatePeriod(); preview.classList.remove('hidden'); document.getElementById('previewError').classList.add('hidden'); button.disabled = true; save.disabled = true;
        try {
            const response = await fetch(@json(route('cooperative.manual-loans.simulate')), { method: 'POST', headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'}, body: new FormData(form) });
            const data = await response.json(); if (!response.ok) throw new Error(data.message || Object.values(data.errors || {}).flat().join(' ') || 'Simulasi gagal.');
            document.getElementById('previewPeriod').textContent = 'Mulai periode ' + data.startper;
            document.getElementById('previewFirst').textContent = money(data.summary.first_installment);
            document.getElementById('previewInterest').textContent = money(data.summary.total_interest);
            document.getElementById('previewTotal').textContent = money(data.summary.total_payment);
            document.getElementById('previewCount').textContent = data.schedule.length + ' bulan';
            document.getElementById('previewRows').innerHTML = data.schedule.map(row => '<tr><td class="px-3 py-1.5">'+row.seqno+'</td><td class="px-3 py-1.5">'+row.periode+'</td><td class="px-3 py-1.5 text-right">'+money(row.amount)+'</td><td class="px-3 py-1.5 text-right">'+money(row.int_amt)+'</td><td class="px-3 py-1.5 text-right font-semibold">'+money(row.total)+'</td><td class="px-3 py-1.5 text-right font-semibold">'+money(row.outstand)+'</td></tr>').join('');
            principal.value = Number(principal.value || 0).toLocaleString('id-ID'); adminFee.value = adminFee.value ? Number(adminFee.value).toLocaleString('id-ID') : ''; save.disabled = false;
        } catch (error) { const target = document.getElementById('previewError'); target.textContent = error.message || 'Simulasi gagal.'; target.classList.remove('hidden'); } finally { button.disabled = false; }
    });
    form.addEventListener('submit', normalize);
})();
</script>
@endpush
