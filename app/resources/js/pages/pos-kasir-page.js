if (!window.__posKasirPageScriptInjected) {
    window.__posKasirPageScriptInjected = true;

    const script = document.createElement('script');
    script.src = '/js/pos-kasir-page-core.js?v=20260608-pos-payment-fix4';
    script.async = false;
    document.head.appendChild(script);
}
