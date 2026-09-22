// assets/js/ui-feedback.js
// Sistem modal estetik global pengganti alert/confirm/prompt bawaan browser.
// Menyediakan: showAlert(), showConfirm(), showPrompt() + auto-intercept [data-confirm].
(function () {
    'use strict';

    var Z = 'z-[100001]';

    var TYPE = {
        success: { icon: 'fa-circle-check', accent: 'text-emerald-600', ring: 'bg-emerald-50', btn: 'bg-emerald-600 hover:bg-emerald-700' },
        error:   { icon: 'fa-circle-xmark', accent: 'text-red-600',     ring: 'bg-red-50',     btn: 'bg-red-600 hover:bg-red-700' },
        warning: { icon: 'fa-triangle-exclamation', accent: 'text-amber-600', ring: 'bg-amber-50', btn: 'bg-amber-600 hover:bg-amber-700' },
        info:    { icon: 'fa-circle-info', accent: 'text-brand-primary', ring: 'bg-blue-50',   btn: 'bg-brand-primary hover:bg-brand-primaryHover' },
        question:{ icon: 'fa-circle-question', accent: 'text-brand-primary', ring: 'bg-blue-50', btn: 'bg-brand-primary hover:bg-brand-primaryHover' },
        danger:  { icon: 'fa-triangle-exclamation', accent: 'text-red-600', ring: 'bg-red-50',  btn: 'bg-red-600 hover:bg-red-700' }
    };

    function detectType(message) {
        var m = String(message == null ? '' : message).toLowerCase();
        if (/gagal|error|kesalahan|koneksi terputus|tidak bisa|tidak dapat|dibatalkan|invalid|gagal/.test(m)) return 'error';
        if (/berhasil|sukses|tersimpan|disimpan|diperbarui|terkirim|dihapus|disalin|selesai/.test(m)) return 'success';
        if (/peringatan|hati-hati|perhatian|permanen|tidak bisa dikembalikan/.test(m)) return 'warning';
        return 'info';
    }

    function isDanger(message) {
        return /hapus|permanen|cabut|reset|batalkan|batal|delete/.test(String(message == null ? '' : message).toLowerCase());
    }

    var active = false;
    var queue = [];

    function push(opts) {
        return new Promise(function (resolve) {
            queue.push({ opts: opts, resolve: resolve });
            if (!active) next();
        });
    }

    function next() {
        if (active || !queue.length) return;
        active = true;
        var item = queue.shift();
        render(item.opts, function (result) {
            active = false;
            item.resolve(result);
            next();
        });
    }

    function render(opts, done) {
        opts = opts || {};
        var mode = opts.mode || 'alert';
        var type = opts.type || (mode === 'confirm' ? 'question' : detectType(opts.message));
        if (mode === 'confirm' && opts.danger && !opts.type) type = 'danger';
        var t = TYPE[type] || TYPE.info;

        var overlay = document.createElement('div');
        overlay.className = 'ui-fb-overlay fixed inset-0 ' + Z + ' flex items-center justify-center bg-black/50 p-4 opacity-0 transition-opacity duration-200';

        var card = document.createElement('div');
        card.className = 'w-full max-w-md overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-2xl scale-95 opacity-0 transition-all duration-200';
        card.setAttribute('role', 'dialog');
        card.setAttribute('aria-modal', 'true');

        var body = document.createElement('div');
        body.className = 'flex items-start gap-4 px-5 pt-5 pb-4';

        var iconWrap = document.createElement('div');
        iconWrap.className = 'flex h-11 w-11 shrink-0 items-center justify-center rounded-full ' + t.ring + ' ' + t.accent;
        var icon = document.createElement('i');
        icon.className = 'fas ' + (mode === 'confirm' && !opts.danger ? 'fa-circle-question' : t.icon) + ' text-lg';
        iconWrap.appendChild(icon);

        var content = document.createElement('div');
        content.className = 'min-w-0 flex-1';

        var title = document.createElement('p');
        title.className = 'text-sm font-extrabold text-gray-900';
        title.textContent = opts.title || (mode === 'confirm' ? 'Konfirmasi' : mode === 'prompt' ? 'Input Diperlukan' : 'Informasi');
        content.appendChild(title);

        var msg = document.createElement('p');
        msg.className = 'mt-1 whitespace-pre-line text-sm leading-relaxed text-gray-600';
        msg.textContent = opts.message == null ? '' : String(opts.message);
        content.appendChild(msg);

        var input = null;
        if (mode === 'prompt') {
            input = document.createElement('input');
            input.type = opts.inputType || 'text';
            input.value = opts.defaultValue || '';
            input.placeholder = opts.placeholder || '';
            input.className = 'mt-3 w-full rounded-xl border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-900 outline-none transition focus:border-blue-500 focus:bg-white focus:ring-2 focus:ring-blue-100';
            content.appendChild(input);
        }

        body.appendChild(iconWrap);
        body.appendChild(content);

        var footer = document.createElement('div');
        footer.className = 'flex items-center justify-end gap-2 border-t border-gray-100 bg-gray-50 px-5 py-3';

        function close(result) {
            overlay.style.opacity = '0';
            card.classList.add('scale-95', 'opacity-0');
            setTimeout(function () {
                overlay.remove();
                done(result);
                if (typeof opts.onClose === 'function') opts.onClose(result);
            }, 180);
        }

        if (mode !== 'alert') {
            var cancel = document.createElement('button');
            cancel.type = 'button';
            cancel.className = 'rounded-xl border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-600 transition hover:bg-gray-100';
            cancel.textContent = opts.cancelText || 'Batal';
            cancel.addEventListener('click', function () { close(mode === 'prompt' ? null : false); });
            footer.appendChild(cancel);
        }

        var ok = document.createElement('button');
        ok.type = 'button';
        ok.className = 'rounded-xl px-4 py-2 text-sm font-semibold text-white shadow-md transition ' + t.btn;
        ok.textContent = opts.confirmText || (mode === 'alert' ? 'OK' : mode === 'prompt' ? 'Simpan' : 'Lanjutkan');
        ok.addEventListener('click', function () { close(mode === 'prompt' ? (input ? input.value : '') : true); });
        footer.appendChild(ok);

        card.appendChild(body);
        card.appendChild(footer);
        overlay.appendChild(card);
        document.body.appendChild(overlay);

        requestAnimationFrame(function () {
            overlay.style.opacity = '1';
            card.classList.remove('scale-95', 'opacity-0');
        });

        overlay.addEventListener('mousedown', function (e) {
            if (e.target === overlay) close(mode === 'prompt' ? null : mode === 'alert' ? undefined : false);
        });

        document.addEventListener('keydown', function onKey(e) {
            if (!document.body.contains(overlay)) { document.removeEventListener('keydown', onKey); return; }
            if (e.key === 'Escape') {
                close(mode === 'alert' ? undefined : mode === 'prompt' ? null : false);
            } else if (e.key === 'Enter' && mode !== 'alert') {
                e.preventDefault();
                close(mode === 'prompt' ? (input ? input.value : '') : true);
            } else if (e.key === 'Enter' && mode === 'alert') {
                e.preventDefault();
                close(undefined);
            }
        });

        setTimeout(function () {
            if (mode === 'prompt' && input) input.focus();
            else ok.focus();
        }, 30);
    }

    function showAlert(message, opts) {
        opts = opts || {};
        if (typeof opts === 'string') opts = { type: opts };
        return push(Object.assign({ mode: 'alert', message: message }, opts));
    }

    function showConfirm(message, opts) {
        opts = opts || {};
        if (opts.danger === undefined) opts.danger = isDanger(message);
        return push(Object.assign({ mode: 'confirm', message: message }, opts));
    }

    function showPrompt(message, opts) {
        opts = opts || {};
        return push(Object.assign({ mode: 'prompt', message: message }, opts));
    }

    window.showAlert = showAlert;
    window.showConfirm = showConfirm;
    window.showPrompt = showPrompt;

    // Auto-intercept: form dengan atribut data-confirm.
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form || form.tagName !== 'FORM' || !form.hasAttribute('data-confirm')) return;
        if (form.dataset.uiConfirmed === '1') return;
        e.preventDefault();
        var submitter = e.submitter || null;
        showConfirm(form.getAttribute('data-confirm')).then(function (ok) {
            if (!ok) return;
            form.dataset.uiConfirmed = '1';
            if (form.requestSubmit) form.requestSubmit(submitter || undefined);
            else form.submit();
            setTimeout(function () { delete form.dataset.uiConfirmed; }, 0);
        });
    }, true);

    // Auto-intercept: tombol/anchor dengan atribut data-confirm.
    document.addEventListener('click', function (e) {
        var el = e.target.closest('button[data-confirm], input[type="submit"][data-confirm], a[data-confirm]');
        if (!el) return;
        if (el.dataset.uiConfirmed === '1') return;
        e.preventDefault();
        var form = el.form;
        showConfirm(el.getAttribute('data-confirm')).then(function (ok) {
            if (!ok) return;
            if (el.tagName === 'A') {
                if (el.target === '_blank') window.open(el.href, '_blank');
                else window.location.href = el.href;
                return;
            }
            el.dataset.uiConfirmed = '1';
            if (form && form.requestSubmit) form.requestSubmit(el);
            else if (form) form.submit();
            else el.click();
            setTimeout(function () { delete el.dataset.uiConfirmed; }, 0);
        });
    }, true);
})();
