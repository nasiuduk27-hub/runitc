{{-- Loader datepicker DD/MM/YYYY global (flatpickr, reuse CDN yang sudah dipakai). --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
<script>
(function () {
    // ponytail: validasi tanggal kalender murni, tanpa lib tambahan.
    function toIso(text) {
        const m = /^(\d{1,2})\/(\d{1,2})\/(\d{4})$/.exec((text || '').trim());
        if (!m) return '';
        const d = +m[1], mo = +m[2], y = +m[3];
        if (y < 1900 || y > 2100) return '';
        const dt = new Date(y, mo - 1, d);
        if (dt.getFullYear() !== y || dt.getMonth() !== mo - 1 || dt.getDate() !== d) return '';
        return y + '-' + String(mo).padStart(2, '0') + '-' + String(d).padStart(2, '0');
    }
    function hiddenFor(el) { return document.getElementById(el.getAttribute('data-date-target')); }
    function fireChange(el) { el.dispatchEvent(new Event('change', { bubbles: true })); }

    function initDisplay(el) {
        if (!el || el.dataset.dateReady) return;
        el.dataset.dateReady = '1';
        // Tanpa flatpickr (CDN gagal): ketikan dd/mm/yyyy tetap disinkronkan ke hidden.
        if (!window.flatpickr) {
            el.addEventListener('change', () => {
                const hidden = hiddenFor(el);
                if (hidden) hidden.value = el.value.trim() === '' ? '' : toIso(el.value);
            });
            return;
        }
        const opts = { dateFormat: 'd/m/Y', allowInput: true, clickOpens: true, disableMobile: true, defaultDate: el.value || null };
        try {
            if (flatpickr.l10ns && flatpickr.l10ns.id) opts.locale = 'id';
            if (el.dataset.min) opts.minDate = el.dataset.min;
            if (el.dataset.max) opts.maxDate = el.dataset.max;
        } catch (e) {}
        const fp = flatpickr(el, Object.assign(opts, {
            onChange(selected) {
                const hidden = hiddenFor(el);
                if (hidden) hidden.value = selected.length ? flatpickr.formatDate(selected[0], 'Y-m-d') : '';
                fireChange(el);
            },
        }));
        el._datePicker = fp;
        // Sinkronkan ketikan manual (saat blur/enter) ke hidden Y-m-d.
        el.addEventListener('change', () => {
            const hidden = hiddenFor(el);
            if (!hidden) return;
            if (el.value.trim() === '') {
                hidden.value = '';
                try { fp.clear(false); } catch (e) {}
                return;
            }
            const iso = toIso(el.value);
            hidden.value = iso;
            if (iso !== '') { try { fp.setDate(iso, false); } catch (e) {} }
        });
    }

    function initAll(root) {
        (root || document).querySelectorAll('input[data-date-display]').forEach(initDisplay);
    }

    document.addEventListener('DOMContentLoaded', () => initAll(document));
    // Sinkronkan ulang setelah form reset (nilai native kembali, state flatpickr perlu disegarkan).
    document.addEventListener('reset', (e) => {
        const form = e.target;
        if (!form || !form.querySelectorAll) return;
        setTimeout(() => form.querySelectorAll('input[data-date-display]').forEach((el) => {
            const hidden = hiddenFor(el);
            if (el._datePicker) { try { el._datePicker.setDate(el.value || null, false); } catch (err) {} }
            if (hidden) hidden.value = el.value.trim() === '' ? '' : toIso(el.value);
        }), 0);
    });
    window.initDateInputs = initAll;
})();
</script>
