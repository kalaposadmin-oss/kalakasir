import './bootstrap';

const prefetchedUrls = new Set();
const priorityPrefetchPaths = new Set([
    '/pos/dashboard',
    '/pos/checkout',
    '/pos/history',
    '/backoffice/dashboard',
    '/backoffice/reports',
    '/backoffice/reports/sales',
    '/backoffice/reports/inventory',
]);

const canPrefetchUrl = (url) => {
    if (!(url instanceof URL)) {
        return false;
    }

    if (url.origin !== window.location.origin) {
        return false;
    }

    if (document.visibilityState === 'hidden') {
        return false;
    }

    if (navigator.onLine === false || navigator.connection?.saveData) {
        return false;
    }

    if (url.pathname === window.location.pathname && url.search === window.location.search) {
        return false;
    }

    if (!/^https?:$/.test(url.protocol)) {
        return false;
    }

    if (url.hash && url.pathname === window.location.pathname) {
        return false;
    }

    return true;
};

const shouldPrefetchLink = (link, url) => {
    if (!link || link.dataset.prefetch === 'off') {
        return false;
    }

    if (link.dataset.prefetch === 'priority') {
        return true;
    }

    return priorityPrefetchPaths.has(url.pathname);
};

const prefetchDocument = (href) => {
    let timeoutId = null;

    try {
        const url = new URL(href, window.location.origin);
        const cacheKey = url.toString();

        if (!canPrefetchUrl(url) || prefetchedUrls.has(cacheKey)) {
            return;
        }

        prefetchedUrls.add(cacheKey);

        const controller = new AbortController();
        timeoutId = window.setTimeout(() => controller.abort(), 2500);

        fetch(cacheKey, {
            method: 'GET',
            credentials: 'same-origin',
            cache: 'force-cache',
            signal: controller.signal,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Purpose': 'prefetch',
            },
        })
            .catch(() => {
                prefetchedUrls.delete(cacheKey);
            })
            .finally(() => {
                if (timeoutId) {
                    window.clearTimeout(timeoutId);
                }
            });
    } catch (_) {
        // Ignore malformed URLs.
    }
};

const bindDocumentPrefetch = () => {
    if (window.__kalakasirPrefetchBound) {
        return;
    }

    window.__kalakasirPrefetchBound = true;

    document.addEventListener('pointerenter', (event) => {
        const link = event.target instanceof Element
            ? event.target.closest('a[href]')
            : null;

        if (!link) {
            return;
        }

        const url = new URL(link.href, window.location.origin);
        if (shouldPrefetchLink(link, url)) {
            prefetchDocument(url.toString());
        }
    }, true);

    document.addEventListener('touchstart', (event) => {
        const link = event.target instanceof Element
            ? event.target.closest('a[href]')
            : null;

        if (!link) {
            return;
        }

        const url = new URL(link.href, window.location.origin);
        if (shouldPrefetchLink(link, url)) {
            prefetchDocument(url.toString());
        }
    }, { passive: true, capture: true });
};

bindDocumentPrefetch();
