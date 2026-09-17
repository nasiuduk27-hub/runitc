// assets/js/main.js

/**
 * 1. UI TOGGLES (Dari layout_footer.php)
 */
function toggleDropdown(element) {
    if (typeof element === 'string') {
        const menu = document.getElementById(element);
        const icon = document.getElementById('icon-' + element);
        if (menu) menu.classList.toggle('hidden');
        if (icon) icon.classList.toggle('rotate-90');
    } else {
        // Element is the button itself
        const submenu = element.nextElementSibling;
        const icon = element.querySelector('.sidebar-chevron');
        if (submenu) submenu.classList.toggle('hidden');
        if (icon) icon.classList.toggle('rotate-90');
    }
}

function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    if (sidebar) sidebar.classList.toggle('minimized');
}

function toggleProfileMenu() {
    const menu = document.getElementById('profileMenu');
    if (menu) menu.classList.toggle('hidden');
}

function togglePassword() {
    const pwd = document.getElementById('passwd');
    const icon = document.getElementById('toggleIcon');
    if (pwd && icon) {
        if (pwd.type === 'password') {
            pwd.type = 'text';
            icon.classList.replace('fa-eye-slash', 'fa-eye');
        } else {
            pwd.type = 'password';
            icon.classList.replace('fa-eye', 'fa-eye-slash');
        }
    }
}

/**
 * 2. DASHBOARD WIDGETS (Dari dashboard.php)
 */
const DASHBOARD_WIDGET_LAYOUT_KEY = 'runitc.dashboard.widgetLayout.v1';
const DASHBOARD_WIDGET_STATE_KEY = 'runitc.dashboard.widgetState.v2';
let dashboardWidgetsLoadedFromServer = false;
let dashboardWidgetsApplyingState = false;
let dashboardWidgetsSaveTimer = null;
const DASHBOARD_STANDARD_WIDGETS = {
    'kpi-active-rooms': 'Active Rooms Today',
    'kpi-participants': 'Participants Today',
    'kpi-crc-pending': 'CRC Pending',
    'kpi-system-modules': 'System Modules',
    'tad-upcoming': 'TAD Dashboard',
    'system-info': 'System Info',
    'tad-participant-recap': 'Rekap TAD',
};

function dashboardEscape(value) {
    return String(value || '').replace(/[&<>'"]/g, function (char) {
        return {'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;'}[char];
    });
}

function getDashboardState() {
    try {
        return JSON.parse(localStorage.getItem(DASHBOARD_WIDGET_STATE_KEY) || '{}') || {};
    } catch (e) {
        return {};
    }
}

function addSelectedDashboardWidget() {
    const picker = document.getElementById('dashboardWidgetPicker');
    const type = picker ? picker.value : 'clock';
    if (type.indexOf('show:') === 0) return showDashboardWidget(type.slice(5));
    addWidget(type);
}

function addWidget(type = 'image', data = {}, id) {
    const container = document.getElementById('widgetContainer');
    if (!container) return;

    const widgetId = id || type + '-widget-' + Date.now();
    container.insertAdjacentHTML('beforeend', getDashboardAddonWidgetHTML(type, widgetId, data));
    initializeDashboardWidgets();
    initializeDashboardAddonInputs(container.querySelector('[data-widget-id="' + widgetId + '"]'));
    saveDashboardWidgetLayout();
}

function removeWidget(btn) {
    btn.closest('.widget-card').remove();
    saveDashboardWidgetLayout();
}

function hideDashboardWidget(btnOrId) {
    const widget = typeof btnOrId === 'string' ? document.querySelector('[data-widget-id="' + btnOrId + '"]') : btnOrId.closest('.widget-card');
    if (!widget) return;
    if (widget.dataset.dashboardType) return removeWidget({ closest: function () { return widget; } });
    widget.classList.add('hidden');
    saveDashboardWidgetLayout();
    updateDashboardWidgetPicker();
}

function showDashboardWidget(id) {
    const widget = document.querySelector('[data-widget-id="' + id + '"]');
    if (!widget) return;
    widget.classList.remove('hidden');
    document.getElementById('widgetContainer').appendChild(widget);
    saveDashboardWidgetLayout();
    updateDashboardWidgetPicker();
}

