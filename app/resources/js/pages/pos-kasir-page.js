if (!window.__posKasirPageScriptInjected) {
    window.__posKasirPageScriptInjected = true;

    const script = document.createElement('script');
    script.src = '/js/pos-kasir-page-core.js?v=20260608-qty-drawer-fix3';
    script.async = false;
    document.head.appendChild(script);
}
