/* Spia modifiche: pronta → in corso → risultati (stato persistente lato server: si può ricaricare la pagina). */
(function () {
    'use strict';

    const $ = id => document.getElementById(id);
    const E = Matriosga.esc, F = Matriosga.fmt;
    const panels = { idle: $('spy-idle'), running: $('spy-running'), results: $('spy-results') };
    let timer = null;

    function show(name) {
        Object.entries(panels).forEach(([k, el]) => el.classList.toggle('d-none', k !== name));
        clearInterval(timer);
    }

    const time = s => s ? s.slice(11, 19) : '';

    function running(state) {
        show('running');
        $('spy-since').textContent = time(state.started_at);
        $('spy-who').textContent = state.user ? `, utente ${state.user}` : '';
        const t0 = Date.now();
        const tick = () => { const s = Math.floor((Date.now() - t0) / 1000); $('spy-elapsed').textContent = `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`; };
        tick();
        timer = setInterval(tick, 1000);
    }

    async function results() {
        show('results');
        const d = await Matriosga.api('/api/spy/stop', { body: {} });
        $('spy-period').textContent = `Intervallo: ${d.state.started_at.slice(0, 19)} → ${time(d.state.stopped_at)} (ora del server)${d.state.user ? ' · utente ' + d.state.user : ''}`;

        // Righe aggiunte/tolte (conteggi)
        $('count-table').tBodies[0].innerHTML = d.changes.length ? d.changes.map(c => `<tr>
            <td>${Matriosga.tableLink(c.table)}</td><td class="text-end">${F(c.before)}</td><td class="text-end">${F(c.after)}</td>
            <td class="text-end fw-semibold ${c.delta > 0 ? 'text-success' : 'text-danger'}" data-value="${c.delta}">${c.delta > 0 ? '+' : ''}${F(c.delta)}</td></tr>`).join('')
            : '<tr><td colspan="4" class="text-secondary">Nessuna tabella ha cambiato numero di righe.</td></tr>';

        // Righe nuove/modificate (a lotti, come la ricerca valore)
        const body = $('scan-table').tBodies[0];
        body.innerHTML = '';
        const queue = [...d.scan];
        const total = queue.length;
        let done = 0, found = 0;
        const status = $('scan-status');
        while (queue.length) {
            status.innerHTML = `<span class="spinner-border spinner-border-sm"></span> Controllo ${F(done)} / ${F(total)} tabelle…`;
            const batch = queue.splice(0, 20);
            let r;
            try { r = await Matriosga.api('/api/spy/scan', { body: { tables: batch } }); }
            catch (e) { status.innerHTML = `<span class="text-danger">${E(e.message)}</span>`; return; }
            const ok = new Set(r.done);
            queue.unshift(...batch.filter(t => !ok.has(t)));
            done += r.done.length || batch.length;
            for (const h of r.hits) {
                found++;
                const rv = h.method === 'rowversion';
                body.insertAdjacentHTML('beforeend', `<tr>
                    <td>${Matriosga.tableLink(h.table)}</td>
                    <td class="text-end" data-value="${rv ? h.changed : h.inserted ?? 0}">${rv ? `<span title="rowversion: nuove o modificate">${F(h.changed)}*</span>` : (h.inserted ?? '—')}</td>
                    <td class="text-end" data-value="${h.updated ?? 0}">${rv ? '*' : (h.updated ?? '—')}</td>
                    <td class="small text-secondary">${rv ? 'rowversion (nuove o modificate)' : 'data/ora' + (h.by_user ? ' + utente' : '')}</td>
                    <td class="text-end"><button type="button" class="btn btn-sm btn-outline-primary" data-spy-rows="${E(h.table)}"><i class="fa-solid fa-eye"></i> Righe</button></td></tr>`);
            }
        }
        status.textContent = found
            ? `${F(found)} tabelle con righe nuove o modificate (controllate ${F(total)} tabelle che hanno data/ora o rowversion).`
            : `Nessuna riga nuova o modificata nelle ${F(total)} tabelle controllabili.`;
    }

    // Righe trovate
    const modal = new bootstrap.Modal($('spy-modal'));
    document.addEventListener('click', async ev => {
        const btn = ev.target.closest('[data-spy-rows]');
        if (!btn) return;
        $('spy-modal-title').textContent = btn.dataset.spyRows;
        $('spy-modal-body').innerHTML = '<span class="spinner-border spinner-border-sm"></span> Caricamento…';
        modal.show();
        try { Matriosga.renderDataTable($('spy-modal-body'), await Matriosga.api('/api/spy/rows', { body: { t: btn.dataset.spyRows } })); }
        catch (e) { $('spy-modal-body').innerHTML = `<div class="alert alert-danger">${E(e.message)}</div>`; }
    });

    $('spy-start').addEventListener('click', async ev => {
        try { running(await Matriosga.busy(ev.currentTarget, () => Matriosga.api('/api/spy/start', { body: { user: $('spy-user').value } }))); }
        catch (e) { Matriosga.toast(e.message, 'danger'); }
    });
    $('spy-stop').addEventListener('click', ev => Matriosga.busy(ev.currentTarget, results).catch(e => Matriosga.toast(e.message, 'danger')));
    document.addEventListener('click', async ev => {
        if (!ev.target.closest('[data-spy-reset]')) return;
        await Matriosga.api('/api/spy/reset', { body: {} });
        show('idle');
    });

    // Avvio: riprende lo stato salvato sul server
    Matriosga.api('/api/spy/state').then(s => {
        if (!s) show('idle');
        else if (!s.stopped_at) running(s);
        else results();
    }).catch(e => { show('idle'); Matriosga.toast(e.message, 'danger'); });
})();