function resetDashboardWidgets() {
    localStorage.removeItem(DASHBOARD_WIDGET_STATE_KEY);
    localStorage.removeItem(DASHBOARD_WIDGET_LAYOUT_KEY);
    const config = window.dashboardWidgetConfig;
    if (!config || !config.saveUrl) return window.location.reload();

    fetch(config.saveUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': config.csrfToken || '',
        },
        body: JSON.stringify({ widgets: [] }),
    }).finally(function () {
        window.location.reload();
    });
}

function resizeWidget(btn) {
    const w = btn.closest('.widget-card');
    if (w.classList.contains('col-span-1')) {
        w.classList.replace('col-span-1', 'md:col-span-2');
        w.classList.add('lg:col-span-2');
    } else if (w.classList.contains('lg:col-span-2')) {
        w.classList.replace('lg:col-span-2', 'lg:col-span-3');
    } else if (w.classList.contains('lg:col-span-3')) {
        w.classList.replace('md:col-span-2', 'md:col-span-full');
        w.classList.replace('lg:col-span-3', 'lg:col-span-4');
    } else {
        w.className = w.className.replace(/md:col-span-\w+|lg:col-span-\w+/g, '');
        w.classList.add('col-span-1');
    }
    saveDashboardWidgetLayout();
}

function getDashboardWidgetCards() {
    const container = document.getElementById('widgetContainer');
    return container ? Array.from(container.querySelectorAll('.widget-card[data-widget-id]')) : [];
}

function saveDashboardWidgetLayout() {
    const container = document.getElementById('widgetContainer');
    if (!container) return;

    const widgets = getDashboardWidgetCards();
    const layout = widgets.map(function (widget) {
        return {
            id: widget.dataset.widgetId,
            classes: Array.from(widget.classList).filter(function (className) {
                return className === 'col-span-1' || className.startsWith('md:col-span-') || className.startsWith('lg:col-span-');
            }),
        };
    });

    localStorage.setItem(DASHBOARD_WIDGET_LAYOUT_KEY, JSON.stringify(layout));
    localStorage.setItem(DASHBOARD_WIDGET_STATE_KEY, JSON.stringify({
        layout: layout,
        hidden: widgets.filter(function (widget) { return widget.classList.contains('hidden') && !widget.dataset.dashboardType; }).map(function (widget) { return widget.dataset.widgetId; }),
        custom: widgets.filter(function (widget) { return widget.dataset.dashboardType; }).map(getDashboardAddonData),
    }));

    scheduleDashboardWidgetsSave();
}

function applyDashboardWidgetLayout() {
    const container = document.getElementById('widgetContainer');
    if (!container) return;

    const state = getDashboardState();
    (state.custom || []).forEach(function (item) {
        if (!item || !item.id || document.querySelector('[data-widget-id="' + item.id + '"]')) return;
        addWidget(item.type || 'note', item.data || {}, item.id);
    });

    let layout = Array.isArray(state.layout) ? state.layout : [];
    if (layout.length === 0) {
        try {
            layout = JSON.parse(localStorage.getItem(DASHBOARD_WIDGET_LAYOUT_KEY) || '[]');
        } catch (e) {
            layout = [];
        }
    }

    if (Array.isArray(state.hidden)) {
        state.hidden.forEach(function (id) {
            const widget = document.querySelector('[data-widget-id="' + id + '"]');
            if (widget) widget.classList.add('hidden');
        });
    }

    if (!Array.isArray(layout) || layout.length === 0) {
        updateDashboardWidgetPicker();
        return;
    }

    const widgetMap = new Map();
    getDashboardWidgetCards().forEach(function (widget) {
        widgetMap.set(widget.dataset.widgetId, widget);
    });

    layout.forEach(function (item) {
        const widget = widgetMap.get(item.id);
        if (!widget) return;

        widget.className = widget.className.replace(/\bcol-span-\d+\b|\bmd:col-span-\S+\b|\blg:col-span-\S+\b/g, '').replace(/\s+/g, ' ').trim();

        if (Array.isArray(item.classes) && item.classes.length > 0) {
            widget.classList.add(...item.classes);
        }

        container.appendChild(widget);
    });
    updateDashboardWidgetPicker();
}

