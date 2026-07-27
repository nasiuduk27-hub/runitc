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
function addWidget() {
    const widgetId = 'image-widget-' + Date.now();
    const newWidgetHTML = `
        <div class="widget-card col-span-1 bg-white rounded-2xl border border-dashed border-gray-300 p-2 relative group flex flex-col items-center justify-center text-center min-h-[350px]" data-widget-id="${widgetId}">
            <div class="absolute top-3 right-3 flex gap-1.5 z-20 opacity-0 group-hover:opacity-100 transition-opacity">
                <button type="button" class="widget-drag-handle bg-gray-800/70 text-white w-8 h-8 rounded-lg hover:bg-indigo-600 flex items-center justify-center shadow-sm cursor-grab" title="Geser widget"><i class="fas fa-grip-vertical text-sm"></i></button>
                <button onclick="resizeWidget(this)" class="bg-gray-800/70 text-white w-8 h-8 rounded-lg hover:bg-blue-600 flex items-center justify-center shadow-sm"><i class="fas fa-expand-alt text-sm"></i></button>
                <button onclick="removeWidget(this)" class="bg-gray-800/70 text-white w-8 h-8 rounded-lg hover:bg-red-500 flex items-center justify-center shadow-sm"><i class="fas fa-times text-sm"></i></button>
            </div>
            <img class="widget-preview hidden w-full h-auto object-contain rounded-xl shadow-sm z-0" src="" alt="Preview">
            <div class="empty-state flex flex-col items-center justify-center p-6 z-10 w-full h-full">
                <div class="w-14 h-14 bg-blue-50 rounded-full flex items-center justify-center text-blue-600 mb-3"><i class="fas fa-image text-xl"></i></div>
                <h3 class="font-bold text-gray-700 mb-1">Widget Baru</h3>
                <label class="cursor-pointer bg-gray-100 text-gray-700 text-sm font-medium py-2 px-4 rounded-lg hover:bg-blue-600 hover:text-white border border-gray-200 mt-2">
                    Pilih Gambar <input type="file" class="hidden" accept="image/*" onchange="previewLocalImage(event, this)">
                </label>
            </div>
        </div>`;
    document.getElementById('widgetContainer').insertAdjacentHTML('beforeend', newWidgetHTML);
    initializeDashboardWidgets();
    saveDashboardWidgetLayout();
}

function removeWidget(btn) {
    btn.closest('.widget-card').remove();
    saveDashboardWidgetLayout();
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

const DASHBOARD_WIDGET_LAYOUT_KEY = 'runitc.dashboard.widgetLayout.v1';

function getDashboardWidgetCards() {
    const container = document.getElementById('widgetContainer');
    return container ? Array.from(container.querySelectorAll('.widget-card[data-widget-id]')) : [];
}

function saveDashboardWidgetLayout() {
    const container = document.getElementById('widgetContainer');
    if (!container) return;

    const layout = getDashboardWidgetCards().map(function (widget) {
        return {
            id: widget.dataset.widgetId,
            classes: Array.from(widget.classList).filter(function (className) {
                return className === 'col-span-1' || className.startsWith('md:col-span-') || className.startsWith('lg:col-span-');
            }),
        };
    });

    localStorage.setItem(DASHBOARD_WIDGET_LAYOUT_KEY, JSON.stringify(layout));
}

function applyDashboardWidgetLayout() {
    const container = document.getElementById('widgetContainer');
    if (!container) return;

    let layout = [];
    try {
        layout = JSON.parse(localStorage.getItem(DASHBOARD_WIDGET_LAYOUT_KEY) || '[]');
    } catch (e) {
        layout = [];
    }

    if (!Array.isArray(layout) || layout.length === 0) return;

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
