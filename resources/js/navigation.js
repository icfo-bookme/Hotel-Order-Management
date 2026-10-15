$(document).ready(function () {
    const $userToggle = $('#user-dropdown-toggle'), $userMenu = $('#user-dropdown-menu');
    function closeUserMenu() {
        $userMenu.prop('hidden', true).addClass('hidden');
        $userToggle.attr('aria-expanded', 'false');
    }
    $userToggle.on('click', function () {
        const open = $userToggle.attr('aria-expanded') !== 'true';
        $userMenu.prop('hidden', !open).toggleClass('hidden', !open);
        $userToggle.attr('aria-expanded', String(open));
    });
    $(document).on('click.userDropdown', function (event) {
        if (!$(event.target).closest('#user-dropdown').length) closeUserMenu();
    }).on('keydown.userDropdown', function (event) {
        if (event.key === 'Escape' && $userToggle.attr('aria-expanded') === 'true') {
            closeUserMenu(); $userToggle.trigger('focus');
        }
    }).on('focusin.userDropdown', function (event) {
        if (!$(event.target).closest('#user-dropdown').length) closeUserMenu();
    });
    const $shell = $('#app-shell'), $sidebar = $('#app-sidebar'), $toggle = $('#sidebar-toggle');
    if (!$shell.length) return;
    const desktop = () => matchMedia('(min-width: 1024px)').matches;
    let collapsed = false, open = false;
    try { collapsed = localStorage.getItem('hotel-sidebar-collapsed') === 'true'; } catch (_) {}
    function render() {
        $shell.toggleClass('sidebar-collapsed', collapsed).toggleClass('sidebar-open', open);
        $('#sidebar-backdrop').prop('hidden', !open).toggleClass('hidden', !open);
        $sidebar.prop('inert', !desktop() && !open);
        $('.app-content').prop('inert', !desktop() && open);
        $('body').toggleClass('navigation-open', !desktop() && open);
        $toggle.attr('aria-expanded', String(desktop() ? !collapsed : open));
        $toggle.attr('aria-label', desktop() ? (collapsed ? 'Expand sidebar' : 'Collapse sidebar') : 'Open navigation');
        $('[data-sidebar-group]').each(function () {
            const $group = $(this), expanded = $group.data('open') && (!desktop() || !collapsed);
            $group.find('[data-sidebar-group-toggle]').attr('aria-expanded', String(!!expanded));
            $group.find('[data-sidebar-chevron]').toggleClass('rotate-180', !!expanded);
            $group.find('[data-sidebar-submenu]').prop('inert', !expanded).attr('aria-hidden', String(!expanded))
                .toggleClass('grid-rows-[1fr] opacity-100', !!expanded).toggleClass('grid-rows-[0fr] opacity-0', !expanded);
        });
    }
    function close() { open = false; render(); $toggle.trigger('focus'); }
    $toggle.on('click', function () {
        if (desktop()) {
            collapsed = !collapsed;
            try { localStorage.setItem('hotel-sidebar-collapsed', String(collapsed)); } catch (_) {}
        } else open = !open;
        render();
        if (open) $('#sidebar-close').trigger('focus');
    });
    $('#sidebar-close, #sidebar-backdrop').on('click', close);
    $('[data-sidebar-group-toggle]').on('click', function () {
        const $group = $(this).closest('[data-sidebar-group]');
        if (desktop() && collapsed) {
            collapsed = false; $group.data('open', true);
            try { localStorage.setItem('hotel-sidebar-collapsed', 'false'); } catch (_) {}
        } else $group.data('open', !$group.data('open'));
        render();
    });
    $(document.defaultView).on('resize', function () { if (desktop()) open = false; render(); });
    $(document).on('keydown.navigation', function (event) { if (event.key === 'Escape' && open) close(); });
    $sidebar.on('keydown', function (event) {
        if (event.key !== 'Tab' || desktop() || !open) return;
        const controls = $sidebar.find('a, button').filter(':visible').filter(function () { return !$(this).closest('[inert]').length; }).toArray();
        const first = controls[0], last = controls[controls.length - 1];
        if (event.shiftKey && $(first).is(':focus')) { event.preventDefault(); $(last).trigger('focus'); }
        else if (!event.shiftKey && $(last).is(':focus')) { event.preventDefault(); $(first).trigger('focus'); }
    });
    render();
});