function getDashboardAddonWidgetHTML(type, id, data) {
    const controls = '<div class="absolute right-3 top-3 z-20 flex gap-1.5 opacity-0 transition-opacity group-hover:opacity-100">' +
        '<button type="button" class="widget-drag-handle flex h-7 w-7 cursor-grab items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-indigo-600" title="Geser widget"><i class="fas fa-grip-vertical text-xs"></i></button>' +
        '<button type="button" onclick="resizeWidget(this)" class="flex h-7 w-7 items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-blue-600" title="Perbesar/perkecil"><i class="fas fa-expand-alt text-xs"></i></button>' +
        '<button type="button" onclick="removeWidget(this)" class="dashboard-remove-widget flex h-7 w-7 items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-red-500" title="Hapus widget"><i class="fas fa-times text-xs"></i></button>' +
    '</div>';

    if (type === 'clock') {
        return '<div class="widget-card relative col-span-1 overflow-hidden rounded-2xl border border-indigo-100 bg-gradient-to-br from-slate-900 to-indigo-700 p-5 text-white shadow-sm group" data-dashboard-type="clock" data-widget-id="' + id + '">' + controls +
            '<p class="text-[11px] font-black uppercase tracking-wider text-indigo-100">Jam Sekarang</p>' +
            '<p class="dashboard-clock mt-4 text-4xl font-black tracking-tight">--:--</p>' +
            '<p class="dashboard-date mt-1 text-xs font-semibold text-indigo-100"></p>' +
        '</div>';
    }

    if (type === 'todo') {
        const items = Array.isArray(data.items) ? data.items : [];
        const title = data.title || 'To-do List';
        return '<div class="widget-card relative col-span-1 rounded-2xl border border-gray-200 bg-white p-5 shadow-sm group md:col-span-2 lg:col-span-2" data-dashboard-type="todo" data-widget-id="' + id + '">' + controls +
            '<input type="text" class="dashboard-widget-title w-full rounded-xl border border-transparent bg-transparent p-0 pr-24 text-[11px] font-black uppercase tracking-wider text-emerald-600 focus:border-emerald-200 focus:bg-white focus:px-2 focus:py-1 focus:ring-emerald-500" value="' + dashboardEscape(title) + '" aria-label="Judul todo list">' +
            '<div class="mt-4 flex gap-2"><input type="text" class="dashboard-todo-input min-w-0 flex-1 rounded-xl border border-gray-300 px-3 py-2 text-xs focus:border-emerald-500 focus:ring-emerald-500" placeholder="Tambah tugas..."><button type="button" onclick="addDashboardTodoItem(this)" class="rounded-xl bg-emerald-600 px-3 py-2 text-xs font-extrabold text-white hover:bg-emerald-700">Tambah</button></div>' +
            '<div class="dashboard-todo-list mt-3 space-y-2">' + items.map(renderDashboardTodoItem).join('') + '</div>' +
        '</div>';
    }

    if (type === 'note') {
        return '<div class="widget-card relative col-span-1 rounded-2xl border border-amber-200 bg-amber-50 p-5 shadow-sm group md:col-span-2 lg:col-span-2" data-dashboard-type="note" data-widget-id="' + id + '">' + controls +
            '<p class="text-[11px] font-black uppercase tracking-wider text-amber-700">Sticky Note</p>' +
            '<textarea class="dashboard-note mt-4 h-40 w-full resize-none rounded-xl border border-amber-200 bg-white/70 p-3 text-sm font-semibold text-amber-950 focus:border-amber-500 focus:ring-amber-500" placeholder="Tulis catatan...">' + dashboardEscape(data.text) + '</textarea>' +
        '</div>';
    }

    return '<div class="widget-card relative col-span-1 flex min-h-[260px] flex-col items-center justify-center rounded-2xl border border-dashed border-gray-300 bg-white p-4 text-center shadow-sm group" data-dashboard-type="image" data-widget-id="' + id + '">' + controls +
        '<img class="widget-preview ' + (data.src ? '' : 'hidden ') + 'max-h-80 w-full rounded-xl object-contain shadow-sm" src="' + dashboardEscape(data.src) + '" alt="Preview">' +
        '<div class="empty-state ' + (data.src ? 'hidden ' : '') + 'flex h-full w-full flex-col items-center justify-center p-6">' +
            '<div class="mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-blue-50 text-blue-600"><i class="fas fa-image text-xl"></i></div>' +
            '<h3 class="mb-1 font-bold text-gray-700">Card Gambar</h3>' +
            '<label class="mt-2 cursor-pointer rounded-lg border border-gray-200 bg-gray-100 px-4 py-2 text-sm font-medium text-gray-700 hover:bg-blue-600 hover:text-white">Pilih Gambar <input type="file" class="hidden" accept="image/*" onchange="previewLocalImage(event, this)"></label>' +
        '</div>' +
    '</div>';
}

