(function () {
    'use strict';

    var cfg = window.scdCalendar || {};
    var feedback = document.getElementById('scd-cal-feedback');

    var drag = { id: null, time: null, from: null, el: null };

    function showFeedback(msg, kind, undoFn) {
        if (!feedback) {
            return;
        }
        feedback.className = 'scd-feedback' + (kind === 'error' ? ' is-error' : '');
        feedback.textContent = '';
        var span = document.createElement('span');
        span.textContent = msg;
        feedback.appendChild(span);
        if (undoFn) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.textContent = cfg.undo || 'Undo';
            btn.addEventListener('click', function () {
                feedback.style.display = 'none';
                undoFn();
            });
            feedback.appendChild(btn);
        }
        feedback.style.display = 'flex';
        clearTimeout(feedback._timer);
        feedback._timer = setTimeout(function () {
            feedback.style.display = 'none';
        }, undoFn ? 6000 : 2600);
    }

    function send(data, done, fail) {
        var body = Object.keys(data).map(function (k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
        }).join('&');
        fetch(cfg.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (r) {
            return r.json();
        }).then(function (j) {
            if (j && j.success) {
                done(j.data || {});
            } else {
                fail((j && j.data && j.data.message) || cfg.failed);
            }
        }).catch(function () {
            fail(cfg.failed);
        });
    }

    function placeChip(chip, toDate) {
        var cell = document.querySelector('.scd-day[data-date="' + toDate + '"]');
        if (!cell || !chip) {
            return;
        }
        var anchor = cell.querySelector('.scd-more') || cell.querySelector('.scd-add');
        cell.insertBefore(chip, anchor || null);
        chip.removeAttribute('hidden');
    }

    function reschedule(toDate) {
        var id = drag.id, time = drag.time, from = drag.from, chip = drag.el;
        showFeedback(cfg.rescheduling);
        send({ action: cfg.rescheduleAction, nonce: cfg.nonce, post_id: id, new_date: toDate, time: time }, function () {
            placeChip(chip, toDate);
            showFeedback(cfg.success, 'success', function () {
                send({ action: cfg.rescheduleAction, nonce: cfg.nonce, post_id: id, new_date: from, time: time }, function () {
                    placeChip(chip, from);
                }, function (m) {
                    showFeedback(m, 'error');
                });
            });
        }, function (m) {
            showFeedback(m, 'error');
        });
    }

    function clearOver() {
        var marked = document.querySelectorAll('.scd-day--dragover');
        for (var i = 0; i < marked.length; i++) {
            marked[i].classList.remove('scd-day--dragover');
        }
    }

    document.addEventListener('dragstart', function (e) {
        var chip = e.target.closest ? e.target.closest('.scd-chip') : null;
        if (!chip) {
            return;
        }
        var cell = chip.closest('.scd-day');
        drag.id = chip.getAttribute('data-post-id');
        drag.time = chip.getAttribute('data-time');
        drag.from = cell ? cell.getAttribute('data-date') : null;
        drag.el = chip;
        chip.classList.add('is-dragging');
        e.dataTransfer.effectAllowed = 'move';
        try {
            e.dataTransfer.setData('text/plain', drag.id);
        } catch (err) { /* older browsers */ }
    });

    document.addEventListener('dragend', function () {
        if (drag.el) {
            drag.el.classList.remove('is-dragging');
        }
        clearOver();
        drag.el = null;
        drag.id = null;
    });

    document.addEventListener('dragover', function (e) {
        if (!drag.id) {
            return;
        }
        var cell = e.target.closest ? e.target.closest('.scd-day') : null;
        if (!cell) {
            return;
        }
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        if (!cell.classList.contains('scd-day--dragover')) {
            clearOver();
            cell.classList.add('scd-day--dragover');
        }
    });

    document.addEventListener('drop', function (e) {
        if (!drag.id) {
            return;
        }
        var cell = e.target.closest ? e.target.closest('.scd-day') : null;
        if (!cell) {
            return;
        }
        e.preventDefault();
        clearOver();
        var toDate = cell.getAttribute('data-date');
        if (toDate && toDate !== drag.from) {
            reschedule(toDate);
        }
    });

    document.addEventListener('click', function (e) {
        var more = e.target.closest ? e.target.closest('.scd-more') : null;
        if (more) {
            e.preventDefault();
            var cell = more.closest('.scd-day');
            var chips = cell.querySelectorAll('.scd-chip');
            var overflow = parseInt(more.getAttribute('data-more'), 10) || 0;
            var cap = chips.length - overflow;
            if (more.getAttribute('data-expanded') === '1') {
                for (var i = cap; i < chips.length; i++) {
                    chips[i].setAttribute('hidden', '');
                }
                more.textContent = (cfg.moreLabel || '+%d more').replace('%d', overflow);
                more.setAttribute('data-expanded', '0');
            } else {
                for (var j = 0; j < chips.length; j++) {
                    chips[j].removeAttribute('hidden');
                }
                more.textContent = cfg.lessLabel || 'Show less';
                more.setAttribute('data-expanded', '1');
            }
            return;
        }

        var add = e.target.closest ? e.target.closest('.scd-add') : null;
        if (add) {
            e.preventDefault();
            var date = add.closest('.scd-day').getAttribute('data-date');
            var title = window.prompt(cfg.newTitle || 'Post title');
            if (title === null) {
                return;
            }
            showFeedback(cfg.creating);
            send({ action: cfg.createAction, nonce: cfg.nonce, date: date, time: '09:00', title: title }, function () {
                window.location.reload();
            }, function (m) {
                showFeedback(m || cfg.createFailed, 'error');
            });
        }
    });
})();
