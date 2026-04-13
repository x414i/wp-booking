/**
 * Misaha Booking System — Admin JavaScript
 * Handles: Hall CRUD, Booking deletion
 */
(function ($) {
    'use strict';

    const { ajaxUrl, nonce } = window.misahaAdmin || {};

    /* ================================================================
       HALL MANAGEMENT
    ================================================================ */

    const $form        = $('#misaha-hall-form');
    const $editId      = $('#hall-edit-id');
    const $hallName    = $('#hall-name');
    const $hallCap     = $('#hall-capacity');
    const $hallPrice   = $('#hall-price');
    const $hallStatus  = $('#hall-status');
    const $hallDesc    = $('#hall-description');
    const $cancelBtn   = $('#misaha-hall-cancel');
    const $formMsg     = $('#misaha-hall-msg');

    if ($form.length) {

        /* ── Submit / Save ─────────────────────────────────── */
        $form.on('submit', function (e) {
            e.preventDefault();

            const data = {
                action:       'misaha_admin_save_hall',
                nonce:        nonce,
                id:           $editId.val(),
                name:         $hallName.val().trim(),
                capacity:     $hallCap.val(),
                price_per_hour: $hallPrice.val(),
                status:       $hallStatus.val(),
                description:  $hallDesc.val().trim(),
            };

            if (!data.name) { showFormMsg('Hall name is required.', 'error'); return; }

            const $btn = $form.find('[type="submit"]').prop('disabled', true).text('Saving…');

            $.post(ajaxUrl, data)
            .done(function (res) {
                if (res.success) {
                    showFormMsg('✅ ' + res.data.message, 'success');
                    resetForm();
                    // Refresh the page to show updated table
                    setTimeout(function () { location.reload(); }, 900);
                } else {
                    showFormMsg('❌ ' + (res.data ? res.data.message : 'Error saving hall.'), 'error');
                }
            })
            .fail(function () { showFormMsg('❌ Network error.', 'error'); })
            .always(function () { $btn.prop('disabled', false).text('💾 Save Hall'); });
        });

        /* ── Edit Hall ─────────────────────────────────────── */
        $(document).on('click', '.misaha-edit-hall', function () {
            const $row = $('#hall-row-' + $(this).data('id'));

            $editId.val($row.data('id'));
            $hallName.val($row.data('name'));
            $hallCap.val($row.data('capacity'));
            $hallPrice.val($row.data('price'));
            $hallStatus.val($row.data('status'));
            $hallDesc.val($row.data('description'));

            $cancelBtn.show();
            $form.find('[type="submit"]').text('✏️ Update Hall');
            $formMsg.hide();

            $('html, body').animate({
                scrollTop: $form.closest('.misaha-admin-card').offset().top - 40
            }, 400);
            $hallName.focus();
        });

        /* ── Cancel Edit ───────────────────────────────────── */
        $cancelBtn.on('click', function () {
            resetForm();
        });

        function resetForm() {
            $editId.val('0');
            $form[0].reset();
            $cancelBtn.hide();
            $form.find('[type="submit"]').text('💾 Save Hall');
        }

        function showFormMsg(msg, type) {
            $formMsg.removeClass('success error').addClass(type).html(msg).fadeIn(200);
        }
    }

    /* ================================================================
       DELETE BOOKING
    ================================================================ */

    $(document).on('click', '.misaha-delete-booking', function () {
        const $btn = $(this);
        const id   = $btn.data('id');

        if (!confirm('Are you sure you want to delete booking #' + id + '?')) return;

        $btn.prop('disabled', true).text('…');

        $.post(ajaxUrl, {
            action: 'misaha_admin_delete_booking',
            nonce:  nonce,
            id:     id,
        })
        .done(function (res) {
            if (res.success) {
                $('#booking-row-' + id).fadeOut(300, function () { $(this).remove(); });
            } else {
                alert('Failed to delete booking.');
                $btn.prop('disabled', false).text('🗑');
            }
        })
        .fail(function () {
            alert('Network error.');
            $btn.prop('disabled', false).text('🗑');
        });
    });

    /* ================================================================
       PRICING — Live Preview (optional UX)
    ================================================================ */

    $('input[name^="price_"]').on('input', function () {
        // Could add a live preview here in future
    });

    /* ================================================================
       DISCOUNT CODE — Auto Uppercase
    ================================================================ */

    $('input[name="disc_code"]').on('input', function () {
        const pos = this.selectionStart;
        this.value = this.value.toUpperCase();
        this.setSelectionRange(pos, pos);
    });

    /* ================================================================
       ADMIN TABLE — Striped hover is handled by CSS.
       This block handles any future dynamic rows.
    ================================================================ */

    // Re-stripe after any table manipulation
    function restripe($table) {
        $table.find('tbody tr:visible').each(function (i) {
            $(this).toggleClass('alternate', i % 2 === 1);
        });
    }

    /* ================================================================
       NOTICES — Auto-dismiss after 5s
    ================================================================ */

    setTimeout(function () {
        $('.notice.is-dismissible').fadeOut(500);
    }, 5000);

})(jQuery);