function renderDashboardTodoItem(item) {
    return '<label class="dashboard-todo-item flex items-center gap-2 rounded-xl bg-gray-50 px-3 py-2 text-xs font-semibold text-gray-700"><input type="checkbox" class="dashboard-todo-check rounded border-gray-300 text-emerald-600" ' + (item.done ? 'checked' : '') + '><span class="min-w-0 flex-1 truncate ' + (item.done ? 'line-through text-gray-400' : '') + '">' + dashboardEscape(item.text) + '</span><button type="button" onclick="removeDashboardTodoItem(this)" class="text-gray-300 hover:text-red-500"><i class="fas fa-times"></i></button></label>';
}

function addDashboardTodoItem(btn) {
    const widget = btn.closest('.widget-card');
    const input = widget.querySelector('.dashboard-todo-input');
    const text = input.value.trim();
    if (!text) return;
    widget.querySelector('.dashboard-todo-list').insertAdjacentHTML('beforeend', renderDashboardTodoItem({ text: text, done: false }));
    input.value = '';
    initializeDashboardAddonInputs(widget);
    saveDashboardWidgetLayout();
}

function removeDashboardTodoItem(btn) {
    btn.closest('.dashboard-todo-item').remove();
    saveDashboardWidgetLayout();
}

function getDashboardAddonData(widget) {
    const type = widget.dataset.dashboardType;
    const data = {};
    if (type === 'note') data.text = widget.querySelector('.dashboard-note')?.value || '';
    if (type === 'image') data.src = widget.querySelector('.widget-preview')?.getAttribute('src') || '';
    if (type === 'todo') {
        data.title = widget.querySelector('.dashboard-widget-title')?.value || 'To-do List';
        data.items = Array.from(widget.querySelectorAll('.dashboard-todo-item')).map(function (item) {
            return { text: item.querySelector('span')?.textContent || '', done: !!item.querySelector('input')?.checked };
        });
    }
    return { id: widget.dataset.widgetId, type: type, data: data };
}

function initializeDashboardAddonInputs(widget) {
    if (!widget) return;
    widget.querySelectorAll('.dashboard-note, .dashboard-todo-check, .dashboard-widget-title').forEach(function (input) {
        if (input.dataset.dashboardSaveInitialized === '1') return;
        input.dataset.dashboardSaveInitialized = '1';
        input.addEventListener('input', saveDashboardWidgetLayout);
        input.addEventListener('change', function () {
            const item = input.closest('.dashboard-todo-item');
            if (item) item.querySelector('span').classList.toggle('line-through', input.checked);
            if (item) item.querySelector('span').classList.toggle('text-gray-400', input.checked);
            saveDashboardWidgetLayout();
        });
    });
}

