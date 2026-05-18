@push('head')
    <style>
        :root {
            --monitor-bg: #f5f1e8;
            --monitor-ink: #1f2937;
            --monitor-muted: #6b7280;
            --monitor-surface: rgba(255, 255, 255, 0.88);
            --monitor-line: rgba(148, 163, 184, 0.22);
            --monitor-hero: linear-gradient(135deg, #132c1b 0%, #264653 48%, #8d5524 100%);
            --monitor-warning: #c26a1b;
            --monitor-danger: #b42318;
            --monitor-ok: #157f5c;
            --monitor-neutral: #6b7280;
        }

        .monitor-shell {
            position: relative;
            overflow: hidden;
        }

        .monitor-shell::before,
        .monitor-shell::after {
            content: '';
            position: absolute;
            inset: auto;
            border-radius: 9999px;
            filter: blur(40px);
            opacity: 0.2;
            pointer-events: none;
        }

        .monitor-shell::before {
            width: 18rem;
            height: 18rem;
            top: -4rem;
            right: -6rem;
            background: rgba(194, 106, 27, 0.45);
        }

        .monitor-shell::after {
            width: 16rem;
            height: 16rem;
            left: -5rem;
            bottom: 5rem;
            background: rgba(21, 127, 92, 0.3);
        }

        .monitor-glass {
            background: var(--monitor-surface);
            backdrop-filter: blur(18px);
        }

        .monitor-grid {
            background-image: linear-gradient(to right, rgba(148, 163, 184, 0.08) 1px, transparent 1px), linear-gradient(to bottom, rgba(148, 163, 184, 0.08) 1px, transparent 1px);
            background-size: 22px 22px;
        }

        .monitor-scrollbar::-webkit-scrollbar {
            width: 10px;
            height: 10px;
        }

        .monitor-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(148, 163, 184, 0.4);
            border-radius: 9999px;
        }
    </style>
@endpush