/**
 * IntuiFy Admin — Server Control Center UI.
 *
 * Reads JSON from /admin/api/server-control.php only (never Prometheus), refreshes
 * on the intervals of spec §30, shows "Last update" and a stale warning, and keeps
 * "monitoring unavailable" (grey, Unknown) separate from a real server problem.
 */
(() => {
    'use strict';

    const root = document.getElementById('scc');
    if (!root) return;

    const VIEW = root.dataset.view || 'overview';
    const API = '/admin/api/server-control.php';
    const REFRESH_MS = { overview: 10000, server: 30000, containers: 10000, domains: 30000, alerts: 15000 };
    const CHART_REFRESH_MS = 30000;
    const STALE_AFTER_S = 60;

    const LEVELS = {
        healthy:  { label: 'Healthy',  icon: '✓' },
        warning:  { label: 'Warning',  icon: '!' },
        critical: { label: 'Critical', icon: '✕' },
        unknown:  { label: 'Unknown',  icon: '?' },
    };
    const HEADLINE = {
        healthy:  'Infrastruttura in salute',
        warning:  'Attenzione richiesta',
        critical: 'Intervento necessario',
        unknown:  'Stato sconosciuto',
    };
    const UNAVAILABLE_TEXT = {
        not_configured: 'Il monitoring non è ancora configurato: imposta <strong>PROMETHEUS_URL</strong> nelle variabili d\'ambiente dell\'app (Dokploy) e collega Prometheus alla stessa rete Docker.',
        unreachable:    'Prometheus non è raggiungibile dalla rete interna.',
        bad_response:   'Il monitoring ha risposto in modo inatteso.',
        query_failed:   'Una query al monitoring è fallita.',
        internal_error: 'Errore interno durante la lettura del monitoring.',
    };

    const $ = (sel) => root.querySelector(sel);
    const body = $('#scc-body');

    // ---------------------------------------------------------------- format --
    const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const nf1 = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 1 });
    const nf0 = new Intl.NumberFormat('it-IT', { maximumFractionDigits: 0 });
    const isNum = (v) => typeof v === 'number' && Number.isFinite(v);
    const dash = '—';

    const fmtPct = (v) => (isNum(v) ? nf1.format(v) + '%' : dash);
    const fmtBytes = (b) => {
        if (!isNum(b)) return dash;
        const units = ['B', 'KB', 'MB', 'GB', 'TB'];
        let i = 0;
        let v = b;
        while (v >= 1024 && i < units.length - 1) { v /= 1024; i++; }
        return nf1.format(v) + ' ' + units[i];
    };
    const fmtMbps = (bytesPerSecond) => (isNum(bytesPerSecond) ? nf1.format((bytesPerSecond * 8) / 1e6) + ' Mbps' : dash);
    const fmtRate = (bytesPerSecond) => (isNum(bytesPerSecond) ? fmtBytes(bytesPerSecond) + '/s' : dash);
    const fmtDuration = (s) => {
        if (!isNum(s)) return dash;
        const d = Math.floor(s / 86400);
        const h = Math.floor((s % 86400) / 3600);
        const m = Math.floor((s % 3600) / 60);
        if (d > 0) return `${d} g ${h} h`;
        if (h > 0) return `${h} h ${m} min`;
        return `${m} min`;
    };
    const fmtDate = (iso) => (iso ? new Date(iso).toLocaleString('it-IT', { dateStyle: 'short', timeStyle: 'short' }) : dash);
    const fmtAgo = (iso) => {
        if (!iso) return dash;
        const s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
        return s < 60 ? `${s} s fa` : s < 3600 ? `${Math.round(s / 60)} min fa` : fmtDuration(s) + ' fa';
    };

    const badge = (level, text) => {
        const l = LEVELS[level] ? level : 'unknown';
        return `<span class="scc-badge scc-${l}"><span class="scc-icon" aria-hidden="true">${LEVELS[l].icon}</span>${esc(text ?? LEVELS[l].label)}</span>`;
    };
    const meter = (pct, level) =>
        `<div class="scc-meter" data-level="${esc(level)}" role="img" aria-label="${esc(fmtPct(pct))}"><span style="width:${isNum(pct) ? Math.min(100, Math.max(0, pct)) : 0}%"></span></div>`;
    const card = ({ label, value, sub = '', level = 'unknown', pct = null, extra = '' }) => `
        <div class="scc-card" data-level="${esc(level)}">
            <div class="scc-card-label"><span>${esc(label)}</span>${level !== 'unknown' || pct !== null ? badge(level) : ''}</div>
            <div class="scc-card-value">${value}</div>
            ${sub ? `<div class="scc-card-sub">${sub}</div>` : ''}
            ${pct !== null ? meter(pct, level) : ''}
            ${extra}
        </div>`;
    const panel = (title, inner, actions = '') => `
        <div class="card scc-panel">
            <div class="card-header"><h3 class="card-title">${esc(title)}</h3>${actions}</div>
            ${inner}
        </div>`;
    // Content wrapped in .scc-cell so the mobile card layout (label | value) stays two columns
    const td = (label, html, cls = '') => `<td data-label="${esc(label)}"${cls ? ` class="${cls}"` : ''}><div class="scc-cell">${html}</div></td>`;

    // ------------------------------------------------------------ freshness --
    let lastGeneratedAt = null;
    let lastFetchOk = false;

    function tick() {
        const updated = $('#scc-updated');
        const stale = $('#scc-stale');
        if (!lastGeneratedAt) return;
        const age = (Date.now() - new Date(lastGeneratedAt).getTime()) / 1000;
        updated.textContent = 'Ultimo aggiornamento: ' + fmtAgo(lastGeneratedAt);
        stale.hidden = age <= STALE_AFTER_S && lastFetchOk;
    }
    setInterval(tick, 1000);

    function setHeadline(level, text) {
        $('#scc-status').innerHTML = badge(level, text ?? HEADLINE[level] ?? HEADLINE.unknown);
    }

    function showUnavailable(reason) {
        const box = $('#scc-unavailable');
        if (!reason) {
            box.hidden = true;
            return;
        }
        box.hidden = false;
        box.innerHTML = `<span aria-hidden="true">ⓘ</span><div><strong>Monitoring non disponibile.</strong> ${UNAVAILABLE_TEXT[reason] || UNAVAILABLE_TEXT.internal_error}<br>Lo stato del server è <strong>sconosciuto</strong>: questo non significa che il server sia offline.</div>`;
        setHeadline('unknown', 'Monitoring unavailable');
    }

    // ----------------------------------------------------------------- fetch --
    async function api(params) {
        const res = await fetch(API + '?' + new URLSearchParams(params), {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });
        if (res.status === 401) {
            window.location.href = '/admin/index.php?expired=1';
            throw new Error('unauthenticated');
        }
        if (!res.ok) throw new Error('HTTP ' + res.status);
        return res.json();
    }

    // ---------------------------------------------------------------- charts --
    const CHART_COLORS = ['#818cf8', '#22d3ee', '#fbbf24'];
    const charts = {};
    let chartRange = '24h';

    const HOUR = 3600 * 1000;
    const TICK_STEP_MS = { '1h': HOUR / 6, '6h': HOUR, '24h': 4 * HOUR, '7d': 24 * HOUR, '30d': 5 * 24 * HOUR };

    const unitFormat = {
        percent: (v) => nf1.format(v) + '%',
        bytesPerSecond: (v) => fmtRate(v),
        load: (v) => nf1.format(v),
    };

    function chartsBlock(defs, title = 'Andamento') {
        const ranges = ['1h', '6h', '24h', '7d', '30d']
            .map((r) => `<button type="button" class="scc-range ${r === chartRange ? 'is-active' : ''}" data-range="${r}">${r.toUpperCase()}</button>`)
            .join('');
        const boxes = defs
            .map((d) => `<div><div class="scc-chart-title">${esc(d.title)}</div><div class="scc-chart-box"><canvas id="chart-${d.metric}" aria-label="${esc(d.title)}"></canvas></div></div>`)
            .join('');
        return panel(title, `<div class="scc-charts">${boxes}</div>`, `<div class="scc-ranges" role="group" aria-label="Intervallo">${ranges}</div>`);
    }

    function bindRangeButtons(defs) {
        body.querySelectorAll('.scc-range').forEach((btn) => {
            btn.addEventListener('click', () => {
                chartRange = btn.dataset.range;
                body.querySelectorAll('.scc-range').forEach((b) => b.classList.toggle('is-active', b === btn));
                loadCharts(defs);
            });
        });
    }

    async function loadCharts(defs) {
        if (typeof Chart === 'undefined') return;
        await Promise.all(defs.map(async (d) => {
            try {
                const resp = await api({ view: 'chart', metric: d.metric, range: chartRange });
                if (!resp.monitoring.available || !resp.data) return;
                drawChart(d.metric, resp.data);
            } catch (e) {
                console.warn('[scc] chart', d.metric, e);
            }
        }));
    }

    function drawChart(metric, data) {
        const canvas = document.getElementById('chart-' + metric);
        if (!canvas) return;
        const fmt = unitFormat[data.unit] || ((v) => nf1.format(v));
        const long = ['7d', '30d'].includes(data.range);
        const datasets = data.series.map((s, i) => ({
            label: s.label,
            data: s.points.filter((p) => p[1] !== null).map(([t, v]) => ({ x: t * 1000, y: v })),
            borderColor: CHART_COLORS[i % CHART_COLORS.length],
            backgroundColor: CHART_COLORS[i % CHART_COLORS.length] + '22',
            fill: data.series.length === 1,
            borderWidth: 1.75,
            pointRadius: 0,
            tension: 0.25,
        }));

        const stepSize = TICK_STEP_MS[data.range];

        if (charts[metric] && charts[metric].canvas === canvas) {
            charts[metric].data.datasets = datasets;
            charts[metric].options.scales.x.ticks.callback = tickFormatter(long);
            charts[metric].options.scales.x.ticks.stepSize = stepSize;
            charts[metric].update('none');
            return;
        }
        charts[metric] = new Chart(canvas, {
            type: 'line',
            data: { datasets },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: false,
                parsing: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: datasets.length > 1, labels: { color: '#94a3b8', boxWidth: 10, font: { size: 11 } } },
                    tooltip: {
                        callbacks: {
                            title: (items) => (items[0] ? new Date(items[0].parsed.x).toLocaleString('it-IT', { dateStyle: 'short', timeStyle: 'short' }) : ''),
                            label: (item) => `${item.dataset.label}: ${fmt(item.parsed.y)}`,
                        },
                    },
                },
                scales: {
                    x: {
                        type: 'linear',
                        bounds: 'data',
                        ticks: { color: '#64748b', stepSize, autoSkip: true, maxTicksLimit: 8, callback: tickFormatter(long) },
                        grid: { color: 'rgba(255,255,255,0.04)' },
                    },
                    y: {
                        beginAtZero: true,
                        suggestedMax: data.unit === 'percent' ? 100 : undefined,
                        ticks: { color: '#64748b', maxTicksLimit: 5, callback: (v) => fmt(v) },
                        grid: { color: 'rgba(255,255,255,0.04)' },
                    },
                },
            },
        });
    }

    function tickFormatter(long) {
        return (v) => new Date(v).toLocaleString('it-IT', long ? { day: '2-digit', month: '2-digit' } : { hour: '2-digit', minute: '2-digit' });
    }

    // ------------------------------------------------------------- renderers --
    const OVERVIEW_CHARTS = [
        { metric: 'cpu', title: 'CPU' },
        { metric: 'memory', title: 'Memoria' },
        { metric: 'disk_io', title: 'Disco I/O' },
        { metric: 'network', title: 'Traffico di rete' },
    ];
    const SERVER_CHARTS = [
        { metric: 'cpu', title: 'CPU' },
        { metric: 'memory', title: 'RAM' },
        { metric: 'network', title: 'Rete RX/TX' },
        { metric: 'disk_io', title: 'Disco lettura/scrittura' },
        { metric: 'load', title: 'Load average' },
    ];

    let skeletonReady = false;

    const renderers = {
        overview(d) {
            const h = d.host;
            const alertsLevel = d.alerts.critical > 0 ? 'critical' : d.alerts.warning > 0 ? 'warning' : 'healthy';
            const contLevel = d.containers.stoppedCritical > 0 ? 'critical' : d.containers.stopped > 0 ? 'warning' : 'healthy';
            const domLevel = d.domains.total === 0 ? 'unknown' : d.domains.online < d.domains.total ? 'critical' : d.domains.sslExpiring > 0 ? 'warning' : 'healthy';

            const cards = [
                card({ label: 'Server status', value: esc((LEVELS[d.status] || LEVELS.unknown).label), sub: 'Uptime ' + fmtDuration(d.host.uptimeSeconds), level: d.status }),
                card({ label: 'CPU', value: fmtPct(h.cpu.usagePercent), sub: (h.cpu.cores ?? dash) + ' core · load ' + (isNum(h.load.load1) ? nf1.format(h.load.load1) : dash), level: h.cpu.level, pct: h.cpu.usagePercent }),
                card({ label: 'Memoria', value: fmtBytes(h.memory.usedBytes), sub: 'su ' + fmtBytes(h.memory.totalBytes) + ' · ' + fmtPct(h.memory.usagePercent), level: h.memory.level, pct: h.memory.usagePercent }),
                card({ label: 'Disco /', value: fmtBytes(h.disk.usedBytes), sub: 'su ' + fmtBytes(h.disk.totalBytes) + ' · liberi ' + fmtBytes(h.disk.freeBytes), level: h.disk.level, pct: h.disk.usagePercent }),
                card({ label: 'Rete', value: '↓ ' + fmtMbps(h.network.rxBytesPerSecond), sub: '↑ ' + fmtMbps(h.network.txBytesPerSecond) }),
                card({ label: 'Container', value: nf0.format(d.containers.running) + ' running', sub: `${nf0.format(d.containers.stopped)} fermi · ${nf0.format(d.containers.stoppedCritical)} critici fermi · health n/d`, level: contLevel }),
                card({ label: 'Alert', value: `${nf0.format(d.alerts.critical)} critical`, sub: `${nf0.format(d.alerts.warning)} warning · ${nf0.format(d.alerts.pending)} in attesa`, level: alertsLevel }),
                card({ label: 'Domini', value: `${nf0.format(d.domains.online)}/${nf0.format(d.domains.total)} online`, sub: `${nf0.format(d.domains.sslExpiring)} SSL in scadenza`, level: domLevel }),
            ].join('');

            const attention = d.needsAttention.length
                ? d.needsAttention.map((a) => `
                    <a class="scc-list-row" href="?view=${esc(a.view)}">
                        <div><div class="scc-list-title">${esc(a.title)}</div><div class="scc-list-detail">${esc(a.detail)}</div></div>
                        ${badge(a.level)}
                    </a>`).join('')
                : `<div class="scc-list-row"><div><div class="scc-list-title">Nessun intervento critico richiesto</div><div class="scc-list-detail">Tutti i controlli sono nella norma</div></div>${badge('healthy', 'OK')}</div>`;

            const services = d.services.map((s) => `
                <div class="scc-list-row"><span>${esc(s.name)}${s.note ? ` <span class="scc-list-detail">(${esc(s.note)})</span>` : ''}</span>${badge(s.level)}</div>`).join('');

            const monitoring = Object.entries(d.monitoring).map(([job, t]) => `
                <div class="scc-list-row"><span>${esc(job)} <span class="scc-list-detail">${t.up}/${t.total}</span></span>${badge(t.level, t.level === 'healthy' ? 'UP' : 'DOWN')}</div>`).join('')
                || '<p class="scc-empty">Nessun target attivo.</p>';

            if (!skeletonReady) {
                body.innerHTML = `
                    <div class="scc-grid" id="scc-cards"></div>
                    <div class="scc-columns">
                        <div>${panel(`Needs attention (${d.needsAttention.length})`, '<div class="scc-list" id="scc-attention"></div>')}</div>
                        <div>${panel('Servizi critici', '<div class="scc-list" id="scc-services"></div>')}</div>
                    </div>
                    ${chartsBlock(OVERVIEW_CHARTS)}
                    ${panel('Monitoring del monitoring', '<div class="scc-list" id="scc-targets"></div>')}`;
                bindRangeButtons(OVERVIEW_CHARTS);
                skeletonReady = true;
                loadCharts(OVERVIEW_CHARTS);
            }
            $('#scc-cards').innerHTML = cards;
            $('#scc-attention').innerHTML = attention;
            $('#scc-attention').closest('.card').querySelector('.card-title').textContent = `Needs attention (${d.needsAttention.length})`;
            $('#scc-services').innerHTML = services;
            $('#scc-targets').innerHTML = monitoring;
            setHeadline(d.status);
        },

        server(d) {
            const h = d.host;
            const i = d.info;
            const info = [
                ['Hostname', i.hostname], ['Sistema operativo', i.os], ['Kernel', i.kernel], ['Architettura', i.arch],
                ['CPU', (h.cpu.cores ?? dash) + ' core'], ['RAM', fmtBytes(h.memory.totalBytes)], ['Swap', fmtBytes(h.memory.swapUsedBytes) + ' / ' + fmtBytes(h.memory.swapTotalBytes)],
                ['Disco /', fmtBytes(h.disk.totalBytes)], ['Docker', i.dockerVersion], ['cAdvisor', i.cadvisorVersion],
                ['Uptime', fmtDuration(h.uptimeSeconds)], ['Ultimo riavvio', fmtDate(h.bootTime)],
            ].map(([k, v]) => `<div><dt>${esc(k)}</dt><dd>${esc(v || dash)}</dd></div>`).join('');

            const cards = [
                card({ label: 'CPU', value: fmtPct(h.cpu.usagePercent), level: h.cpu.level, pct: h.cpu.usagePercent }),
                card({ label: 'RAM', value: fmtPct(h.memory.usagePercent), sub: fmtBytes(h.memory.usedBytes) + ' / ' + fmtBytes(h.memory.totalBytes), level: h.memory.level, pct: h.memory.usagePercent }),
                card({ label: 'Disco /', value: fmtPct(h.disk.usagePercent), sub: 'liberi ' + fmtBytes(h.disk.freeBytes), level: h.disk.level, pct: h.disk.usagePercent }),
                card({ label: 'Load', value: isNum(h.load.load1) ? nf1.format(h.load.load1) : dash, sub: `5m ${isNum(h.load.load5) ? nf1.format(h.load.load5) : dash} · 15m ${isNum(h.load.load15) ? nf1.format(h.load.load15) : dash} · ${isNum(h.load.perCore) ? nf1.format(h.load.perCore) : dash}/core`, level: h.load.level }),
                card({ label: 'Rete', value: '↓ ' + fmtMbps(h.network.rxBytesPerSecond), sub: '↑ ' + fmtMbps(h.network.txBytesPerSecond) }),
            ].join('');

            const fsRows = d.filesystems.map((f) => `<tr>
                ${td('Mount', `<span class="scc-strong">${esc(f.mount)}</span><div class="scc-muted">${esc(f.device)} · ${esc(f.fstype)}</div>`)}
                ${td('Totale', fmtBytes(f.totalBytes), 'num')}
                ${td('Usato', fmtBytes(f.usedBytes), 'num')}
                ${td('Libero', fmtBytes(f.freeBytes), 'num')}
                ${td('Uso', `${badge(f.level, fmtPct(f.usagePercent))}`, 'num')}
                ${td('Inode', fmtPct(f.inodePercent), 'num')}
            </tr>`).join('') || `<tr><td colspan="6" class="scc-empty">Nessun filesystem rilevato.</td></tr>`;

            const worst = [h.cpu.level, h.memory.level, h.disk.level, h.load.level].reduce((a, b) => (rank(b) > rank(a) ? b : a), 'unknown');

            if (!skeletonReady) {
                body.innerHTML = `
                    ${panel('Informazioni', '<dl class="scc-info" id="scc-info"></dl>')}
                    <div class="scc-grid" id="scc-cards"></div>
                    ${panel('Filesystem', `<div class="scc-table-wrap"><table class="scc-table"><thead><tr><th>Mount</th><th class="num">Totale</th><th class="num">Usato</th><th class="num">Libero</th><th class="num">Uso</th><th class="num">Inode</th></tr></thead><tbody id="scc-fs"></tbody></table></div>`)}
                    ${chartsBlock(SERVER_CHARTS, 'Grafici')}`;
                bindRangeButtons(SERVER_CHARTS);
                skeletonReady = true;
                loadCharts(SERVER_CHARTS);
            }
            $('#scc-info').innerHTML = info;
            $('#scc-cards').innerHTML = cards;
            $('#scc-fs').innerHTML = fsRows;
            setHeadline(worst);
        },

        containers(d) {
            if (!skeletonReady) {
                const filters = [['all', 'Tutti'], ['running', 'Running'], ['stopped', 'Fermi'], ['high-cpu', 'CPU alta'], ['high-ram', 'RAM alta']]
                    .map(([k, l]) => `<button type="button" class="scc-chip ${k === containerFilter ? 'is-active' : ''}" data-filter="${k}">${l}</button>`).join('')
                    + '<button type="button" class="scc-chip" disabled title="Lo stato health richiede l\'IntuiFy Server Agent (Fase 3)">Unhealthy · n/d</button>';
                body.innerHTML = `
                    <div class="scc-grid" id="scc-cards"></div>
                    ${panel('Container', `<div class="scc-table-wrap"><table class="scc-table"><thead><tr><th>Container</th><th>Progetto</th><th>Stato</th><th class="num">CPU</th><th class="num">RAM</th><th class="num">Rete ↓/↑</th><th class="num">Uptime</th></tr></thead><tbody id="scc-rows"></tbody></table></div>`,
                        `<div class="scc-toolbar"><input type="search" id="scc-search" class="form-input !py-1.5 !text-sm" placeholder="Cerca container…" style="min-width:12rem">${filters}</div>`)}`;
                body.querySelectorAll('.scc-chip[data-filter]').forEach((chip) => chip.addEventListener('click', () => {
                    containerFilter = chip.dataset.filter;
                    body.querySelectorAll('.scc-chip[data-filter]').forEach((c) => c.classList.toggle('is-active', c === chip));
                    drawContainerRows();
                }));
                $('#scc-search').addEventListener('input', drawContainerRows);
                skeletonReady = true;
            }
            containerData = d;
            const s = d.summary;
            $('#scc-cards').innerHTML = [
                card({ label: 'Running', value: nf0.format(s.running), level: s.running > 0 ? 'healthy' : 'critical' }),
                card({ label: 'Fermi (24h)', value: nf0.format(s.stopped), level: s.stopped > 0 ? 'warning' : 'healthy' }),
                card({ label: 'Critici fermi', value: nf0.format(s.stoppedCritical), level: s.stoppedCritical > 0 ? 'critical' : 'healthy' }),
                card({ label: 'Unhealthy', value: 'n/d', sub: 'Serve l\'IntuiFy Server Agent (Fase 3)' }),
            ].join('');
            drawContainerRows();
            setHeadline(s.stoppedCritical > 0 ? 'critical' : s.stopped > 0 ? 'warning' : 'healthy');
        },

        domains(d) {
            const rows = d.items.map((x) => `<tr>
                ${td('Dominio', `<a class="scc-strong hover:underline" href="${esc(x.url)}" target="_blank" rel="noopener noreferrer">${esc(x.host)}</a><div class="scc-muted">${esc(x.url)}</div>`)}
                ${td('Progetto', esc(x.project))}
                ${td('Stato', badge(x.level, x.online ? LEVELS[x.level].label : 'Offline'))}
                ${td('HTTP', x.httpStatus ? esc(x.httpStatus) : dash, 'num')}
                ${td('SSL', x.sslDaysLeft === null ? dash : badge(sslLevel(x.sslDaysLeft), nf0.format(x.sslDaysLeft) + ' g'), 'num')}
                ${td('Latenza', isNum(x.latencyMs) ? nf0.format(x.latencyMs) + ' ms' : dash, 'num')}
            </tr>`).join('') || '<tr><td colspan="6" class="scc-empty">Nessun endpoint configurato in Blackbox (prometheus.yml → job blackbox-http).</td></tr>';

            const s = d.summary;
            body.innerHTML = `
                <div class="scc-grid">
                    ${card({ label: 'Online', value: `${nf0.format(s.online)}/${nf0.format(s.total)}`, level: s.total === 0 ? 'unknown' : s.online < s.total ? 'critical' : 'healthy' })}
                    ${card({ label: 'SSL < 30 giorni', value: nf0.format(s.sslExpiring), level: s.sslExpiring > 0 ? 'warning' : 'healthy' })}
                </div>
                ${panel('Domini & SSL', `<div class="scc-table-wrap"><table class="scc-table"><thead><tr><th>Dominio</th><th>Progetto</th><th>Stato</th><th class="num">HTTP</th><th class="num">SSL</th><th class="num">Latenza</th></tr></thead><tbody>${rows}</tbody></table></div>`,
                    '<a href="/admin/domains.php" class="btn btn-secondary btn-sm">Scadenze registrar →</a>')}`;
            setHeadline(d.items.reduce((w, x) => (rank(x.level) > rank(w) ? x.level : w), s.total ? 'healthy' : 'unknown'));
        },

        alerts(d) {
            const rows = d.items.map((a) => `<tr>
                ${td('Severità', badge(a.severity))}
                ${td('Alert', `<span class="scc-strong">${esc(a.name)}</span><div class="scc-muted">${esc(a.summary)}</div>`)}
                ${td('Servizio', esc(a.service || dash))}
                ${td('Inizio', fmtDate(a.since))}
                ${td('Durata', a.since ? fmtAgo(a.since).replace(' fa', '') : dash)}
                ${td('Stato', a.state === 'firing' ? 'Attivo' : 'In attesa')}
                ${td('Azioni', '<button type="button" class="scc-chip" disabled title="Acknowledge e Silence arrivano in Fase 2 (Alertmanager + audit log)">Ack / Silence</button>')}
            </tr>`).join('') || '<tr><td colspan="7" class="scc-empty">Nessun alert attivo.</td></tr>';

            const s = d.summary;
            body.innerHTML = `
                <div class="scc-grid">
                    ${card({ label: 'Critical', value: nf0.format(s.critical), level: s.critical > 0 ? 'critical' : 'healthy' })}
                    ${card({ label: 'Warning', value: nf0.format(s.warning), level: s.warning > 0 ? 'warning' : 'healthy' })}
                    ${card({ label: 'In attesa', value: nf0.format(s.pending), sub: 'Condizione vera ma non ancora per la durata richiesta' })}
                </div>
                ${panel('Alert attivi', `<div class="scc-table-wrap"><table class="scc-table"><thead><tr><th>Severità</th><th>Alert</th><th>Servizio</th><th>Inizio</th><th>Durata</th><th>Stato</th><th>Azioni</th></tr></thead><tbody>${rows}</tbody></table></div>`)}`;
            setHeadline(s.critical > 0 ? 'critical' : s.warning > 0 ? 'warning' : 'healthy', s.critical + s.warning === 0 ? 'Nessun alert attivo' : undefined);
        },
    };

    const rank = (l) => ({ critical: 3, warning: 2, healthy: 1 }[l] || 0);
    const sslLevel = (days) => (days < 7 ? 'critical' : days < 30 ? 'warning' : 'healthy');

    // Containers keep filter/search state between refreshes
    let containerFilter = 'all';
    let containerData = null;
    const HIGH_CPU = 50;                 // % of one core
    const HIGH_RAM = 1024 ** 3;          // 1 GB

    function drawContainerRows() {
        if (!containerData) return;
        const q = ($('#scc-search')?.value || '').toLowerCase();
        const rows = containerData.items.filter((c) => {
            if (q && !(`${c.name} ${c.service} ${c.project} ${c.image}`.toLowerCase().includes(q))) return false;
            switch (containerFilter) {
                case 'running': return c.status === 'running';
                case 'stopped': return c.status === 'stopped';
                case 'high-cpu': return (c.cpuPercent ?? 0) >= HIGH_CPU;
                case 'high-ram': return (c.memoryBytes ?? 0) >= HIGH_RAM;
                default: return true;
            }
        }).map((c) => `<tr>
            ${td('Container', `<span class="scc-strong">${esc(c.service)}</span><div class="scc-muted" title="${esc(c.image)}">${esc(c.name)}</div>`)}
            ${td('Progetto', esc(c.project))}
            ${td('Stato', c.status === 'running' ? badge('healthy', 'Running') : badge('warning', 'Fermo'))}
            ${td('CPU', isNum(c.cpuPercent) ? nf1.format(c.cpuPercent) + '%' : dash, 'num')}
            ${td('RAM', fmtBytes(c.memoryBytes), 'num')}
            ${td('Rete ↓/↑', `${fmtRate(c.rxBytesPerSecond)} / ${fmtRate(c.txBytesPerSecond)}`, 'num')}
            ${td('Uptime', c.status === 'running' ? fmtDuration(c.uptimeSeconds) : 'visto ' + fmtAgo(c.lastSeen), 'num')}
        </tr>`).join('');
        $('#scc-rows').innerHTML = rows || '<tr><td colspan="7" class="scc-empty">Nessun container corrisponde al filtro.</td></tr>';
    }

    // ------------------------------------------------------------------ loop --
    let timer = null;
    let chartTimer = null;

    async function refresh() {
        try {
            const resp = await api({ view: VIEW });
            lastFetchOk = true;
            if (!resp.monitoring.available || !resp.data) {
                showUnavailable(resp.monitoring.reason || 'internal_error');
                if (!skeletonReady) {
                    body.innerHTML = '<div class="card"><p class="scc-empty">Nessun dato disponibile finché il monitoring non risponde.</p></div>';
                }
                lastGeneratedAt = new Date().toISOString();
                lastFetchOk = false;
            } else {
                showUnavailable(null);
                lastGeneratedAt = resp.data.generatedAt;
                renderers[VIEW](resp.data);
            }
        } catch (e) {
            console.warn('[scc] refresh failed', e);
            lastFetchOk = false;
            if (!lastGeneratedAt) {
                showUnavailable('internal_error');
            }
        }
        tick();
    }

    function schedule() {
        clearInterval(timer);
        clearInterval(chartTimer);
        if (document.hidden) return;
        timer = setInterval(refresh, REFRESH_MS[VIEW] || 15000);
        if (VIEW === 'overview' || VIEW === 'server') {
            const defs = VIEW === 'overview' ? OVERVIEW_CHARTS : SERVER_CHARTS;
            chartTimer = setInterval(() => skeletonReady && loadCharts(defs), CHART_REFRESH_MS);
        }
    }

    // Pause polling in background tabs, catch up immediately when visible again
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refresh();
        schedule();
    });

    refresh();
    schedule();
})();