function updateDashboardClocks() {
    const now = new Date();
    document.querySelectorAll('.dashboard-clock').forEach(function (clock) {
        clock.textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        const date = clock.parentElement.querySelector('.dashboard-date');
        if (date) date.textContent = now.toLocaleDateString('id-ID', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    });
}

function updateDashboardWidgetPicker() {
    const picker = document.getElementById('dashboardWidgetPicker');
    if (!picker) return;
    const current = picker.value;
    picker.innerHTML = '<option value="clock">Jam</option><option value="todo">To-do List</option><option value="note">Sticky Note</option><option value="image">Card Gambar</option>';
    Object.keys(DASHBOARD_STANDARD_WIDGETS).forEach(function (id) {
        const widget = document.querySelector('[data-widget-id="' + id + '"]');
        if (!widget || !widget.classList.contains('hidden')) return;
        const option = document.createElement('option');
        option.value = 'show:' + id;
        option.textContent = 'Tampilkan: ' + DASHBOARD_STANDARD_WIDGETS[id];
        picker.appendChild(option);
    });
    if (Array.from(picker.options).some(function (option) { return option.value === current; })) picker.value = current;
}

function getDashboardWidgetClasses(widget) {
    return Array.from(widget.classList).filter(function (className) {
        return className === 'col-span-1' || className.startsWith('md:col-span-') || className.startsWith('lg:col-span-');
    });
}

function getDashboardWidgetTitle(widget) {
    if (widget.dataset.dashboardType === 'todo') return widget.querySelector('.dashboard-widget-title')?.value || 'To-do List';
    return DASHBOARD_STANDARD_WIDGETS[widget.dataset.widgetId] || widget.dataset.dashboardType || 'Widget';
}

function buildDashboardWidgetsPayload() {
    return getDashboardWidgetCards().map(function (widget, index) {
        const addon = widget.dataset.dashboardType ? getDashboardAddonData(widget) : null;
        return {
            widget_key: widget.dataset.widgetId,
            widget_type: widget.dataset.dashboardType || 'system',
            title: getDashboardWidgetTitle(widget),
            settings: Object.assign({}, addon ? addon.data : {}, { classes: getDashboardWidgetClasses(widget) }),
            sort_order: index,
            is_hidden: widget.classList.contains('hidden'),
        };
    });
}

function scheduleDashboardWidgetsSave() {
    const config = window.dashboardWidgetConfig;
    if (!config || !config.saveUrl || !dashboardWidgetsLoadedFromServer || dashboardWidgetsApplyingState) return;
    clearTimeout(dashboardWidgetsSaveTimer);
    dashboardWidgetsSaveTimer = setTimeout(saveDashboardWidgetsToServer, 500);
}

function saveDashboardWidgetsToServer() {
    const config = window.dashboardWidgetConfig;
    if (!config || !config.saveUrl) return;

    fetch(config.saveUrl, {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': config.csrfToken || '',
        },
        body: JSON.stringify({ widgets: buildDashboardWidgetsPayload() }),
    }).catch(function () {});
}

function applyDashboardWidgetsFromServer(widgets) {
    const container = document.getElementById('widgetContainer');
    if (!container || !Array.isArray(widgets)) return;

    dashboardWidgetsApplyingState = true;
    getDashboardWidgetCards().forEach(function (widget) {
        if (widget.dataset.dashboardType) widget.remove();
        else widget.classList.remove('hidden');
    });

    widgets.forEach(function (item) {
        if (!item || !item.widget_key || item.widget_type === 'system') return;
        const data = item.settings || {};
        data.title = item.title || data.title;
        addWidget(item.widget_type || 'note', data, item.widget_key);
    });

    const widgetMap = new Map();
    getDashboardWidgetCards().forEach(function (widget) {
        widgetMap.set(widget.dataset.widgetId, widget);
    });

    widgets.forEach(function (item) {
        const widget = widgetMap.get(item.widget_key);
        if (!widget) return;
        const settings = item.settings || {};
        widget.className = widget.className.replace(/\bcol-span-\d+\b|\bmd:col-span-\S+\b|\blg:col-span-\S+\b/g, '').replace(/\s+/g, ' ').trim();
        if (Array.isArray(settings.classes) && settings.classes.length > 0) widget.classList.add(...settings.classes);
        widget.classList.toggle('hidden', !!item.is_hidden);
        container.appendChild(widget);
    });

    initializeDashboardWidgets();
    updateDashboardWidgetPicker();
    localStorage.setItem(DASHBOARD_WIDGET_STATE_KEY, JSON.stringify({
        layout: widgets.map(function (item) { return { id: item.widget_key, classes: (item.settings || {}).classes || [] }; }),
        hidden: widgets.filter(function (item) { return item.is_hidden && item.widget_type === 'system'; }).map(function (item) { return item.widget_key; }),
        custom: getDashboardWidgetCards().filter(function (widget) { return widget.dataset.dashboardType; }).map(getDashboardAddonData),
    }));
    dashboardWidgetsApplyingState = false;
}

function loadDashboardWidgetsFromServer() {
    const config = window.dashboardWidgetConfig;
    if (!config || !config.fetchUrl) {
        dashboardWidgetsLoadedFromServer = true;
        return Promise.resolve();
    }

    return fetch(config.fetchUrl, { headers: { 'Accept': 'application/json' } })
        .then(function (response) { return response.ok ? response.json() : null; })
        .then(function (payload) {
            const widgets = payload && Array.isArray(payload.widgets) ? payload.widgets : [];
            if (widgets.length > 0) {
                applyDashboardWidgetsFromServer(widgets);
            }
            dashboardWidgetsLoadedFromServer = true;
            if (widgets.length === 0 && localStorage.getItem(DASHBOARD_WIDGET_STATE_KEY)) saveDashboardWidgetLayout();
        })
        .catch(function () {
            dashboardWidgetsLoadedFromServer = true;
        });
}

