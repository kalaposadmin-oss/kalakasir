if (!window.__posKasirPageScriptInjected) {
    window.__posKasirPageScriptInjected = true;

    const script = document.createElement('script');
    script.src = '/js/pos-kasir-page-core.js?v=20260619-rounding-settings';
    script.async = false;
    document.head.appendChild(script);
}
