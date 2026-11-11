(function () {
    'use strict';

    var cfg = window.scdCalendar || {};
    var feedback = document.getElementById('scd-cal-feedback');
    var selbar = document.getElementById('scd-selbar');

    var drag = { id: null, time: null, from: null, el: null };
    var selected = {}; // post id -> chip element
    var pop = null;    // open popover backdrop

    // --- helpers -------------------------------------------------------------

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

    function send(data) {
        var body = Object.keys(data).map(function (k) {
            return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]);
        }).join('&');
        return fetch(cfg.ajaxUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body
        }).then(function (r) {
            return r.json();
        }).then(function (j) {
            if (j && j.success) {
                return j.data || {};
            }
            throw new Error((j && j.data && j.data.message) || cfg.failed);
        });
    }

    function chipDate(chip) {
        var cell = chip.closest('.scd-day');
        return cell ? cell.getAttribute('data-date') : null;
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

    // --- reschedule (single + batch) ----------------------------------------

    function settle(promises) {
        return Promise.all(promises.map(function (p) {
            return p.then(function (v) { return { ok: true, value: v }; }, function (e) { return { ok: false, error: e }; });
        }));
    }

    function rescheduleMany(chips, toDate) {
        var moves = chips.map(function (chip) {
            return { chip: chip, id: chip.getAttribute('data-post-id'), time: chip.getAttribute('data-time'), from: chipDate(chip) };
        }).filter(function (m) {
            return m.from !== toDate;
        });
        if (!moves.length) {
            return;
        }
        showFeedback(cfg.rescheduling);
        settle(moves.map(function (m) {
            return send({ action: cfg.rescheduleAction, nonce: cfg.nonce, post_id: m.id, new_date: toDate, time: m.time })
                .then(function () { placeChip(m.chip, toDate); });
        })).then(function (results) {
            var done = moves.filter(function (m, i) { return results[i].ok; });
            var failed = results.filter(function (r) { return !r.ok; });
            if (!done.length) {
                showFeedback(failed[0].error.message || cfg.failed, 'error');
                return;
            }
            var msg = failed.length
                ? (cfg.partial || '%1$d moved, %2$d failed.').replace('%1$d', done.length).replace('%2$d', failed.length)
                : cfg.success;
            showFeedback(msg, failed.length ? 'error' : 'success', function () {
                settle(done.map(function (m) {
                    return send({ action: cfg.rescheduleAction, nonce: cfg.nonce, post_id: m.id, new_date: m.from, time: m.time })
                        .then(function () { placeChip(m.chip, m.from); });
                })).then(function (back) {
                    var bad = back.filter(function (r) { return !r.ok; });
                    if (bad.length) {
                        showFeedback(bad[0].error.message || cfg.failed, 'error');
                    }
                });
            });
        });
    }

    function reloadSoon(msg) {
        showFeedback(msg);
        setTimeout(function () { window.location.reload(); }, 900);
    }

    function scheduleDraft(el, toDate) {
        showFeedback(cfg.rescheduling);
        send({ action: cfg.scheduleAction, nonce: cfg.nonce, post_id: el.getAttribute('data-post-id'), new_date: toDate, time: '09:00' })
            .then(function (d) {
                reloadSoon(d.moved ? (cfg.movedTo || '%s').replace('%s', d.when) : (cfg.scheduledAt || '%s').replace('%s', d.when));
            }).catch(function (e) { showFeedback(e.message || cfg.failed, 'error'); });
    }

    function unschedule(chip) {
        showFeedback(cfg.rescheduling);
        send({ action: cfg.unscheduleAction, nonce: cfg.nonce, post_id: chip.getAttribute('data-post-id') })
            .then(function () { reloadSoon(cfg.unscheduled); })
            .catch(function (e) { showFeedback(e.message || cfg.failed, 'error'); });
    }

    // --- selection -----------------------------------------------------------

    function selCount() {
        return Object.keys(selected).length;
    }

    function refreshSelbar() {
        if (!selbar) {
            return;
        }
        var n = selCount();
        if (!n) {
            selbar.classList.remove('is-on');
            return;
        }
        selbar.querySelector('.scd-selcount').textContent = (cfg.selected || '%d selected').replace('%d', n);
        selbar.querySelector('.scd-selhint').textContent = cfg.moveHint || '';
        var clear = selbar.querySelector('.scd-selclear');
        clear.textContent = cfg.clear || 'Clear';
        selbar.classList.add('is-on');
    }

    function toggleSelect(chip) {
        var id = chip.getAttribute('data-post-id');
        if (selected[id]) {
            delete selected[id];
            chip.classList.remove('is-selected');
        } else {
            selected[id] = chip;
            chip.classList.add('is-selected');
        }
        refreshSelbar();
    }

    function clearSelection() {
        Object.keys(selected).forEach(function (id) {
            selected[id].classList.remove('is-selected');
        });
        selected = {};
        refreshSelbar();
    }

    if (selbar) {
        selbar.querySelector('.scd-selclear').addEventListener('click', clearSelection);
    }

    // --- popover (create / edit) --------------------------------------------

    function closePopover() {
        if (pop) {
            var ret = pop._return;
            pop.remove();
            pop = null;
            if (ret && ret.focus) {
                ret.focus();
            }
        }
    }

    var fieldSeq = 0;

    function field(labelText, input) {
        var id = 'scd-f-' + (++fieldSeq);
        input.id = id;
        var label = document.createElement('label');
        label.setAttribute('for', id);
        label.textContent = labelText;
        var wrap = document.createDocumentFragment();
        wrap.appendChild(label);
        wrap.appendChild(input);
        return wrap;
    }

    function option(value, label) {
        var o = document.createElement('option');
        o.value = value;
        o.textContent = label;
        return o;
    }

    function trapFocus(e) {
        if (e.key !== 'Tab' || !pop) {
            return;
        }
        var f = pop.querySelectorAll('input:not([disabled]), select:not([disabled]), textarea:not([disabled]), button:not([disabled]), a[href]');
        if (!f.length) {
            return;
        }
        var first = f[0];
        var last = f[f.length - 1];
        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    function openPopover(mode, opts) {
        closePopover();
        opts = opts || {};

        var backdrop = document.createElement('div');
        backdrop.className = 'scd-pop-backdrop';
        var panel = document.createElement('div');
        panel.className = 'scd-pop';
        panel.setAttribute('role', 'dialog');
        panel.setAttribute('aria-modal', 'true');
        panel.setAttribute('aria-labelledby', 'scd-pop-heading');
        backdrop.appendChild(panel);

        var heading = document.createElement('h3');
        heading.id = 'scd-pop-heading';
        heading.textContent = mode === 'edit' ? (cfg.editHeading || 'Edit post') : (cfg.newHeading || 'New post');
        panel.appendChild(heading);

        var title = document.createElement('input');
        title.type = 'text';
        title.value = opts.title || '';
        panel.appendChild(field(cfg.titleLabel || 'Title', title));

        var typeSel = null;
        var statusSel = null;
        var types = cfg.types || [];
        if (mode === 'create') {
            if (types.length > 1) {
                typeSel = document.createElement('select');
                types.forEach(function (t) { typeSel.appendChild(option(t.name, t.label)); });
                panel.appendChild(field(cfg.typeLabel || 'Post type', typeSel));
            }
            statusSel = document.createElement('select');
            panel.appendChild(field(cfg.statusLabel || 'Status', statusSel));
        }

        var when = document.createElement('input');
        when.type = 'datetime-local';
        when.value = opts.when || '';
        var whenFields = field(cfg.whenLabel || 'Publish at', when);
        var whenWrap = document.createElement('div');
        whenWrap.appendChild(whenFields);
        panel.appendChild(whenWrap);

        var hint = document.createElement('p');
        hint.className = 'scd-hint';
        panel.appendChild(hint);

        var excerpt = null;
        if (mode === 'edit') {
            excerpt = document.createElement('textarea');
            excerpt.rows = 3;
            excerpt.value = opts.chip.getAttribute('data-excerpt') || '';
            panel.appendChild(field(cfg.excerptLabel || 'Excerpt', excerpt));
        }

        function currentType() {
            var name = typeSel ? typeSel.value : (types[0] && types[0].name);
            return types.filter(function (t) { return t.name === name; })[0] || types[0];
        }

        function syncCreateFields() {
            if (!statusSel) {
                return;
            }
            var canPublish = !!(currentType() && currentType().canPublish);
            var prev = statusSel.value;
            statusSel.textContent = '';
            if (canPublish) {
                statusSel.appendChild(option('future', cfg.statusFuture || 'Scheduled'));
            }
            statusSel.appendChild(option('draft', cfg.statusDraft || 'Draft'));
            statusSel.value = prev === 'draft' || !canPublish ? 'draft' : 'future';
            var isDraft = statusSel.value === 'draft';
            whenWrap.hidden = isDraft;
            hint.textContent = isDraft ? (cfg.draftHint || '') : (cfg.pastHint || '');
        }

        if (statusSel) {
            if (typeSel) {
                typeSel.addEventListener('change', syncCreateFields);
            }
            statusSel.addEventListener('change', syncCreateFields);
            syncCreateFields();
        } else if (mode === 'edit' && opts.chip.getAttribute('data-status') === 'future') {
            hint.textContent = cfg.pastHint || '';
        }

        var actions = document.createElement('div');
        actions.className = 'scd-pop-actions';

        if (mode === 'edit') {
            var del = document.createElement('button');
            del.type = 'button';
            del.className = 'scd-del';
            del.textContent = cfg.del || 'Delete';
            del.addEventListener('click', function () {
                if (!window.confirm(cfg.confirmDel || 'Delete?')) {
                    return;
                }
                send({ action: cfg.trashAction, nonce: cfg.nonce, post_id: opts.chip.getAttribute('data-post-id') })
                    .then(function () {
                        opts.chip.remove();
                        closePopover();
                        showFeedback(cfg.deleted || 'Deleted.');
                    }).catch(function (e) { showFeedback(e.message, 'error'); });
            });
            actions.appendChild(del);

            var open = document.createElement('a');
            open.className = 'scd-open';
            open.href = opts.chip.getAttribute('href');
            open.textContent = cfg.openEditor || 'Open editor';
            actions.appendChild(open);
        }

        var spacer = document.createElement('span');
        spacer.className = 'scd-spacer';
        actions.appendChild(spacer);

        var cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.textContent = cfg.cancel || 'Cancel';
        cancel.addEventListener('click', closePopover);
        actions.appendChild(cancel);

        var save = document.createElement('button');
        save.type = 'button';
        save.className = 'scd-save';
        save.textContent = cfg.save || 'Save';
        save.addEventListener('click', function () {
            if (mode === 'edit') {
                save.disabled = true;
                showFeedback(cfg.saving);
                send({ action: cfg.editAction, nonce: cfg.nonce, post_id: opts.chip.getAttribute('data-post-id'), title: title.value, when: when.value, excerpt: excerpt.value })
                    .then(function (d) {
                        var chip = opts.chip;
                        chip.setAttribute('data-title', d.title);
                        chip.setAttribute('data-time', d.time);
                        chip.setAttribute('data-excerpt', d.excerpt || '');
                        chip.querySelector('.scd-ti').textContent = d.title;
                        var timeEl = chip.querySelector('.scd-time');
                        if (timeEl) {
                            timeEl.textContent = d.time.slice(0, 5);
                        }
                        if (d.date) {
                            placeChip(chip, d.date);
                        }
                        closePopover();
                        showFeedback(d.moved ? (cfg.movedTo || '%s').replace('%s', d.when) : cfg.success);
                    }).catch(function (e) { save.disabled = false; showFeedback(e.message || cfg.saveFailed, 'error'); });
            } else {
                var parts = (when.value || '').split('T');
                save.disabled = true;
                showFeedback(cfg.saving);
                send({
                    action: cfg.createAction, nonce: cfg.nonce, date: parts[0], time: parts[1] || '09:00', title: title.value,
                    post_type: currentType() ? currentType().name : '', status: statusSel.value
                })
                    .then(function (d) {
                        if (d.status === 'draft') {
                            reloadSoon(cfg.draftCreated);
                        } else {
                            reloadSoon(d.moved ? (cfg.movedTo || '%s').replace('%s', d.when) : (cfg.scheduledAt || '%s').replace('%s', d.when));
                        }
                    })
                    .catch(function (e) { save.disabled = false; showFeedback(e.message || cfg.createFailed, 'error'); });
            }
        });
        actions.appendChild(save);
        panel.appendChild(actions);

        backdrop.addEventListener('mousedown', function (e) {
            if (e.target === backdrop) {
                closePopover();
            }
        });

        backdrop.addEventListener('keydown', trapFocus);
        document.body.appendChild(backdrop);
        backdrop._return = opts.chip || opts.cell || null;
        pop = backdrop;
        title.focus();
        title.setSelectionRange(0, 0);
        title.scrollLeft = 0;
    }

    function editChip(chip) {
        var date = chipDate(chip);
        var time = (chip.getAttribute('data-time') || '09:00:00').slice(0, 5);
        openPopover('edit', { chip: chip, title: chip.getAttribute('data-title') || '', when: date + 'T' + time });
    }

    function createOn(cell) {
        var date = cell.getAttribute('data-date');
        openPopover('create', { cell: cell, when: date + 'T09:00' });
    }

    // --- drag and drop -------------------------------------------------------

    function clearOver() {
        var marked = document.querySelectorAll('.scd-day--dragover');
        for (var i = 0; i < marked.length; i++) {
            marked[i].classList.remove('scd-day--dragover');
        }
    }

    document.addEventListener('dragstart', function (e) {
        var chip = e.target.closest ? e.target.closest('.scd-chip, .scd-dchip') : null;
        if (!chip) {
            return;
        }
        drag.el = chip;
        drag.id = chip.getAttribute('data-post-id');
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
        if (!toDate) {
            return;
        }
        if (drag.el && drag.el.classList.contains('scd-dchip')) {
            scheduleDraft(drag.el, toDate);
            return;
        }
        var chips;
        if (selected[drag.id] && selCount() > 1) {
            chips = Object.keys(selected).map(function (id) { return selected[id]; });
            clearSelection();
        } else {
            chips = [drag.el];
        }
        rescheduleMany(chips, toDate);
    });

    var drawer = document.getElementById('scd-drawer');
    if (drawer) {
        drawer.addEventListener('dragover', function (e) {
            if (drag.el && drag.el.classList.contains('scd-chip') && drag.el.getAttribute('data-status') === 'future') {
                e.preventDefault();
                drawer.classList.add('is-over');
            }
        });
        drawer.addEventListener('dragleave', function () {
            drawer.classList.remove('is-over');
        });
        drawer.addEventListener('drop', function (e) {
            drawer.classList.remove('is-over');
            if (drag.el && drag.el.classList.contains('scd-chip') && drag.el.getAttribute('data-status') === 'future') {
                e.preventDefault();
                e.stopPropagation();
                unschedule(drag.el);
            }
        });
    }

    // --- clicks --------------------------------------------------------------

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
            createOn(add.closest('.scd-day'));
            return;
        }

        var chip = e.target.closest ? e.target.closest('.scd-chip') : null;
        if (chip) {
            e.preventDefault();
            if (e.metaKey || e.ctrlKey) {
                toggleSelect(chip);
            } else {
                editChip(chip);
            }
        }
    });

    // --- keyboard ------------------------------------------------------------

    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && pop) {
            closePopover();
            return;
        }
        var tag = (document.activeElement && document.activeElement.tagName) || '';
        if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT') {
            return;
        }

        var active = document.activeElement;
        var chip = active && active.classList && active.classList.contains('scd-chip') ? active : null;
        var cell = active && active.classList && active.classList.contains('scd-day') ? active : null;

        if (chip) {
            if (e.key === 'Enter') {
                e.preventDefault();
                editChip(chip);
            } else if (e.key === ' ' || e.key === 'Spacebar') {
                e.preventDefault();
                toggleSelect(chip);
            }
            return;
        }

        if (!cell) {
            return;
        }
        var days = Array.prototype.slice.call(document.querySelectorAll('.scd-day[data-date]'));
        var idx = days.indexOf(cell);
        var next = -1;
        if (e.key === 'ArrowRight') { next = idx + 1; }
        else if (e.key === 'ArrowLeft') { next = idx - 1; }
        else if (e.key === 'ArrowDown') { next = idx + 7; }
        else if (e.key === 'ArrowUp') { next = idx - 7; }
        else if (e.key === 'Enter') {
            e.preventDefault();
            var firstChip = cell.querySelector('.scd-chip:not([hidden])');
            if (firstChip) { firstChip.focus(); } else { createOn(cell); }
            return;
        }
        if (next >= 0 && next < days.length) {
            e.preventDefault();
            days[next].focus();
        }
    });
})();
