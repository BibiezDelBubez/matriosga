/* Analisi colonna: scelta tabella/colonna, chiamata /api/analysis, resa di statistiche, valori frequenti e distribuzione. */
(function () {
    'use strict';

    const $ = id => document.getElementById(id);
    const E = Matriosga.esc, F = Matriosga.fmt;
    const tableInput = $('an-table'), colSelect = $('an-column'), out = $('an-result');

    async function loadColumns(selected = '') {
        const t = tableInput.value.trim();
        if (!t) return;
        colSelect.disabled = true;
        try {
            const d = await Matriosga.api('/api/tables/columns', { params: { t } });
            tableInput.value = d.table;
            colSelect.innerHTML = '<option value="">— scegli —</option>' + d.columns.map(c =>
                `<option value="${E(c.name)}"${c.name.toLowerCase() === selected.toLowerCase() ? ' selected' : ''}${['binary', 'other'].includes(c.category) ? ' disabled' : ''}>${E(c.name)} · ${E(c.type)}</option>`).join('');
            colSelect.disabled = false;
        } catch (e) {
            colSelect.innerHTML = '<option value="">Tabella non trovata</option>';
        }
    }

    const pct = (n, total) => total ? (n / total * 100).toLocaleString('it-IT', { maximumFractionDigits: 1 }) + '%' : '—';
    const card = (label, value, sub = '') => `<div class="col-6 col-md-4 col-xl-2"><div class="card stat-card h-100">
        <div class="stat-label">${E(label)}</div><div class="stat-value fs-5 text-break">${value}</div>${sub ? `<div class="small text-secondary">${sub}</div>` : ''}</div></div>`;
    const bars = (rows, total) => {
        const max = Math.max(...rows.map(r => r.n), 1);
        return rows.map(r => `<div class="bar-row"><span class="bar-label ident">${E(r.label)}</span>
            <span class="bar-track"><span class="bar-fill" style="width:${(r.n / max * 100).toFixed(1)}%"></span></span>
            <span class="bar-value">${F(r.n)} <span class="text-secondary">${pct(r.n, total)}</span></span></div>`).join('');
    };

    function render(d) {
        const s = d.stats, total = +s.total, nonNull = +s.non_null, nulls = total - nonNull, distinct = +s.distinct_count;
        const isKey = distinct === nonNull && nulls === 0 && total > 0;
        let cards = card('Righe', F(total)) + card('Non NULL', F(nonNull)) + card('NULL', F(nulls), pct(nulls, total))
            + card('Valori distinti', F(distinct), isKey ? '<span class="badge text-bg-success">tutti diversi: possibile chiave</span>' : pct(distinct, nonNull) + ' dei non NULL');
        if ('empty_count' in s) cards += card('Vuoti / spazi', F(+s.empty_count), pct(+s.empty_count, total));
        if ('min_value' in s) cards += card('Minimo', `<span class="ident">${E(s.min_value ?? '—')}</span>`) + card('Massimo', `<span class="ident">${E(s.max_value ?? '—')}</span>`);
        if ('avg_value' in s && s.avg_value !== null) cards += card('Media', Number(s.avg_value).toLocaleString('it-IT', { maximumFractionDigits: 4 }));
        if ('min_len' in s) cards += card('Lunghezza', `${s.min_len ?? '—'} – ${s.max_len ?? '—'}`, `media ${s.avg_len === null ? '—' : Number(s.avg_len).toFixed(1)}`);

        const topRows = d.top.map(r => `<tr>
            <td class="ident text-break">${r.value === null ? '<span class="cell-null">NULL</span>' : E(r.value) + Matriosga.copyBtn(String(r.value))}</td>
            <td class="text-end">${F(r.n)}</td>
            <td class="text-end text-nowrap" style="width:30%"><span class="bar-track d-inline-block align-middle me-1" style="width:60%"><span class="bar-fill" style="width:${Math.min(100, r.pct)}%"></span></span>${r.pct.toLocaleString('it-IT')}%</td>
            <td class="text-end">${r.value === null ? '' : `<a class="small" href="${E(Matriosga.url('/search', { q: String(r.value), mode: 'equals' }))}" title="Cerca questo valore in tutto il database"><i class="fa-solid fa-magnifying-glass"></i></a>`}</td></tr>`).join('');

        out.innerHTML = `
            <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                <h2 class="h5 mb-0 ident">${Matriosga.tableLink(d.table)}.<strong>${E(d.column)}</strong>${Matriosga.copyBtn(d.column)}</h2>
                <span class="badge badge-type">${E(d.type)}</span>
                ${d.sample ? `<span class="badge text-bg-warning" title="Tabella di ${F(d.table_rows)} righe">campione: prime ${F(d.sample)} righe</span>` : ''}
            </div>
            <div class="row g-3 mb-3">${cards}</div>
            <div class="row g-3">
                <div class="col-xl-7"><div class="card h-100">
                    <div class="card-header">Valori più frequenti <span class="text-secondary fw-normal small">(primi ${d.top_limit}, NULL compreso)</span></div>
                    <div class="table-responsive scroll-y"><table class="table table-sm table-hover table-sticky mb-0">
                        <thead><tr><th>Valore</th><th class="text-end">Occorrenze</th><th class="text-end">%</th><th></th></tr></thead>
                        <tbody>${topRows}</tbody></table></div></div></div>
                <div class="col-xl-5">
                    ${d.distribution && d.distribution.rows.length ? `<div class="card mb-3"><div class="card-header">Distribuzione per ${E(d.distribution.kind.toLowerCase())}</div>
                        <div class="card-body scroll-y">${bars(d.distribution.rows, nonNull)}</div></div>` : ''}
                    ${Matriosga.codeBlock('SQL: valori e occorrenze', d.sql, 'fa-database')}
                    ${Matriosga.codeBlock('Power Query', d.pq, 'fa-chart-simple')}
                </div>
            </div>`;
    }

    async function analyze(ev) {
        ev?.preventDefault();
        const t = tableInput.value.trim(), c = colSelect.value;
        if (!t || !c) return;
        const url = new URL(location.href);
        url.searchParams.set('t', t);
        url.searchParams.set('c', c);
        history.replaceState(null, '', url);
        out.innerHTML = '<div class="text-secondary"><span class="spinner-border spinner-border-sm"></span> Analisi in corso… (sulle tabelle grandi può richiedere qualche secondo)</div>';
        try {
            render(await Matriosga.busy($('an-go'), () => Matriosga.api('/api/analysis', { params: { t, c, full: $('an-full').checked ? 1 : '' } })));
        } catch (e) {
            out.innerHTML = `<div class="alert alert-danger">${E(e.message)}</div>`;
        }
    }

    tableInput.addEventListener('change', () => loadColumns());
    colSelect.addEventListener('change', analyze);
    $('an-form').addEventListener('submit', analyze);

    if (tableInput.value.trim()) {
        loadColumns(colSelect.dataset.initial).then(() => { if (colSelect.value) analyze(); });
    }
})();
