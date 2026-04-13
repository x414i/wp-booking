/**
 * Misaha Booking System — Frontend JavaScript
 * Handles: Hall Booking Calendar, Seat Map, User Dashboard tabs
 * Supports: Arabic (RTL) via misahaVars.i18n translations
 */
(function ($) {
    'use strict';

    const { ajaxUrl, nonce, currency, isLoggedIn, loginUrl, isRtl, locale, i18n } = window.misahaVars || {};

    /* ================================================================
       TRANSLATION HELPER
    ================================================================ */

    /**
     * Translate a string using the i18n object passed from PHP.
     * Falls back to the English key if no translation found.
     */
    function t(key) {
        return (i18n && i18n[key]) ? i18n[key] : key;
    }

    /**
     * Get locale string for date formatting.
     * Maps ar_* → 'ar-LY' etc.
     */
    function getDateLocale() {
        if (locale && locale.indexOf('ar') === 0) return 'ar-LY';
        return 'en-GB';
    }

    /* ================================================================
       UTILITY HELPERS
    ================================================================ */

    function fmt(amount) {
        return currency + ' ' + parseFloat(amount).toFixed(2);
    }

    function showMsg($el, msg, type) {
        $el.removeClass('success error').addClass(type).html(msg).fadeIn(200);
    }

    function showResult($el, msg, type) {
        $el.removeClass('success error').addClass(type).html(msg).slideDown(250);
        $el.show();
    }

    function showLoading($el) {
        $el.show();
    }

    function hideLoading($el) {
        $el.hide();
    }

    function goToStep($wrap, fromPanel, toPanel, fromStep, toStep) {
        $wrap.find('#misaha-step-' + fromPanel).slideUp(220, function () {
            $wrap.find('#misaha-step-' + toPanel).slideDown(260);
        });
        $wrap.find('.misaha-step[data-step="' + fromStep + '"]').removeClass('active').addClass('done');
        $wrap.find('.misaha-step[data-step="' + toStep + '"]').addClass('active');
        $('html, body').animate({ scrollTop: $wrap.offset().top - 30 }, 350);
    }

    function goToSeatStep($wrap, fromPanel, toPanel, fromStep, toStep) {
        $wrap.find('#misaha-sm-step-' + fromPanel).slideUp(220, function () {
            $wrap.find('#misaha-sm-step-' + toPanel).slideDown(260);
        });
        $wrap.find('.misaha-step[data-step="' + fromStep + '"]').removeClass('active').addClass('done');
        $wrap.find('.misaha-step[data-step="' + toStep + '"]').addClass('active');
        $('html, body').animate({ scrollTop: $wrap.offset().top - 30 }, 350);
    }

    /* ================================================================
       BOOKING CALENDAR MODULE
    ================================================================ */

    (function initBookingCalendar() {
        const $wrap = $('#misaha-booking-calendar-wrap');
        if (!$wrap.length) return;

        let selectedHallId   = 0;
        let selectedHallName = '';
        let selectedHallPrice = 0;
        let selectedDate     = '';
        let selectedSlot     = null;
        let discountData     = null;

        /* ── Hall Selection ─────────────────────────────────── */
        $wrap.on('click', '.misaha-select-hall', function () {
            const $btn = $(this);
            selectedHallId    = $btn.data('hall-id');
            selectedHallName  = $btn.data('hall-name');
            selectedHallPrice = parseFloat($btn.data('hall-price'));

            $wrap.find('.misaha-hall-card').removeClass('selected');
            $btn.closest('.misaha-hall-card').addClass('selected');
            $wrap.find('#misaha-booking-date').val('');
            selectedSlot = null;
            $('#misaha-date-picker').slideDown(200);
        });

        /* ── Load Slots ─────────────────────────────────────── */
        $wrap.on('click', '#misaha-load-slots', function () {
            selectedDate = $wrap.find('#misaha-booking-date').val();

            if (!selectedHallId) {
                alert(t('Please select a hall first.'));
                return;
            }
            if (!selectedDate) {
                alert(t('Please pick a date.'));
                return;
            }

            goToStep($wrap, 1, 2, 1, 2);
            renderSlotInfo();
            fetchSlots();
        });

        function renderSlotInfo() {
            const dateObj = new Date(selectedDate + 'T00:00:00');
            const dateStr = dateObj.toLocaleDateString(getDateLocale(), { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            $('#misaha-selected-info').html(
                '🏛️ <strong>' + selectedHallName + '</strong> &nbsp;|&nbsp; 📅 <strong>' + dateStr + '</strong>'
            );
        }

        function fetchSlots() {
            const $grid    = $wrap.find('#misaha-slots-grid').empty();
            const $loading = $wrap.find('#misaha-slots-loading');
            showLoading($loading);
            selectedSlot = null;
            $wrap.find('#misaha-proceed-confirm').hide();

            $.post(ajaxUrl, {
                action:  'misaha_get_slots',
                nonce:   nonce,
                hall_id: selectedHallId,
                date:    selectedDate,
            })
            .done(function (res) {
                hideLoading($loading);
                if (!res.success || !res.data.length) {
                    $grid.html('<p style="grid-column:1/-1;text-align:center;color:#64748b;">' + t('No slots available for this date.') + '</p>');
                    return;
                }
                res.data.forEach(function (slot) {
                    const isBooked = slot.status === 'booked';
                    const $slot = $('<div>', {
                        class: 'misaha-slot' + (isBooked ? ' booked' : ''),
                        'data-start': slot.start,
                        'data-end':   slot.end,
                        'data-label': slot.label,
                        'data-price': slot.price,
                    }).html(
                        '<span class="misaha-slot-time">' + slot.label + '</span>' +
                        '<span class="misaha-slot-price">' + fmt(slot.price) + '</span>' +
                        '<span class="misaha-slot-tag">' + (isBooked ? t('Booked') : t('Available')) + '</span>'
                    );
                    $grid.append($slot);
                });
            })
            .fail(function () {
                hideLoading($loading);
                $grid.html('<p style="color:#ef4444;">' + t('Failed to load slots. Please try again.') + '</p>');
            });
        }

        /* ── Slot Click ─────────────────────────────────────── */
        $wrap.on('click', '.misaha-slot:not(.booked)', function () {
            $wrap.find('.misaha-slot').removeClass('selected');
            $(this).addClass('selected');
            selectedSlot = {
                start: $(this).data('start'),
                end:   $(this).data('end'),
                label: $(this).data('label'),
                price: parseFloat($(this).data('price')),
            };
            $wrap.find('#misaha-proceed-confirm').fadeIn(200);
        });

        /* ── Proceed to Confirm ─────────────────────────────── */
        $wrap.on('click', '#misaha-proceed-confirm', function () {
            if (!selectedSlot) return;
            discountData = null;
            $wrap.find('#misaha-discount-code').val('');
            $wrap.find('#misaha-discount-msg').text('').removeClass('success error');
            renderPriceBreakdown(selectedSlot.price, 0);
            renderSummary();
            goToStep($wrap, 2, 3, 2, 3);
        });

        function renderSummary() {
            const dateObj = new Date(selectedDate + 'T00:00:00');
            const dateStr = dateObj.toLocaleDateString(getDateLocale(), { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
            $('#misaha-booking-summary').html(
                '<h3>📋 ' + t('Booking Summary') + '</h3>' +
                '<div class="misaha-summary-row"><span class="label">' + t('Hall') + '</span><span class="value">' + selectedHallName + '</span></div>' +
                '<div class="misaha-summary-row"><span class="label">' + t('Date') + '</span><span class="value">' + dateStr + '</span></div>' +
                '<div class="misaha-summary-row"><span class="label">' + t('Time Slot') + '</span><span class="value">' + selectedSlot.label + '</span></div>' +
                '<div class="misaha-summary-row"><span class="label">' + t('Duration') + '</span><span class="value">' + t('1 hour') + '</span></div>'
            );
        }

        function renderPriceBreakdown(basePrice, discAmount) {
            const disc     = discAmount || 0;
            const finalAmt = Math.max(0, basePrice - disc);
            const discLine = disc > 0
                ? '<div class="misaha-price-line discount"><span>' + t('Discount') + '</span><span>− ' + fmt(disc) + '</span></div>'
                : '';
            $('#misaha-price-breakdown').html(
                '<div class="misaha-price-line"><span>' + t('Base Price') + '</span><span>' + fmt(basePrice) + '</span></div>' +
                discLine +
                '<div class="misaha-price-line total"><span>' + t('Total') + '</span><span>' + fmt(finalAmt) + '</span></div>'
            );
        }

        /* ── Discount ───────────────────────────────────────── */
        $wrap.on('click', '#misaha-apply-discount', function () {
            const code = $wrap.find('#misaha-discount-code').val().trim().toUpperCase();
            const $msg = $wrap.find('#misaha-discount-msg');

            if (!code) {
                showMsg($msg, t('Please enter a discount code.'), 'error');
                return;
            }

            $(this).prop('disabled', true).text(t('Checking…'));

            $.post(ajaxUrl, {
                action: 'misaha_validate_discount',
                nonce:  nonce,
                code:   code,
                type:   'hall',
                price:  selectedSlot.price,
            })
            .done(function (res) {
                if (res.success && res.data.valid) {
                    discountData = res.data;
                    showMsg($msg, '✅ ' + res.data.message, 'success');
                    renderPriceBreakdown(selectedSlot.price, discountData.discount_amount);
                } else {
                    discountData = null;
                    showMsg($msg, '❌ ' + (res.data ? res.data.message : t('Invalid code.')), 'error');
                    renderPriceBreakdown(selectedSlot.price, 0);
                }
            })
            .fail(function () {
                showMsg($msg, '❌ ' + t('Network error.'), 'error');
            })
            .always(function () {
                $wrap.find('#misaha-apply-discount').prop('disabled', false).text(t('Apply'));
            });
        });

        /* ── Pay Now ────────────────────────────────────────── */
        $wrap.on('click', '#misaha-pay-now', function () {
            if (!isLoggedIn) {
                window.location.href = loginUrl;
                return;
            }
            const $btn    = $(this).prop('disabled', true).text(t('Processing…'));
            const $result = $wrap.find('#misaha-booking-result').hide().removeClass('success error');
            const code    = $wrap.find('#misaha-discount-code').val().trim().toUpperCase();

            $.post(ajaxUrl, {
                action:        'misaha_create_booking',
                nonce:         nonce,
                hall_id:       selectedHallId,
                date:          selectedDate,
                slot:          selectedSlot.start + '-' + selectedSlot.end,
                discount_code: code,
            })
            .done(function (res) {
                if (res.success) {
                    const d = res.data;
                    if (d.payment_url) {
                        showResult($result,
                            '✅ ' + t('Booking') + ' <strong>#' + d.booking_id + '</strong> ' + t('created! Redirecting to payment…'),
                            'success'
                        );
                        setTimeout(function () {
                            window.location.href = d.payment_url;
                        }, 1800);
                    } else {
                        showResult($result,
                            '✅ ' + t('Booking') + ' <strong>#' + d.booking_id + '</strong> ' + t('created! Total:') + ' <strong>' + fmt(d.final_price) + '</strong>. ' + t('Our team will contact you for payment.'),
                            'success'
                        );
                        $btn.text(t('Booked!'));
                    }
                } else {
                    showResult($result, '❌ ' + res.data.message, 'error');
                    $btn.prop('disabled', false).text('💳 ' + t('Proceed to Payment'));
                }
            })
            .fail(function () {
                showResult($result, '❌ ' + t('Network error. Please try again.'), 'error');
                $btn.prop('disabled', false).text('💳 ' + t('Proceed to Payment'));
            });
        });

        /* ── Back buttons ───────────────────────────────────── */
        $wrap.on('click', '.misaha-back', function () {
            const to = $(this).data('to');
            if (to === 1) {
                $wrap.find('#misaha-step-2').slideUp(200, function () {
                    $wrap.find('#misaha-step-1').slideDown(240);
                });
                $wrap.find('.misaha-step[data-step="2"]').removeClass('active done');
                $wrap.find('.misaha-step[data-step="1"]').removeClass('done').addClass('active');
            } else if (to === 2) {
                $wrap.find('#misaha-step-3').slideUp(200, function () {
                    $wrap.find('#misaha-step-2').slideDown(240);
                });
                $wrap.find('.misaha-step[data-step="3"]').removeClass('active done');
                $wrap.find('.misaha-step[data-step="2"]').removeClass('done').addClass('active');
            }
        });
    })();

    /* ================================================================
       SEAT MAP MODULE
    ================================================================ */

    (function initSeatMap() {
        const $wrap = $('#misaha-seat-map-wrap');
        if (!$wrap.length) return;

        let selectedHallId   = 0;
        let selectedHallName = '';
        let selectedSeatId   = 0;
        let selectedSeatNum  = '';
        let selectedPassType = '';
        let selectedPassPrice = 0;
        let discountData     = null;

        /* ── Hall Select ─────────────────────────────────────── */
        $wrap.on('click', '.misaha-sm-select-hall', function () {
            const $btn = $(this);
            selectedHallId   = $btn.data('hall-id');
            selectedHallName = $btn.data('hall-name');

            $wrap.find('#misaha-hall-banner').html('🏛️ <strong>' + selectedHallName + '</strong> — ' + t('click a seat to select it'));
            goToSeatStep($wrap, 1, 2, 1, 2);
            fetchSeats();
        });

        /* ── Fetch + Render Seat Map ─────────────────────────── */
        function fetchSeats() {
            const $grid    = $wrap.find('#misaha-seat-grid').empty();
            const $loading = $wrap.find('#misaha-sm-loading');
            showLoading($loading);
            selectedSeatId = 0;
            $wrap.find('#misaha-sm-to-pass').hide();

            $.post(ajaxUrl, {
                action:  'misaha_get_seats',
                nonce:   nonce,
                hall_id: selectedHallId,
            })
            .done(function (res) {
                hideLoading($loading);
                if (!res.success || !res.data.length) {
                    $grid.html('<p style="text-align:center;color:#64748b;padding:30px 0;">' + t('No seats available for this hall.') + '</p>');
                    return;
                }

                // Group by row_label
                const rows = {};
                res.data.forEach(function (seat) {
                    const row = seat.row_label || seat.seat_number.charAt(0);
                    if (!rows[row]) rows[row] = [];
                    rows[row].push(seat);
                });

                Object.keys(rows).sort().forEach(function (rowLabel) {
                    const $row = $('<div class="misaha-seat-row">');
                    $row.append('<span class="misaha-row-label">' + rowLabel + '</span>');
                    rows[rowLabel].forEach(function (seat) {
                        const isOccupied = seat.pass_status === 'occupied';
                        const $seat = $('<div>', {
                            class: 'misaha-seat' + (isOccupied ? ' seat-occupied' : ''),
                            'data-seat-id':  seat.id,
                            'data-seat-num': seat.seat_number,
                            title: seat.seat_number + (isOccupied ? ' (' + t('Occupied') + ')' : ' (' + t('Available') + ')'),
                        }).text(seat.seat_number.replace(rowLabel, ''));
                        $row.append($seat);
                    });
                    $grid.append($row);
                });
            })
            .fail(function () {
                hideLoading($loading);
                $grid.html('<p style="color:#ef4444;padding:20px;">' + t('Failed to load seats.') + '</p>');
            });
        }

        /* ── Seat Click ─────────────────────────────────────── */
        $wrap.on('click', '.misaha-seat:not(.seat-occupied)', function () {
            $wrap.find('.misaha-seat').removeClass('seat-selected');
            $(this).addClass('seat-selected');
            selectedSeatId  = $(this).data('seat-id');
            selectedSeatNum = $(this).data('seat-num');
            $wrap.find('#misaha-sm-to-pass').fadeIn(200);
        });

        /* ── Go to Pass Step ────────────────────────────────── */
        $wrap.on('click', '#misaha-sm-to-pass', function () {
            $wrap.find('#misaha-selected-seat-info').html(
                '💺 ' + t('Selected') + ': <strong>' + selectedSeatNum + '</strong> — <strong>' + selectedHallName + '</strong>'
            );
            $wrap.find('.misaha-pass-card').removeClass('selected');
            $wrap.find('.misaha-pick-pass').removeClass('misaha-btn-primary').addClass('misaha-btn-outline');
            $wrap.find('#misaha-start-date-row').hide();
            selectedPassType  = '';
            selectedPassPrice = 0;
            goToSeatStep($wrap, 2, 3, 2, 3);
        });

        /* ── Pass Type Pick ─────────────────────────────────── */
        $wrap.on('click', '.misaha-pick-pass', function () {
            const $btn = $(this);
            selectedPassType  = $btn.data('pass');
            selectedPassPrice = parseFloat($btn.data('price'));

            const today = new Date().toISOString().split('T')[0];
            $wrap.find('#misaha-pass-start').attr('min', today).val(today);

            $wrap.find('.misaha-pass-card').removeClass('selected');
            $btn.closest('.misaha-pass-card').addClass('selected');
            $wrap.find('.misaha-pick-pass').removeClass('misaha-btn-primary').addClass('misaha-btn-outline');
            $btn.removeClass('misaha-btn-outline').addClass('misaha-btn-primary');
            $wrap.find('#misaha-start-date-row').slideDown(200);

            setTimeout(function () {
                if (!$wrap.find('#misaha-pass-start').val()) return;
                goToConfirmStep();
            }, 600);
        });

        $wrap.on('change', '#misaha-pass-start', function () {
            if (selectedPassType) goToConfirmStep();
        });

        function goToConfirmStep() {
            discountData = null;
            $wrap.find('#misaha-sm-discount-code').val('');
            $wrap.find('#misaha-sm-discount-msg').text('').removeClass('success error');
            renderPassSummary();
            renderPassPriceBreakdown(selectedPassPrice, 0);
            goToSeatStep($wrap, 3, 4, 3, 4);
        }

        function renderPassSummary() {
            const startDate  = $wrap.find('#misaha-pass-start').val();
            const passLabels = { day: t('Day Pass'), week: t('Weekly Pass'), month: t('Monthly Pass') };
            const endDates   = getEndDate(startDate, selectedPassType);
            const fmtDate = function (d) {
                return new Date(d + 'T00:00:00').toLocaleDateString(getDateLocale(), { day: 'numeric', month: 'long', year: 'numeric' });
            };

            $wrap.find('#misaha-pass-summary').html(
                '<h3>📋 ' + t('Pass Summary') + '</h3>' +
                '<div class="misaha-summary-row"><span class="label">' + t('Hall') + '</span><span class="value">' + selectedHallName + '</span></div>' +
                '<div class="misaha-summary-row"><span class="label">' + t('Seat') + '</span><span class="value">' + selectedSeatNum + '</span></div>' +
                '<div class="misaha-summary-row"><span class="label">' + t('Pass Type') + '</span><span class="value">' + (passLabels[selectedPassType] || selectedPassType) + '</span></div>' +
                '<div class="misaha-summary-row"><span class="label">' + t('Start Date') + '</span><span class="value">' + fmtDate(startDate) + '</span></div>' +
                '<div class="misaha-summary-row"><span class="label">' + t('End Date') + '</span><span class="value">' + fmtDate(endDates) + '</span></div>'
            );
        }

        function getEndDate(startDate, passType) {
            const d = new Date(startDate + 'T00:00:00');
            if      (passType === 'week')  d.setDate(d.getDate() + 6);
            else if (passType === 'month') { d.setMonth(d.getMonth() + 1); d.setDate(d.getDate() - 1); }
            return d.toISOString().split('T')[0];
        }

        function renderPassPriceBreakdown(basePrice, discAmount) {
            const disc     = discAmount || 0;
            const finalAmt = Math.max(0, basePrice - disc);
            const discLine = disc > 0
                ? '<div class="misaha-price-line discount"><span>' + t('Discount') + '</span><span>− ' + fmt(disc) + '</span></div>'
                : '';
            $wrap.find('#misaha-pass-price-breakdown').html(
                '<div class="misaha-price-line"><span>' + t('Pass Price') + '</span><span>' + fmt(basePrice) + '</span></div>' +
                discLine +
                '<div class="misaha-price-line total"><span>' + t('Total') + '</span><span>' + fmt(finalAmt) + '</span></div>'
            );
        }

        /* ── Seat Discount ──────────────────────────────────── */
        $wrap.on('click', '#misaha-sm-apply-discount', function () {
            const code = $wrap.find('#misaha-sm-discount-code').val().trim().toUpperCase();
            const $msg = $wrap.find('#misaha-sm-discount-msg');

            if (!code) { showMsg($msg, t('Enter a discount code.'), 'error'); return; }

            $(this).prop('disabled', true).text(t('Checking…'));

            $.post(ajaxUrl, {
                action: 'misaha_validate_discount',
                nonce:  nonce,
                code:   code,
                type:   'seat',
                price:  selectedPassPrice,
            })
            .done(function (res) {
                if (res.success && res.data.valid) {
                    discountData = res.data;
                    showMsg($msg, '✅ ' + res.data.message, 'success');
                    renderPassPriceBreakdown(selectedPassPrice, discountData.discount_amount);
                } else {
                    discountData = null;
                    showMsg($msg, '❌ ' + (res.data ? res.data.message : t('Invalid code.')), 'error');
                    renderPassPriceBreakdown(selectedPassPrice, 0);
                }
            })
            .fail(function () { showMsg($msg, '❌ ' + t('Network error.'), 'error'); })
            .always(function () {
                $wrap.find('#misaha-sm-apply-discount').prop('disabled', false).text(t('Apply'));
            });
        });

        /* ── Pay Now (Pass) ─────────────────────────────────── */
        $wrap.on('click', '#misaha-sm-pay-now', function () {
            if (!isLoggedIn) { window.location.href = loginUrl; return; }

            const $btn      = $(this).prop('disabled', true).text(t('Processing…'));
            const $result   = $wrap.find('#misaha-pass-result').hide().removeClass('success error');
            const startDate = $wrap.find('#misaha-pass-start').val();
            const code      = $wrap.find('#misaha-sm-discount-code').val().trim().toUpperCase();

            if (!startDate) {
                showResult($result, '❌ ' + t('Please select a start date.'), 'error');
                $btn.prop('disabled', false).text('💳 ' + t('Proceed to Payment'));
                return;
            }

            $.post(ajaxUrl, {
                action:        'misaha_create_pass',
                nonce:         nonce,
                seat_id:       selectedSeatId,
                hall_id:       selectedHallId,
                pass_type:     selectedPassType,
                start_date:    startDate,
                discount_code: code,
            })
            .done(function (res) {
                if (res.success) {
                    const d = res.data;
                    if (d.payment_url) {
                        showResult($result,
                            '✅ ' + t('Pass') + ' <strong>#' + d.pass_id + '</strong> ' + t('created! Redirecting to payment…'),
                            'success'
                        );
                        setTimeout(function () { window.location.href = d.payment_url; }, 1800);
                    } else {
                        showResult($result,
                            '✅ ' + t('Pass') + ' <strong>#' + d.pass_id + '</strong> ' + t('created! Total:') + ' <strong>' + fmt(d.final_price) + '</strong><br>' +
                            t('Valid:') + ' <strong>' + d.start_date + '</strong> → <strong>' + d.end_date + '</strong>',
                            'success'
                        );
                        $btn.text(t('Done!'));
                    }
                } else {
                    showResult($result, '❌ ' + res.data.message, 'error');
                    $btn.prop('disabled', false).text('💳 ' + t('Proceed to Payment'));
                }
            })
            .fail(function () {
                showResult($result, '❌ ' + t('Network error. Please try again.'), 'error');
                $btn.prop('disabled', false).text('💳 ' + t('Proceed to Payment'));
            });
        });

        /* ── Seat Map Back Buttons ───────────────────────────── */
        $wrap.on('click', '.misaha-sm-back', function () {
            const to = parseInt($(this).data('to'));
            const from = to + 1;

            $wrap.find('#misaha-sm-step-' + from).slideUp(200, function () {
                $wrap.find('#misaha-sm-step-' + to).slideDown(240);
            });
            $wrap.find('.misaha-step[data-step="' + from + '"]').removeClass('active done');
            $wrap.find('.misaha-step[data-step="' + to + '"]').removeClass('done').addClass('active');
        });
    })();

    /* ================================================================
       USER DASHBOARD TABS
    ================================================================ */

    (function initDashboard() {
        const $dash = $('#misaha-user-dashboard');
        if (!$dash.length) return;

        $dash.on('click', '.misaha-tab-btn', function () {
            const tab = $(this).data('tab');
            $dash.find('.misaha-tab-btn').removeClass('active');
            $dash.find('.misaha-tab-panel').hide();
            $(this).addClass('active');
            $dash.find('#misaha-tab-' + tab).slideDown(220);
        });
    })();

})(jQuery);