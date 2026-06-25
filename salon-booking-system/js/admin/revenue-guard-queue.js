/**
 * Revenue Guard — attendance resolution queue (admin calendar & reports).
 */
(function ($) {
  'use strict';

  if (typeof slnRevenueGuard === 'undefined') {
    return;
  }

  var cfg = slnRevenueGuard;

  function post(action, data) {
    return $.ajax({
      url: cfg.ajaxUrl,
      type: 'POST',
      dataType: 'json',
      data: $.extend(
        {
          action: action,
          security: cfg.nonce,
        },
        data || {}
      ),
    });
  }

  function openModal() {
    var $modal = $('#sln-rg-queue-modal');
    if (!$modal.length) {
      return;
    }
    $modal.css('display', 'block').attr('aria-hidden', 'false');
    $('body').addClass('sln-rg-modal-open');
    loadQueue();
  }

  function closeModal() {
    $('#sln-rg-queue-modal').css('display', 'none').attr('aria-hidden', 'true');
    $('body').removeClass('sln-rg-modal-open');
  }

  function renderQueue(bookings) {
    var $list = $('#sln-rg-queue-list');
    if (!bookings || !bookings.length) {
      $list.html('<p class="sln-rg-empty">' + (cfg.i18n.empty || 'No appointments pending.') + '</p>');
      return;
    }

    var clockSvg =
      '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" xmlns="http://www.w3.org/2000/svg">' +
      '<circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.6"/>' +
      '<path d="M12 7.5V12l3 1.8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>' +
      '</svg>';

    var html = '';
    bookings.forEach(function (row) {
      var amount = row.amount ? '<span class="sln-rg-queue-item__amount">' + row.amount + '</span>' : '';
      html +=
        '<div class="sln-rg-queue-item" data-booking-id="' +
        row.id +
        '">' +
        '<div class="sln-rg-queue-item__info">' +
        '<div class="sln-rg-queue-item__customer">' +
        escapeHtml(row.customer_name) +
        '</div>' +
        '<div class="sln-rg-queue-item__meta">' +
        '<span class="sln-rg-queue-item__time">' +
        clockSvg +
        escapeHtml(row.date) +
        ' ' +
        escapeHtml(row.time) +
        '</span>' +
        amount +
        '</div>' +
        '</div>' +
        '<div class="sln-rg-queue-item__actions">' +
        '<button type="button" class="sln-rg-pill sln-rg-pill--primary sln-rg-resolve" data-status="attended">' +
        cfg.i18n.attended +
        '</button>' +
        '<button type="button" class="sln-rg-pill sln-rg-resolve" data-status="partial_show">' +
        cfg.i18n.partial +
        '</button>' +
        '<button type="button" class="sln-rg-pill sln-rg-resolve" data-status="no_show">' +
        cfg.i18n.noShow +
        '</button>' +
        '<button type="button" class="sln-rg-pill sln-rg-pill--muted sln-rg-resolve" data-status="excused">' +
        cfg.i18n.excused +
        '</button>' +
        '</div>' +
        '</div>';
    });

    $list.html(html);
  }

  function escapeHtml(str) {
    return $('<div>').text(str || '').html();
  }

  function loadQueue() {
    $('#sln-rg-queue-list').html('<p class="sln-rg-loading">Loading...</p>');
    post('sln_revenue_guard_unresolved', { limit: 50 })
      .done(function (res) {
        if (res.success) {
          renderQueue(res.data.bookings);
        }
      })
      .fail(function () {
        $('#sln-rg-queue-list').html('<p class="sln-rg-error">' + cfg.i18n.error + '</p>');
      });
  }

  function resolveBooking(bookingId, status, fraction) {
    var payload = {
      booking_id: bookingId,
      status: status,
    };
    if (fraction !== undefined) {
      payload.delivered_fraction = fraction;
    }
    return post('sln_revenue_guard_resolve', payload);
  }

  $(document).on('click', '#sln-rg-open-queue, .sln-rg-open-queue', function (e) {
    e.preventDefault();
    openModal();
  });

  $(document).on('click', '.sln-rg-modal__close, .sln-rg-modal__backdrop', function () {
    closeModal();
  });

  $(document).on('click', '.sln-rg-resolve', function () {
    var $btn = $(this);
    var $item = $btn.closest('.sln-rg-queue-item');
    var bookingId = parseInt($item.data('booking-id'), 10);
    var status = $btn.data('status');
    var fraction;

    if (status === 'partial_show') {
      var input = window.prompt('Delivered fraction (0.1–0.9):', '0.5');
      if (input === null) {
        return;
      }
      fraction = parseFloat(input);
      if (isNaN(fraction)) {
        return;
      }
    }

    $btn.prop('disabled', true);
    resolveBooking(bookingId, status, fraction)
      .done(function (res) {
        if (res.success) {
          $item.fadeOut(function () {
            $(this).remove();
            if (!$('#sln-rg-queue-list .sln-rg-queue-item').length) {
              $('#sln-rg-queue-list').html('<p class="sln-rg-empty">All done.</p>');
              $('#sln-rg-unresolved-banner').fadeOut();
            }
          });
        } else {
          alert(res.data && res.data.error ? res.data.error : cfg.i18n.error);
          $btn.prop('disabled', false);
        }
      })
      .fail(function () {
        alert(cfg.i18n.error);
        $btn.prop('disabled', false);
      });
  });

  $(document).on('click', '#sln-rg-bulk-attended', function () {
    if (!window.confirm(cfg.i18n.confirmBulk)) {
      return;
    }
    var ids = [];
    $('#sln-rg-queue-list .sln-rg-queue-item').each(function () {
      ids.push($(this).data('booking-id'));
    });
    if (!ids.length) {
      return;
    }
    post('sln_revenue_guard_bulk_resolve', {
      booking_ids: ids,
      status: 'attended',
    }).done(function (res) {
      if (res.success) {
        closeModal();
        $('#sln-rg-unresolved-banner').fadeOut();
      }
    });
  });
})(jQuery);
