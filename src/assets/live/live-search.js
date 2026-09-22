/* Storefront extension: one anchored panel, inheriting the shop typography and accent.
 * Navigation suggestions precede collections and products. The input remains usable
 * throughout loading; Enter always retains the existing full search route. */
(function () {
    'use strict';
    if (window.SkeeksLiveSearch) return;
    let sequence = 0;
    const instances = new WeakMap();
    const text = (tag, className, value) => {
        const element = document.createElement(tag);
        element.className = className;
        if (value) element.textContent = value;
        return element;
    };
    function safeUrl(value) {
        try {
            const url = new URL(value, location.href);
            return /^https?:$/.test(url.protocol) ? url.href : null;
        } catch (_) { return null; }
    }
    function highlight(element, value, query) {
        const words = query.toLocaleLowerCase().match(/[\p{L}\p{N}]+/gu) || [];
        const original = String(value || '');
        const lower = original.toLocaleLowerCase();
        let position = 0;
        while (position < original.length) {
            let found = original.length;
            let length = 0;
            words.forEach(word => {
                const index = lower.indexOf(word, position);
                if (index >= 0 && (index < found || (index === found && word.length > length))) {
                    found = index; length = word.length;
                }
            });
            element.append(document.createTextNode(original.slice(position, found)));
            if (!length) break;
            element.append(text('strong', 'sx-live-search__match', original.slice(found, found + length)));
            position = found + length;
        }
    }
    function attach(input, config) {
        if (instances.has(input) || !input.form || !window.fetch || !window.AbortController) return;
        const form = input.form;
        const id = 'sx-live-search-' + (++sequence);
        const panel = text('div', 'sx-live-search');
        panel.id = id;
        panel.hidden = true;
        panel.setAttribute('role', 'region');
        panel.setAttribute('aria-label', 'Подсказки поиска');
        const status = text('div', 'sx-live-search__status');
        status.setAttribute('role', 'status');
        status.setAttribute('aria-live', 'polite');
        const body = text('div', 'sx-live-search__body');
        const footer = text('a', 'sx-live-search__all', 'Все результаты поиска →');
        panel.append(status, body, footer);
        document.body.append(panel);
        input.setAttribute('autocomplete', 'off');
        input.setAttribute('aria-controls', id);
        input.setAttribute('aria-expanded', 'false');
        if (!input.getAttribute('aria-label')) input.setAttribute('aria-label', 'Поиск по каталогу');
        let timer, controller, revision = 0, focused = false, composing = false;
        let current = '', matchedQuery = '', results = [], active = -1;
        const normalize = () => input.value.trim().replace(/\s+/g, ' ').slice(0, 120);
        const resultUrl = query => {
            const url = new URL(form.action || config.resultsUrl, location.href);
            url.searchParams.set(config.queryParam, query);
            return url.href;
        };
        function position() {
            if (panel.hidden) return;
            const rect = input.getBoundingClientRect();
            const formRect = form.getBoundingClientRect();
            const viewport = window.visualViewport;
            const width = viewport ? viewport.width : window.innerWidth;
            const height = viewport ? viewport.height : window.innerHeight;
            if (!rect.width || rect.bottom < 0 || rect.top > height) { close(); return; }
            const panelWidth = width <= 600 ? width - 16 : Math.min(Math.max(formRect.width, 560), 760, width - 24);
            const left = width <= 600 ? 8 : Math.max(12, Math.min(rect.left, width - panelWidth - 12));
            panel.style.width = panelWidth + 'px';
            panel.style.left = left + 'px';
            panel.style.top = (rect.bottom + 6) + 'px';
            panel.style.maxHeight = Math.max(100, Math.min(620, height - rect.bottom - 18)) + 'px';
        }
        function show() {
            if (!focused) return;
            panel.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            position();
        }
        function cancel() {
            clearTimeout(timer);
            revision++;
            if (controller) controller.abort();
            controller = null;
        }
        function close() {
            cancel();
            panel.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            active = -1;
        }
        function choose(index) {
            active = index;
            results[index].focus();
        }
        function render(data, query) {
            matchedQuery = typeof data.matchedQuery === 'string' && data.matchedQuery ? data.matchedQuery : query;
            footer.href = resultUrl(query);
            body.inert = false;
            body.removeAttribute('aria-hidden');
            panel.style.minHeight = '';
            body.replaceChildren();
            let count = 0;
            (Array.isArray(data.groups) ? data.groups : []).forEach(group => {
                const section = text('section', 'sx-live-search__group');
                section.append(text('h2', 'sx-live-search__heading', group.title));
                (Array.isArray(group.items) ? group.items : []).forEach(item => {
                    const url = safeUrl(item.url);
                    if (!url) return;
                    const link = text('a', 'sx-live-search__item' + (group.id === 'products' ? ' sx-live-search__item--product' : ''));
                    link.href = url;
                    const photo = text('span', 'sx-live-search__photo');
                    const imageUrl = item.image && safeUrl(item.image);
                    if (imageUrl) {
                        const image = document.createElement('img');
                        image.src = imageUrl; image.alt = ''; image.width = 56; image.height = 56;
                        image.loading = 'lazy'; image.decoding = 'async';
                        image.addEventListener('error', () => image.remove(), {once:true});
                        photo.append(image);
                    }
                    const copy = text('span', 'sx-live-search__copy');
                    const name = text('span', 'sx-live-search__name');
                    highlight(name, item.name, typeof data.highlightQuery === 'string' ? data.highlightQuery : matchedQuery);
                    copy.append(name);
                    if (item.subtitle) copy.append(text('span', 'sx-live-search__subtitle', item.subtitle));
                    if (item.price) copy.append(text('span', 'sx-live-search__price', item.price));
                    link.append(photo, copy, text('span', 'sx-live-search__arrow', '↗'));
                    section.append(link); count++;
                });
                if (section.children.length > 1) body.append(section);
            });
            panel.removeAttribute('aria-busy');
            status.textContent = count ? 'Найдено подсказок: ' + count : 'Ничего не найдено. Попробуйте другое название или артикул.';
            if (count && matchedQuery !== query) status.textContent = 'Результаты по запросу «' + matchedQuery + '»';
            status.classList.toggle('sx-live-search__status--quiet', count > 0 && matchedQuery === query);
            results = Array.from(panel.querySelectorAll('a[href]'));
            active = -1;
            show();
        }
        function schedule() {
            cancel();
            if (composing) return;
            current = normalize();
            matchedQuery = '';
            footer.href = resultUrl(current);
            if (current.length < 2 || !/[\p{L}\p{N}]/u.test(current)) { close(); return; }
            const previousHeight = panel.hidden ? 0 : panel.getBoundingClientRect().height;
            panel.style.minHeight = previousHeight ? Math.min(previousHeight, window.innerHeight - input.getBoundingClientRect().bottom - 18) + 'px' : '';
            body.inert = true;
            body.setAttribute('aria-hidden', 'true');
            results = [footer]; active = -1;
            status.classList.remove('sx-live-search__status--quiet');
            status.textContent = 'Ищем в каталоге…';
            panel.setAttribute('aria-busy', 'true');
            show();
            const query = current, requestRevision = revision;
            timer = setTimeout(async () => {
                const request = new AbortController();
                controller = request;
                const timeout = setTimeout(() => request.abort(), 8000);
                try {
                    const url = new URL(config.endpoint, location.href);
                    url.searchParams.set('q', query);
                    const response = await fetch(url.href, {signal:request.signal, credentials:'same-origin', headers:{Accept:'application/json', 'X-Requested-With':'XMLHttpRequest'}});
                    if (!response.ok) throw new Error('Search unavailable');
                    const data = await response.json();
                    if (requestRevision !== revision || normalize() !== query) return;
                    render(data, query);
                } catch (_) {
                    if (requestRevision !== revision) return;
                    body.replaceChildren();
                    body.inert = false;
                    body.removeAttribute('aria-hidden');
                    panel.style.minHeight = '';
                    panel.removeAttribute('aria-busy');
                    status.textContent = 'Не удалось загрузить подсказки. Нажмите Enter или откройте все результаты.';
                } finally {
                    clearTimeout(timeout);
                    if (controller === request) controller = null;
                }
            }, 250);
        }
        input.addEventListener('input', schedule);
        input.addEventListener('click', () => { if (panel.hidden) { focused = true; schedule(); } });
        input.addEventListener('focus', () => { focused = true; schedule(); });
        input.addEventListener('compositionstart', () => { composing = true; cancel(); });
        input.addEventListener('compositionend', () => { composing = false; schedule(); });
        input.addEventListener('keydown', event => {
            if (event.isComposing) return;
            if (event.key === 'Escape') { close(); return; }
            if (!panel.hidden && results.length && (event.key === 'ArrowDown' || event.key === 'ArrowUp')) {
                event.preventDefault(); choose(event.key === 'ArrowDown' ? 0 : results.length - 1);
            }
        });
        panel.addEventListener('keydown', event => {
            if (event.key === 'Escape') { input.focus(); close(); return; }
            if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
                event.preventDefault();
                choose((active + (event.key === 'ArrowDown' ? 1 : -1) + results.length) % results.length);
            }
        });
        document.addEventListener('focusin', event => {
            if (event.target !== input && !panel.contains(event.target)) { focused = false; close(); }
        });
        document.addEventListener('pointerdown', event => {
            if (event.target !== input && !panel.contains(event.target)) { focused = false; close(); }
        });
        form.addEventListener('submit', () => {
            close();
        });
        window.addEventListener('resize', position, {passive:true});
        window.addEventListener('scroll', position, {passive:true});
        if (window.visualViewport) window.visualViewport.addEventListener('resize', position, {passive:true});
        instances.set(input, true);
    }
    window.SkeeksLiveSearch = {
        init(config) {
            document.querySelectorAll('.sx-search-form input').forEach(input => {
                if (input.name === config.queryParam) attach(input, config);
            });
        }
    };
})();
