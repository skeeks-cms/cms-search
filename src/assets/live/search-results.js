(function () {
    'use strict';
    if (window.sxSearchResultsReady) return;
    window.sxSearchResultsReady = true;
    const safeUrl = value => {
        try { const url = new URL(value, location.href); return /^https?:$/.test(url.protocol) ? url.href : null; }
        catch (_) { return null; }
    };
    document.addEventListener('click', async event => {
        const button = event.target.closest('.sx-search-results__more');
        if (!button || button.disabled) return;
        const group = button.closest('[data-group]');
        const root = group.closest('.sx-search-results');
        const items = group.querySelector('.sx-search-results__items');
        const status = group.querySelector('[role="status"]');
        button.disabled = true;
        button.textContent = 'Загружаем…';
        status.textContent = '';
        const request = new AbortController();
        const timeout = setTimeout(() => request.abort(), 15000);
        try {
            const url = new URL(root.dataset.endpoint, location.href);
            url.searchParams.set('q', root.dataset.query);
            url.searchParams.set('group', group.dataset.group);
            url.searchParams.set('page', button.dataset.page);
            const response = await fetch(url, {signal: request.signal, credentials: 'same-origin', headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error('Search unavailable');
            const data = await response.json();
            if (!Array.isArray(data.items) || data.id !== group.dataset.group) throw new Error('Invalid response');
            const ids = new Set(Array.from(items.children, item => item.dataset.id));
            let firstAdded;
            let added = 0;
            data.items.forEach(item => {
                const href = safeUrl(item.url);
                if (!href || ids.has(String(item.id))) return;
                ids.add(String(item.id));
                const link = document.createElement('a');
                link.className = 'sx-search-results__item'; link.href = href; link.dataset.id = item.id;
                const imageUrl = item.image && safeUrl(item.image);
                if (imageUrl) {
                    const img = document.createElement('img');
                    img.src = imageUrl; img.alt = ''; img.width = 80; img.height = 80; img.loading = 'lazy'; link.append(img);
                }
                const copy = document.createElement('span');
                const name = document.createElement('span'); name.className = 'sx-search-results__name'; name.textContent = item.name;
                const subtitle = document.createElement('span'); subtitle.className = 'sx-search-results__subtitle'; subtitle.textContent = item.subtitle;
                copy.append(name, subtitle); link.append(copy); items.append(link);
                firstAdded = firstAdded || link; added++;
            });
            button.dataset.page = data.nextPage || '';
            button.hidden = !data.nextPage;
            status.textContent = added ? 'Добавлено: ' + added + '. Показано: ' + items.children.length : 'Все результаты показаны';
            if (firstAdded) firstAdded.focus({preventScroll: true});
        } catch (_) {
            status.textContent = 'Не удалось загрузить. Нажмите «Показать ещё», чтобы повторить.';
        } finally {
            clearTimeout(timeout); button.disabled = false; button.textContent = 'Показать ещё';
        }
    });
})();
