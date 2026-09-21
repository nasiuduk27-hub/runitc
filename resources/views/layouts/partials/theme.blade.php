<script>
    (function () {
        try {
            const dark = false;
            document.documentElement.classList.toggle('dark', dark);
            document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        } catch (e) {}
    })();
</script>
<style>
    html.dark { --dark-bg: #101828; --dark-surface: #182230; --dark-raised: #202b3c; --dark-line: #344054; --dark-text: #f2f4f7; --dark-soft: #cdd5df; --dark-muted: #98a2b3; --dark-accent-soft: rgba(59, 130, 246, .14); }
    html.dark body,
    html.dark .bg-brand-bg,
    html.dark .bg-gray-50,
    html.dark .bg-gray-100 { background-color: var(--dark-bg) !important; }
    html.dark .bg-white,
    html.dark .bg-gray-50\/80 { background-color: var(--dark-surface) !important; }
    html.dark .hover\:bg-gray-50:hover,
    html.dark .hover\:bg-gray-100:hover,
    html.dark .hover\:bg-blue-50:hover,
    html.dark .hover\:bg-indigo-50:hover { background-color: var(--dark-raised) !important; }
    html.dark .bg-blue-50,
    html.dark .bg-blue-50\/60,
    html.dark .bg-blue-100,
    html.dark .bg-indigo-50 { background-color: var(--dark-accent-soft) !important; }
    html.dark .text-gray-900,
    html.dark .text-gray-800,
    html.dark .text-gray-700 { color: var(--dark-text) !important; }
    html.dark .text-gray-600,
    html.dark .text-gray-500 { color: var(--dark-soft) !important; }
    html.dark .text-gray-400,
    html.dark .text-gray-300 { color: var(--dark-muted) !important; }
    html.dark .text-blue-700,
    html.dark .text-blue-600,
    html.dark .text-indigo-600 { color: #60a5fa !important; }
    html.dark .border-gray-300,
    html.dark .border-gray-200,
    html.dark .border-gray-100,
    html.dark .border-blue-200,
    html.dark .border-blue-100,
    html.dark .border-indigo-100,
    html.dark .divide-gray-100 > :not([hidden]) ~ :not([hidden]) { border-color: var(--dark-line) !important; }
    html.dark .dark-theme-toggle { background-color: var(--dark-raised) !important; border-color: var(--dark-line) !important; color: #fbbf24 !important; }
    html.dark .dark-theme-toggle:hover { background-color: #263449 !important; }
    html.dark input,
    html.dark select,
    html.dark textarea { background-color: var(--dark-bg) !important; border-color: var(--dark-line) !important; color: var(--dark-text) !important; }
    html.dark input::placeholder,
    html.dark textarea::placeholder { color: var(--dark-muted) !important; }
    html.dark .shadow-sm,
    html.dark .shadow-xl { box-shadow: 0 10px 30px rgba(0, 0, 0, .35) !important; }
    html.dark .select2-container--default .select2-selection--single,
    html.dark .select2-container--default .select2-selection--multiple,
    html.dark .select2-dropdown { background: var(--dark-surface) !important; border-color: var(--dark-line) !important; color: var(--dark-text) !important; }
    html.dark .select2-container--default .select2-results__option--highlighted[aria-selected] { background: #3b82f6 !important; }
</style>
<script>
    function toggleTheme() {
        const dark = !document.documentElement.classList.contains('dark');
        document.documentElement.classList.toggle('dark', dark);
        document.documentElement.style.colorScheme = dark ? 'dark' : 'light';
        try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
        document.querySelectorAll('[data-theme-icon]').forEach(function (icon) {
            icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
        });
    }
    document.addEventListener('DOMContentLoaded', function () {
        const dark = document.documentElement.classList.contains('dark');
        document.querySelectorAll('[data-theme-icon]').forEach(function (icon) {
            icon.className = dark ? 'fas fa-sun' : 'fas fa-moon';
        });
    });
</script>
