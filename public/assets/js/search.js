/*
 * Ricerca valore: plan → coda di tabelle → WORKERS richieste parallele a lotti.
 * Il server elabora ogni lotto per pochi secondi e restituisce le tabelle fatte; le altre tornano in coda.
 */
(function () {
    'use strict';

    const WORKERS = 2;
    const $ = id => document.getElementById(id);
    const form = $('search-form');
    const BATCH = +form.dataset.batch || 20;
    const MAX_HITS = +form.dataset.maxHits || 500;
    const E = Matriosga.esc;
    const tbody = document.querySelector('#results tbody');

    let run = null; // stato della ricerca in corso

    function criteria() {
        const fd = new FormData(form);
        return {
            value: fd.get('value').trim(),
            mode: fd.get('mode'),
            numbers: fd.has('numbers'),
            views: fd.has('views'),
            force: fd.has('force'),
            all: fd.has('all'),
        };
    }

    function setRunning(on) {
        $('btn-search').classList.toggle('d-none', on);
        $('btn-stop').classList.toggle('d-none', !on);
        form.querySelectorAll('input').forEach(i => i.disabled = on);
    }

    function updateStatus() {
        const r = run;
        const pct = r.total ? Math.round(r.done / r.total * 100) : 100;
        $('status-bar').style.width = pct + '%';
        $('status-text').textContent = `${r.finished ? (r.stopped ? 'Interrotta' : 'Completata') : 'Ricerca in corso…'} — tabelle analizzate ${Matriosga.fmt(r.done)} / ${Matriosga.fmt(r.total)}`;
        $('status-time').textContent = ((performance.now() - r.start) / 1000).toFixed(1) + ' s';
        $('results-summary').textContent = `${Matriosga.fmt(r.hits)} colonne in ${Matriosga.fmt(r.tables.size)} tabelle`;
    }

    function addHits(hits) {
        for (const h of hits) {
            run.hits++;
            run.tables.add(h.table);
            const tr = document.createElement('tr');
            tr.innerHTML = `<td data-value="${E(h.table)}">${Matriosga.tableLink(h.table)}</td>
                <td class="text-nowrap" data-value="${E(h.column)}"><span class="ident fw-semibold">${E(h.column)}</span>${Matriosga.copyBtn(h.column)}</td>
                <td data-value="${E(h.type)}"><span class="badge badge-type">${E(h.type)}</span></td>
                <td class="text-end fw-semibold" data-value="${h.count}">${Matriosga.fmt(h.count)}</td>
                <td class="text-end text-secondary" data-value="${h.rows ?? -1}">${Matriosga.fmt(h.rows)}</td>
                <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary text-nowrap" data-rows-table="${E(h.table)}" data-rows-column="${E(h.column)}"><i class="fa-solid fa-eye"></i> Righe</button></td>`;
            tbody.appendChild(tr);
        }
        $('results-card').classList.toggle('d-none', run.hits === 0);
        // riapplica il filtro testuale ai nuovi risultati
        document.querySelector('[data-filter-table="#results"]').dispatchEvent(new Event('input'));
    }

    function note(html, type = 'warning') {
        $('status-notes').insertAdjacentHTML('beforeend', `<div class="alert alert-${type} py-2 small mb-2">${html}</div>`);
    }

    async function worker() {
        while (!run.stopped && run.queue.length) {
            if (run.hits >= MAX_HITS) {
                run.stopped = true;
                note(`Raggiunto il limite di ${MAX_HITS} colonne trovate: restringi la ricerca (es. modalità «uguale a»).`);
                break;
            }
            const batch = run.queue.splice(0, BATCH);
            let data;
            try {
                data = await Matriosga.api('/api/search/run', { body: { ...run.crit, tables: batch }, signal: run.abort.signal });
            } catch (e) {
                if (run.stopped) return;
                note('Errore: ' + E(e.message), 'danger');
                run.done += batch.length;
                continue;
            }
            const done = new Set(data.done.map(t => t.toLowerCase()));
            const rest = batch.filter(t => !done.has(t.toLowerCase()));
            run.queue.unshift(...rest);
            run.done += data.done.length;
            if (!data.done.length) run.done += rest.splice(0).length; // sicurezza: evita cicli infiniti
            addHits(data.hits);
            data.errors.forEach(([t, msg]) => run.errors.push(`<span class="ident">${E(t)}</span>: ${E(msg)}`));
            updateStatus();
        }
    }

    async function start(ev) {
        ev?.preventDefault();
        const crit = criteria();
        if (!crit.value) return;
        const url = new URL(location.href);
        url.searchParams.set('q', crit.value);
        url.searchParams.set('mode', crit.mode);
        history.replaceState(null, '', url);

        tbody.innerHTML = '';
        $('status-notes').innerHTML = '';
        $('results-empty').classList.add('d-none');
        $('results-card').classList.add('d-none');
        $('search-status').classList.remove('d-none');
        setRunning(true);
        run = { crit, queue: [], total: 0, done: 0, hits: 0, tables: new Set(), errors: [], start: performance.now(), stopped: false, finished: false, abort: new AbortController() };
        const timer = setInterval(updateStatus, 500);

        try {
            $('status-text').textContent = 'Preparazione: scelta delle colonne compatibili…';
            const plan = await Matriosga.api('/api/search/plan', { body: crit, signal: run.abort.signal });
            run.queue = plan.tables.map(t => t[0]);
            run.total = run.queue.length;
            if (plan.skipped.length) {
                note(`Saltate ${plan.skipped.length} tabelle con più di ${Matriosga.fmt(plan.maxRows)} righe
                    (es. ${plan.skipped.slice(-3).map(s => `<span class="ident">${E(s[0])}</span> ${Matriosga.fmt(s[1])}`).join(', ')}).
                    Attiva «Anche tabelle oltre…» per includerle.`, 'info');
            }
            if (!run.total) note('Nessuna colonna compatibile con questo valore (tipo o lunghezza).', 'info');
            updateStatus();
            await Promise.all(Array.from({ length: WORKERS }, worker));
        } catch (e) {
            if (!run.stopped) note(E(e.message), 'danger');
        } finally {
            clearInterval(timer);
            run.finished = true;
            setRunning(false);
            updateStatus();
            if (run.errors.length) {
                note(`<details><summary>${run.errors.length} tabelle non analizzate (timeout o errore)</summary><div class="mt-1">${run.errors.join('<br>')}</div></details>`);
            }
            $('results-empty').classList.toggle('d-none', run.hits > 0 || run.stopped);
        }
    }

    function stop() {
        if (!run) return;
        run.stopped = true;
        run.queue = [];
        run.abort.abort();
    }

    // Finestra "righe trovate"
    const modal = new bootstrap.Modal($('rows-modal'));
    document.addEventListener('click', async ev => {
        const btn = ev.target.closest('[data-rows-table]');
        if (!btn) return;
        const t = btn.dataset.rowsTable, c = btn.dataset.rowsColumn;
        $('rows-title').textContent = `${t}.${c}`;
        $('rows-body').innerHTML = '<div class="text-secondary"><span class="spinner-border spinner-border-sm"></span> Caricamento…</div>';
        modal.show();
        try {
            const d = await Matriosga.api('/api/search/rows', { body: { ...run.crit, t, c } });
            $('rows-body').innerHTML = '<div id="rows-grid" class="mb-3"></div>' + Matriosga.codeBlock('SQL', d.sql, 'fa-database') + Matriosga.codeBlock('Power Query', d.pq, 'fa-chart-simple');
            Matriosga.renderDataTable($('rows-grid'), d);
        } catch (e) {
            $('rows-body').innerHTML = `<div class="alert alert-danger">${E(e.message)}</div>`;
        }
    });

    form.addEventListener('submit', start);
    $('btn-stop').addEventListener('click', stop);
    if ($('value').value.trim()) start(); // arrivo da un link con ?q=
})();
