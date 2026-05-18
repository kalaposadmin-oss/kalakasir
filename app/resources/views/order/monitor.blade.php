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

        /* Header */
        .monitor-header {
            position: sticky;
            top: 0;
            z-index: 10;
            background: linear-gradient(135deg, #1C1917 0%, #292524 100%);
            color: white;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .monitor-header h1 {
            font-size: 1.25rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .monitor-header .clock {
            font-family: 'JetBrains Mono', monospace;
            font-size: 1.5rem;
            font-weight: 500;
            color: var(--orange-500);
        }

        /* Stats bar */
        .stats-bar {
            display: flex;
            gap: 1rem;
            padding: 1rem 2rem;
            background: white;
            border-bottom: 1px solid var(--app-border);
        }
        .stat-card {
            flex: 1;
            text-align: center;
            padding: 0.75rem;
            border-radius: 0.75rem;
            background: var(--app-bg);
        }
        .stat-card .stat-num {
            font-size: 1.75rem;
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
            padding: 1rem 2rem 2rem;
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
            border-radius: 0.75rem 0.75rem 0 0;
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
            border-radius: 0 0 0.75rem 0.75rem;
            background: white;
            padding: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
            overflow-y: auto;
        }

        /* Order card */
        .order-card {
            border: 1px solid var(--app-border);
            border-radius: 0.75rem;
            padding: 0.75rem;
            transition: all 0.3s ease;
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
        .order-card .order-time {
            font-size: 0.65rem;
            color: var(--app-muted);
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
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
        <div>
            <h1>{{ $tenantName ?? 'Kala Kasir' }}</h1>
            <p style="font-size: 0.7rem; color: #A8A29E;">Monitor Pesanan Real-time</p>
        </div>
        <div class="clock" id="clock">--:--:--</div>
    </header>

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

        function updateClock() {
            const now = new Date();
            $('clock').textContent = now.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        }
        setInterval(updateClock, 1000);
        updateClock();

        function renderOrders(orders) {
            const groups = { pending: [], progress: [], done: [] };
            (orders || []).forEach(o => {
                const status = o.status === 'confirmed' ? 'progress' : o.status === 'done' ? 'done' : 'pending';
                groups[status].push(o);
            });

            ['pending','progress','done'].forEach(status => {
                const col = $('col-' + status);
                const empty = $('empty-' + status);
                col.querySelectorAll('.order-card').forEach(el => el.remove());

                $('count-' + status).textContent = groups[status].length;

                if (groups[status].length === 0) {
                    empty.style.display = '';
                    return;
                }
                empty.style.display = 'none';

                groups[status].forEach(o => {
                    const card = document.createElement('div');
                    card.className = 'order-card new-entry';
                    const items = (o.items || []).map(i => i.qty + 'x ' + i.name).join(', ');
                    const time = o.created_at ? new Date(o.created_at).toLocaleTimeString('id-ID', { hour:'2-digit', minute:'2-digit' }) : '';
                    card.innerHTML = `
                        <div class="order-code">#${o.code || o.id}</div>
                        <div class="order-customer">${o.customer_name || '-'}</div>
                        <div class="order-items">${items}</div>
                        <div class="order-total">Rp ${(o.total || 0).toLocaleString('id-ID')}</div>
                        <div class="order-time">
                            <span class="material-symbols-outlined" style="font-size:12px;">schedule</span>
                            ${time}
                        </div>`;
                    col.appendChild(card);
                });
            });
        }

        async function refresh() {
            try {
                const res = await fetch('{{ url("/api/online-orders/monitor") }}', {
                    headers: { 'Accept': 'application/json', 'X-Tenant-ID': '{{ $tenantId ?? "" }}' }
                });
                if (res.ok) {
                    const data = await res.json();
                    renderOrders(data.data || data);
                }
            } catch(e) { /* silently retry */ }
        }

        refresh();
        setInterval(refresh, 10000);
    })();
    </script>
</body>
</html>