function getDragAfterElement(container, y) {
    const draggableElements = Array.from(container.querySelectorAll('.widget-card[data-widget-id]:not(.dragging)'));

    return draggableElements.reduce(function (closest, child) {
        const box = child.getBoundingClientRect();
        const offset = y - box.top - box.height / 2;

        if (offset < 0 && offset > closest.offset) {
            return { offset: offset, element: child };
        }

        return closest;
    }, { offset: Number.NEGATIVE_INFINITY, element: null }).element;
}

function initializeDashboardWidgets() {
    const container = document.getElementById('widgetContainer');
    if (!container) return;

    getDashboardWidgetCards().forEach(function (widget) {
        const controls = widget.querySelector('.widget-drag-handle')?.parentElement;
        if (controls && !controls.querySelector('.dashboard-remove-widget')) {
            controls.insertAdjacentHTML('beforeend', '<button type="button" onclick="hideDashboardWidget(this)" class="dashboard-remove-widget flex h-7 w-7 items-center justify-center rounded-lg bg-gray-800/70 text-white shadow-sm hover:bg-red-500" title="Sembunyikan widget"><i class="fas fa-times text-xs"></i></button>');
        }
        initializeDashboardAddonInputs(widget);

        if (widget.dataset.dragInitialized === '1') return;

        const handle = widget.querySelector('.widget-drag-handle');
        if (!handle) return;

        widget.draggable = true;
        widget.dataset.dragInitialized = '1';

        handle.addEventListener('mousedown', function () {
            widget.dataset.dragHandleActive = '1';
        });

        handle.addEventListener('mouseup', function () {
            widget.dataset.dragHandleActive = '0';
        });

        widget.addEventListener('dragstart', function (event) {
            if (widget.dataset.dragHandleActive !== '1') {
                event.preventDefault();
                return;
            }

            widget.classList.add('dragging', 'opacity-50');
            event.dataTransfer.effectAllowed = 'move';
        });

        widget.addEventListener('dragend', function () {
            widget.classList.remove('dragging', 'opacity-50');
            widget.dataset.dragHandleActive = '0';
            saveDashboardWidgetLayout();
        });
    });

    if (container.dataset.dragInitialized === '1') return;
    container.dataset.dragInitialized = '1';

    container.addEventListener('dragover', function (event) {
        event.preventDefault();

        const dragging = container.querySelector('.widget-card.dragging');
        if (!dragging) return;

        const afterElement = getDragAfterElement(container, event.clientY);
        if (afterElement == null) {
            container.appendChild(dragging);
        } else {
            container.insertBefore(dragging, afterElement);
        }
    });
}

function previewLocalImage(event, input) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = function(e) {
        const w = input.closest('.widget-card');
        w.querySelector('.widget-preview').src = e.target.result;
        w.querySelector('.empty-state').classList.add('hidden');
        w.querySelector('.widget-preview').classList.remove('hidden');
        w.classList.remove('border-dashed', 'min-h-[350px]');
        saveDashboardWidgetLayout();
    };
    reader.readAsDataURL(file);
}

/**
 * 3. GLOBAL LISTENERS
 */
window.addEventListener('load', function () {
    const loader = document.getElementById('global-loader');
    if (loader) {
        loader.style.opacity = '0';
        setTimeout(() => { loader.style.display = 'none'; }, 300);
    }

    applyDashboardWidgetLayout();
    initializeDashboardWidgets();
    loadDashboardWidgetsFromServer();
    updateDashboardClocks();
    setInterval(updateDashboardClocks, 1000);
    updateDashboardWidgetPicker();
});

document.addEventListener('click', function(e) {
    const profileButton = document.getElementById('profileButton');
    const profileMenu = document.getElementById('profileMenu');
    if (profileMenu && profileButton) {
        if (!profileButton.contains(e.target) && !profileMenu.contains(e.target)) {
            profileMenu.classList.add('hidden');
        }
    }
});
