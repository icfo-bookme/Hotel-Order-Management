import './bootstrap';

import Alpine from 'alpinejs';

window.Alpine = Alpine;

Alpine.data('appNavigation', () => ({
    sidebarOpen: false,
    sidebarCollapsed: false,
    desktop: window.matchMedia('(min-width: 1024px)').matches,
    init() {
        try { this.sidebarCollapsed = localStorage.getItem('hotel-sidebar-collapsed') === 'true'; } catch (_) {}
        this.$watch('sidebarOpen', open => {
            document.body.classList.toggle('navigation-open', open && !this.desktop);
        });
    },
    toggleSidebar() {
        if (!this.desktop) {
            this.sidebarOpen = !this.sidebarOpen;
            if (this.sidebarOpen) this.$nextTick(() => this.$refs.sidebarClose.focus());
            return;
        }
        this.sidebarCollapsed = !this.sidebarCollapsed;
        try { localStorage.setItem('hotel-sidebar-collapsed', String(this.sidebarCollapsed)); } catch (_) {}
    },
    closeSidebar() {
        this.sidebarOpen = false;
        this.$nextTick(() => this.$refs.menuToggle.focus());
    },
    updateViewport() {
        this.desktop = window.matchMedia('(min-width: 1024px)').matches;
        this.sidebarOpen = false;
        document.body.classList.remove('navigation-open');
    },
    trapFocus(event) {
        if (this.desktop || !this.sidebarOpen) return;
        const controls = [...this.$refs.sidebar.querySelectorAll('a[href], button')].filter(el => el.getClientRects().length);
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
    },
}));

Alpine.start();
