<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>{{ $tenantName ?? 'Kala Kasir' }} — Monitor Pesanan</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20,400,0,0" rel="stylesheet" />

    <style>
        :root {
            --app-bg: #FAF9F7;
            --app-border: #E8E5E0;
            --app-muted: #8C8680;
            --orange-500: #F97316;
            --green-500: #22C55E;
            --amber-500: #F59E0B;
            --red-500: #EF4444;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--app-bg);
            color: #1C1917;
            min-height: 100vh;
        }

        body {
            background:
                radial-gradient(circle at top, rgba(249,115,22,0.08), transparent 30%),
                linear-gradient(to bottom, #fff7ed 0%, #f8fafc 32%);
        }

        .monitor-shell {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .monitor-header {
            position: sticky;
            top: 0;
            z-index: 10;
            background: rgba(255,255,255,0.96);
            border-bottom: 1px solid var(--app-border);
            backdrop-filter: blur(18px);
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .monitor-brand {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 0;
        }
        .monitor-badge {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 0.9rem;
            background: linear-gradient(135deg, #FFF7ED 0%, #FFFFFF 100%);
            border: 1px solid #FED7AA;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            color: var(--orange-500);
        }
        .monitor-header h1 {
            font-size: 1rem;
            font-weight: 900;
            letter-spacing: -0.03em;
            color: #1C1917;
        }
        .monitor-header .subline {
            font-size: 0.7rem;
            color: var(--app-muted);
            margin-top: 0.1rem;
        }
        .monitor-header .clock {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--orange-500);
            white-space: nowrap;
        }

        /* Stats bar */
        .stats-bar {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 0.75rem;
            padding: 1rem 1.25rem 0;
        }
        .stat-card {
            flex: 1;
            text-align: center;
            padding: 0.85rem 0.75rem;
            border-radius: 1rem;
            background: white;
            border: 1px solid var(--app-border);
            box-shadow: var(--app-soft-shadow);
        }
        .stat-card .stat-num {
            font-size: 1.5rem;
            font-weight: 800;
            line-height: 1;
        }
        .stat-card .stat-label {
            font-size: 0.65rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--app-muted);
            margin-top: 0.25rem;
        }
        .stat-pending .stat-num { color: var(--amber-500); }
        .stat-progress .stat-num { color: var(--orange-500); }
        .stat-done .stat-num { color: var(--green-500); }

        /* Queue columns */
        .queue-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
            padding: 1rem 1.25rem 1.5rem;
            min-height: calc(100vh - 160px);
        }
        .queue-col {
            display: flex;
            flex-direction: column;
        }
        .queue-col-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 1rem 1rem 0 0;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }
        .col-pending .queue-col-header { background: #FEF3C7; color: #92400E; }
        .col-progress .queue-col-header { background: #FFEDD5; color: #9A3412; }
        .col-done .queue-col-header { background: #DCFCE7; color: #166534; }

        .queue-col-body {
            flex: 1;
            border: 1px solid var(--app-border);
            border-top: none;
            border-radius: 0 0 1rem 1rem;
            background: white;
            padding: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            overflow-y: auto;
            min-height: 18rem;
        }

        /* Order card */
        .order-card {
            border: 1px solid var(--app-border);
            border-radius: 0.9rem;
            padding: 0.85rem;
            transition: all 0.3s ease;
            background: linear-gradient(180deg, #fff 0%, #fffaf5 100%);
        }
        .order-card.new-entry {
            animation: slideIn 0.4s ease-out;
        }
        @keyframes slideIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .order-card .order-code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.7rem;
            font-weight: 500;
            color: var(--app-muted);
        }
        .order-card .order-customer {
            font-size: 0.85rem;
            font-weight: 600;
            margin-top: 0.15rem;
        }
        .order-card .order-items {
            font-size: 0.75rem;
            color: var(--app-muted);
            margin-top: 0.25rem;
        }
        .order-card .order-total {
            font-size: 0.85rem;
            font-weight: 700;
            color: var(--orange-500);
            margin-top: 0.35rem;
        }
        .order-card .order-meta {
            font-size: 0.65rem;
            color: var(--app-muted);
            margin-top: 0.4rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .queue-empty {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            color: var(--app-muted);
            font-size: 0.75rem;
            padding: 2rem 0;
        }

        .monitor-banner {
            margin: 0.75rem 1.25rem 0;
            border: 1px solid #FED7AA;
            background: linear-gradient(135deg, #FFF7ED 0%, #FFFFFF 100%);
            border-radius: 1rem;
            padding: 0.85rem 1rem;
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            align-items: center;
            box-shadow: var(--app-soft-shadow);
        }
        .monitor-banner strong { color: #1C1917; }
        .monitor-banner span { color: var(--app-muted); font-size: 0.75rem; }

        .monitor-state {
            margin: 0 1.25rem 1rem;
            border-radius: 1rem;
            padding: 0.9rem 1rem;
            font-size: 0.8rem;
            border: 1px solid var(--app-border);
            background: white;
            box-shadow: var(--app-soft-shadow);
        }
        .monitor-state.is-loading { color: #9A3412; border-color: #FED7AA; background: #FFF7ED; }
        .monitor-state.is-error { color: #991B1B; border-color: #FECACA; background: #FEF2F2; }
        .monitor-state.is-hidden { display: none; }

        .order-card {
            cursor: pointer;
            position: relative;
        }
        .order-card:hover {
            border-color: #FDBA74;
            box-shadow: 0 10px 30px rgba(249, 115, 22, 0.12);
            transform: translateY(-1px);
        }
        .order-card .card-hint {
            margin-top: 0.4rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            font-size: 0.65rem;
            color: var(--app-muted);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .queue-grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            .stats-bar { flex-wrap: wrap; }
            .stat-card { min-width: calc(50% - 0.5rem); }
        }
    </style>
</head>
<body>
    <header class="monitor-header">
        <div class="monitor-brand">
            <div class="monitor-badge">
                <span class="material-symbols-outlined" style="font-size:18px;">storefront</span>
            </div>
            <div class="min-w-0">
                <h1>{{ $tenantName ?? 'Kala Kasir' }}</h1>
                <div class="subline">Monitor pesanan real-time</div>
            </div>
        </div>
        <div class="clock" id="clock">--:--:--</div>
    </header>

    <div class="monitor-banner">
        <div>
            <strong>Levia Bakery Order Board</strong><br />
            <span id="last-refresh">Memuat data...</span>
        </div>
        <div class="text-right">
            <strong id="monitor-total-orders">0</strong><br />
            <span>Total pesanan</span>
        </div>
    </div>

    <div id="monitor-state" class="monitor-state is-loading">Memuat antrian pesanan...</div>

    <div class="stats-bar">
        <div class="stat-card stat-pending">
            <div class="stat-num" id="count-pending">0</div>
            <div class="stat-label">Menunggu</div>
        </div>
        <div class="stat-card stat-progress">
            <div class="stat-num" id="count-progress">0</div>
            <div class="stat-label">Diproses</div>
        </div>
        <div class="stat-card stat-done">
            <div class="stat-num" id="count-done">0</div>
            <div class="stat-label">Selesai</div>
        </div>
    </div>

    <div class="queue-grid">
        {{-- Pending --}}
        <div class="queue-col col-pending">
            <div class="queue-col-header">
                <span class="material-symbols-outlined" style="font-size:16px;">pending</span>
                Menunggu
            </div>
            <div class="queue-col-body" id="col-pending">
                <div class="queue-empty" id="empty-pending">Belum ada pesanan</div>
            </div>
        </div>

        {{-- In Progress --}}
        <div class="queue-col col-progress">
            <div class="queue-col-header">
                <span class="material-symbols-outlined" style="font-size:16px;">soup_kitchen</span>
                Sedang Diproses
            </div>
            <div class="queue-col-body" id="col-progress">
                <div class="queue-empty" id="empty-progress">Belum ada pesanan</div>
            </div>
        </div>

        {{-- Done --}}
        <div class="queue-col col-done">
            <div class="queue-col-header">
                <span class="material-symbols-outlined" style="font-size:16px;">check_circle</span>
                Selesai
            </div>
            <div class="queue-col-body" id="col-done">
                <div class="queue-empty" id="empty-done">Belum ada pesanan</div>
            </div>
        </div>
    </div>

    <script>
    (function(){
        const $ = id => document.getElementById(id);
        const formatMoney = (value) => Number(value || 0).toLocaleString('id-ID');

        function updateClock() {
            const now = new Date();
            $('clock').textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        setInterval(updateClock, 1000);
        updateClock();

        function renderOrders(payload) {
            const orders = payload.orders || {};
            const stats = payload.stats || {};
            const groups = {
                pending: [...(orders.menunggu_ongkir || []), ...(orders.menunggu_pembayaran || [])],
                progress: [...(orders.pembayaran_terverifikasi || []), ...(orders.diproses || [])],
                done: [...(orders.siap_diambil || []), ...(orders.siap_dikirim || []), ...(orders.selesai || [])],
            };

            ['pending','progress','done'].forEach(status => {
                const col = $('col-' + status);
                const empty = $('empty-' + status);
                col.querySelectorAll('.order-card').forEach(el => el.remove());

                $('count-' + status).textContent = status === 'pending'
                    ? (stats.menunggu_ongkir || 0) + (stats.menunggu_pembayaran || 0)
                    : status === 'progress'
                        ? (stats.pembayaran_terverifikasi || 0) + (stats.diproses || 0)
                        : (stats.siap_diambil || 0) + (stats.siap_dikirim || 0) + (stats.selesai || 0);

                if (groups[status].length === 0) {
                    empty.style.display = '';
                    return;
                }
                empty.style.display = 'none';

                groups[status].forEach(o => {
                    const card = document.createElement('div');
                    card.className = 'order-card new-entry';
                    card.setAttribute('role', 'link');
                    card.setAttribute('tabindex', '0');
                    const items = (o.items || []).map(i => i.qty + 'x ' + (i.product_name || i.name || '-')).join(', ');
                    const fulfillment = [o.fulfillment_type, o.fulfillment_time].filter(Boolean).join(' · ');
                    const orderCode = String(o.order_code || o.code || o.id);
                    const pinUrl = `/admin-order/${encodeURIComponent(orderCode)}/pin`;
                    card.innerHTML = `
                        <div class="order-code">#${o.order_code || o.code || o.id}</div>
                        <div class="order-customer">${o.customer_name || '-'}</div>
                        <div class="order-items">${items}</div>
                        <div class="order-total">Rp ${(o.total || o.grand_total || 0).toLocaleString('id-ID')}</div>
                        <div class="order-meta">
                            <span>${fulfillment || 'Tanpa jadwal'}</span>
                            <span>Tap untuk PIN</span>
                        </div>`;
                    card.addEventListener('click', () => window.location.href = pinUrl);
                    card.addEventListener('keydown', (event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                            event.preventDefault();
                            window.location.href = pinUrl;
                        }
                    });
                    col.appendChild(card);
                });
            });
        }

        async function refresh() {
            try {
                $('monitor-state').className = 'monitor-state is-loading';
                $('monitor-state').textContent = 'Memuat antrian pesanan...';
                const res = await fetch('{{ url("/monitor/orders/data") }}', {
                    headers: { 'Accept': 'application/json' }
                });
                if (res.ok) {
                    const data = await res.json();
                    $('monitor-state').className = 'monitor-state is-hidden';
                    $('monitor-total-orders').textContent = String(data?.stats?.total || 0);
                    $('last-refresh').textContent = 'Pembaruan terakhir ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
                    renderOrders(data);
                } else {
                    throw new Error('Gagal memuat data monitor.');
                }
            } catch(e) { /* silently retry */ }
        }

        refresh();
        setInterval(refresh, 10000);
    })();
    </script>
</body>
</html>
