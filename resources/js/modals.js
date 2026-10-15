const modalStack = [];
let previousOverflow = null;

function findModal(id) {
    return $('[data-modal]').filter(function () { return $(this).attr('data-modal') === id; });
}

const appModal = {
    open(id, trigger = null) {
        const $modal = findModal(id);
        if (!$modal.length || modalStack.includes(id)) return;
        if (!modalStack.length) {
            previousOverflow = { body: $('body').css('overflow'), html: $('html').css('overflow') };
            $('body, html').css('overflow', 'hidden');
            $('#app-shell').prop('inert', true);
        }
        if (modalStack.length) findModal(modalStack.at(-1)).prop('inert', true);
        $modal.data('trigger', trigger || $(':focus')[0]).prop('hidden', false).prop('inert', false)
            .removeClass('hidden').addClass('flex').attr('aria-hidden', 'false');
        modalStack.push(id);
        $modal.css('z-index', 60 + modalStack.length);
        const $focus = $modal.find('input:not([type="hidden"]), select, textarea, button').filter(':visible:not(:disabled)').first();
        ($focus.length ? $focus : $modal).trigger('focus');
        $modal.trigger('modal:opened');
    },
    close(id) {
        const $modal = findModal(id);
        if (!$modal.length || $modal.data('busy') || modalStack.at(-1) !== id) return;
        $modal.prop('hidden', true).addClass('hidden').removeClass('flex').attr('aria-hidden', 'true');
        modalStack.pop();
        if (modalStack.length) {
            findModal(modalStack.at(-1)).prop('inert', false);
        } else {
            if (previousOverflow) {
                $('body').css('overflow', previousOverflow.body);
                $('html').css('overflow', previousOverflow.html);
                previousOverflow = null;
            }
            $('#app-shell').prop('inert', false);
        }
        const trigger = $modal.data('trigger');
        if (trigger && $(trigger).is(':visible')) $(trigger).trigger('focus');
        $modal.trigger('modal:closed');
    },
    busy(id, busy) {
        const $modal = findModal(id);
        $modal.data('busy', busy);
        $modal.find('[data-close-modal]').prop('disabled', busy);
        $modal.find('[data-submit-btn]').each(function () {
            const $button = $(this);
            if (!$button.data('original-label')) $button.data('original-label', $button.text());
            $button.prop('disabled', busy).text(busy ? $button.attr('data-saving-text') || 'Saving...' : $button.data('original-label'));
        });
    },
};

$.appModal = appModal;

$(document).ready(function () {
    $(document).on('click', '[data-open-modal], [data-modal-target]', function (event) {
        event.preventDefault();
        appModal.open($(this).attr('data-open-modal') || $(this).attr('data-modal-target'), this);
    }).on('click', '[data-close-modal]', function (event) {
        event.preventDefault();
        appModal.close($(this).closest('[data-modal]').attr('data-modal'));
    });
    $('[data-modal]').on('click', function (event) {
        if ($(event.target).is(this)) appModal.close($(this).attr('data-modal'));
    }).on('keydown', function (event) {
        if ($(this).attr('data-modal') !== modalStack.at(-1)) return;
        if (event.key === 'Escape') { event.stopPropagation(); appModal.close(modalStack.at(-1)); }
        if (event.key === 'Tab') {
            const $controls = $(this).find('a[href], button, input:not([type="hidden"]), select, textarea, [tabindex]').filter(':visible:not(:disabled):not([tabindex="-1"])');
            if (!$controls.length) { event.preventDefault(); $(this).trigger('focus'); }
            else if (event.shiftKey && $controls.first().is(':focus')) { event.preventDefault(); $controls.last().trigger('focus'); }
            else if (!event.shiftKey && $controls.last().is(':focus')) { event.preventDefault(); $controls.first().trigger('focus'); }
        }
    });
    $('[data-modal][data-show="true"]').each(function () { appModal.open($(this).attr('data-modal')); });
    $('[data-auto-dismiss]').each(function () { const $notice = $(this); setTimeout(function () { $notice.fadeOut(150); }, Number($notice.attr('data-auto-dismiss'))); });
});
