if (!window.__posKasirPageScriptInjected) {
    window.__posKasirPageScriptInjected = true;

    const script = document.createElement('script');
    script.src = '/js/pos-kasir-page-core.js';
    script.async = false;
    document.head.appendChild(script);
}
