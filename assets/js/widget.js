(function () {
    'use strict';

    var WIDGET_SELECTOR = '#scheduled_content_widget';
    var INSIDE_SELECTOR = WIDGET_SELECTOR + ' .inside';

    function getInside() {
        return document.querySelector(INSIDE_SELECTOR);
    }

    function updateUrl(params) {
        if (!window.history || !window.history.replaceState) { return; }
        var url = new URL(window.location.href);
        Object.keys(params).forEach(function (key) {
            var value = params[key];
            if (value === '' || value === null || value === undefined) {
                url.searchParams.delete(key);
            } else {
                url.searchParams.set(key, value);
            }
        });
        window.history.replaceState({}, '', url.toString());
    }

    function showLoading() {
        var inside = getInside();
        if (!inside) { return; }
        inside.style.opacity = '0.5';
        inside.style.pointerEvents = 'none';
        inside.setAttribute('aria-busy', 'true');
        if (scdWidget.loadingText) {
            inside.setAttribute('aria-label', scdWidget.loadingText);
        }
    }

    function clearLoading() {
        var inside = getInside();
        if (!inside) { return; }
        inside.style.opacity = '';
        inside.style.pointerEvents = '';
        inside.removeAttribute('aria-busy');
        inside.removeAttribute('aria-label');
    }

    function swapWidgetBody(html) {
        var inside = getInside();
        if (!inside) { return; }

        // Parse server HTML via DOMParser, then move the parsed body's children
        // into the widget. Safety depends on the server output being fully
        // escaped (esc_html / esc_attr / esc_url on every dynamic value before
        // render) — DOMParser only guarantees that inline <script> tags don't
        // execute, not that attacker-controlled attributes would be safe.
        var parsed = new DOMParser().parseFromString(html, 'text/html');

        while (inside.firstChild) {
            inside.removeChild(inside.firstChild);
        }
        Array.prototype.slice.call(parsed.body.childNodes).forEach(function (node) {
            inside.appendChild(document.adoptNode(node));
        });
    }

    function refresh(payload, urlParams) {
        showLoading();

        var body = new URLSearchParams();
        body.append('action', scdWidget.action);
        body.append('nonce', scdWidget.nonce);
        Object.keys(payload).forEach(function (key) {
            body.append(key, payload[key]);
        });

        fetch(scdWidget.ajaxUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: body.toString()
        })
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (!json || !json.success || !json.data) {
                    fallbackReload();
                    return;
                }
                swapWidgetBody(json.data.html);
                clearLoading();
                scdWidget.nonce = json.data.nonce;
                if (urlParams) { updateUrl(urlParams); }
                bind();
            })
            .catch(fallbackReload);
    }

    function fallbackReload() {
        clearLoading();
        window.location.reload();
    }

    function bind() {
        var inside = getInside();
        if (!inside) { return; }

        Array.prototype.slice.call(inside.querySelectorAll('form')).forEach(function (form) {
            var actionInput = form.querySelector('input[name="action"]');
            if (!actionInput) { return; }

            if (actionInput.value === 'scd_toggle_view') {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var viewInput = form.querySelector('input[name="view"]');
                    var view = viewInput ? viewInput.value : 'list';
                    refresh({ view: view }, {});
                });
            } else if (actionInput.value === 'scd_toggle_mine') {
                form.addEventListener('submit', function (e) {
                    e.preventDefault();
                    var current = form.getAttribute('data-mine-only') === '1';
                    refresh({ mine_only: current ? '0' : '1' }, {});
                });
            }
        });

        var filterForm = inside.querySelector('.scd-filters form');
        if (filterForm) {
            filterForm.addEventListener('submit', function (e) {
                e.preventDefault();
                var type = filterForm.querySelector('select[name="scd_type"]');
                var author = filterForm.querySelector('select[name="scd_author"]');
                var typeVal = type ? type.value : '';
                var authorVal = author ? author.value : '0';
                refresh(
                    { scd_type: typeVal, scd_author: authorVal },
                    { scd_type: typeVal, scd_author: authorVal === '0' ? '' : authorVal }
                );
            });

            var clearLink = filterForm.querySelector('.scd-filters-clear');
            if (clearLink) {
                clearLink.addEventListener('click', function (e) {
                    e.preventDefault();
                    refresh(
                        { scd_type: '', scd_author: '0' },
                        { scd_type: '', scd_author: '' }
                    );
                });
            }
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bind);
    } else {
        bind();
    }
})();
