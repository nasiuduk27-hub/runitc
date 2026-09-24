{{-- Loader datepicker DD/MM/YYYY global (flatpickr, reuse CDN yang sudah dipakai). --}}
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr/dist/l10n/id.js"></script>
<style>
    .date-input-wrap { position: relative; }
    .date-input-wrap > input[data-date-display] { padding-right: 2.25rem; }
    .date-input-wrap > .date-pick-btn {
        position: absolute; right: .5rem; top: 50%; transform: translateY(-50%);
        background: none; border: 0; padding: 0; line-height: 1;
        cursor: pointer; color: #9ca3af;
    }
    .date-input-wrap > .date-pick-btn:hover { color: #4b5563; }
</style>
<script>
(function () {
    // ponytail: validasi tanggal kalender murni, tanpa lib tambahan.
    function parseDate(str) {
        if (!str) return null;
        let m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(String(str).trim());
        if (m && checkValid(+m[3], +m[2], +m[1])) return new Date(+m[1], +m[2] - 1, +m[3]);
        m = /^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/.exec(String(str).trim());
        if (m && checkValid(+m[1], +m[2], +m[3])) return new Date(+m[3], +m[2] - 1, +m[1]);
        return str;
    }
    function checkValid(d, mo, y) {
        if (y < 1900 || y > 2100) return false;
        const dt = new Date(y, mo - 1, d);
        return dt.getFullYear() === y && dt.getMonth() === mo - 1 && dt.getDate() === d;
    }
    function toIso(text) {
        const m = /^(\d{1,2})[\/\-\.](\d{1,2})[\/\-\.](\d{4})$/.exec((text || '').trim());
        if (!m) return '';
        const d = +m[1], mo = +m[2], y = +m[3];
        if (!checkValid(d, mo, y)) return '';
        return y + '-' + String(mo).padStart(2, '0') + '-' + String(d).padStart(2, '0');
    }
    function hiddenFor(el) { return document.getElementById(el.getAttribute('data-date-target')); }
    function fireChange(el) { el.dispatchEvent(new Event('change', { bubbles: true })); }

    function initDisplay(el) {
        if (!el || el.dataset.dateReady) return;
        el.dataset.dateReady = '1';
        const button = el.closest('.date-input-wrap')?.querySelector('.date-pick-btn');
        // Tanpa flatpickr (CDN gagal): ketikan dd/mm/yyyy tetap disinkronkan ke hidden.
        if (!window.flatpickr) {
            if (button) button.addEventListener('click', () => el.focus());
            el.addEventListener('change', () => {
                const hidden = hiddenFor(el);
                if (hidden) hidden.value = el.value.trim() === '' ? '' : toIso(el.value);
            });
            return;
        }
        const opts = { dateFormat: 'd/m/Y', allowInput: true, clickOpens: true, disableMobile: true, defaultDate: el.value || null };
        try {
            if (flatpickr.l10ns && flatpickr.l10ns.id) opts.locale = 'id';
            if (el.dataset.min) opts.minDate = parseDate(el.dataset.min);
            if (el.dataset.max) opts.maxDate = parseDate(el.dataset.max);
        } catch (e) {}
        const fp = flatpickr(el, Object.assign(opts, {
            onChange(selected) {
                const hidden = hiddenFor(el);
                if (hidden) hidden.value = selected.length ? flatpickr.formatDate(selected[0], 'Y-m-d') : '';
                fireChange(el);
            },
        }));
        el._datePicker = fp;
        if (button) {
            button.addEventListener('click', (e) => {
                e.preventDefault();
                e.stopPropagation();
                fp.toggle();
            });
        }
        // Sinkronkan ketikan manual (real-time & saat blur) ke hidden Y-m-d.
        function syncManualInput(e) {
            const hidden = hiddenFor(el);
            if (!hidden) return;
            const val = el.value.trim();
            if (val === '') {
                hidden.value = '';
                try { fp.clear(false); } catch (err) {}
                return;
            }
            const iso = toIso(val);
            if (iso !== '') {
                hidden.value = iso;
                try { fp.setDate(parseDate(iso), false); } catch (err) {}
            } else if (e && e.type === 'change') {
                hidden.value = '';
            }
        }
        el.addEventListener('input', syncManualInput);
        el.addEventListener('change', syncManualInput);
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
