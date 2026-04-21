(function ($) {
    'use strict';

    function showFeedback(message, kind) {
        var $el = $('#scd-cal-feedback');
        $el.removeClass('scd-cal-feedback--success scd-cal-feedback--error');
        if (kind) {
            $el.addClass('scd-cal-feedback--' + kind);
        }
        $el.text(message).fadeIn(120);
        clearTimeout($el.data('timer'));
        $el.data('timer', setTimeout(function () { $el.fadeOut(200); }, 2500));
    }

    $(function () {
        $('.scd-cal-item').draggable({
            revert: 'invalid',
            revertDuration: 150,
            helper: 'clone',
            appendTo: 'body',
            cursor: 'move',
            zIndex: 9999
        });

        $('.scd-cal-day').droppable({
            accept: '.scd-cal-item',
            tolerance: 'pointer',
            hoverClass: 'scd-cal-day-over',
            drop: function (event, ui) {
                var $day = $(this);
                var $item = ui.draggable;
                var newDate = $day.data('date');
                var postId = $item.data('post-id');
                var time = $item.data('time') || '09:00:00';

                if (!newDate || !postId) { return; }

                // Ignore drop onto same day.
                if ($item.closest('.scd-cal-day').data('date') === newDate) { return; }

                showFeedback(scdCalendar.rescheduling);

                $.post(scdCalendar.ajaxUrl, {
                    action: 'scd_reschedule',
                    nonce: scdCalendar.nonce,
                    post_id: postId,
                    new_date: newDate,
                    time: time
                })
                    .done(function (response) {
                        if (response && response.success) {
                            $item.detach().appendTo($day);
                            // If the item was in a missed day, recompute colors.
                            $item.removeClass('scd-cal-item--missed');
                            showFeedback(scdCalendar.success, 'success');
                        } else {
                            var msg = (response && response.data && response.data.message) || scdCalendar.failed;
                            showFeedback(msg, 'error');
                        }
                    })
                    .fail(function () {
                        showFeedback(scdCalendar.failed, 'error');
                    });
            }
        });
    });
})(jQuery);
