/* Query di SGA: finestra con il codice completo (termine evidenziato) e, per viste/funzioni, come usarla in Power BI. */
(function () {
    'use strict';

    const $ = id => document.getElementById(id);
    const E = Matriosga.esc;
    const body = $('module-body');
    if (!body) return;
    const modal = new bootstrap.Modal($('module-modal'));

    document.addEventListener('click', async ev => {
        const btn = ev.target.closest('[data-module]');
        if (!btn) return;
        $('module-title').textContent = btn.dataset.module;
        body.innerHTML = '<div class="text-secondary"><span class="spinner-border spinner-border-sm"></span> Caricamento…</div>';
        modal.show();
        try {
            const d = await Matriosga.api('/api/queries/show', { params: { n: btn.dataset.module } });
            const code = Matriosga.highlight(d.definition, body.dataset.term, body.dataset.word === '1');
            let usage = '';
            if (d.usage) {
                usage = `<h6 class="mt-1">Usarla in Power BI</h6>
                    ${d.params.length ? `<p class="small text-secondary">È una funzione con ${d.params.length} parametri (${E(d.params.join(', '))}): sostituisci i <span class="ident">NULL</span> con i valori che ti servono.</p>` : ''}
                    ${Matriosga.codeBlock('SQL', d.usage, 'fa-database')}${Matriosga.codeBlock('Power Query', d.pq, 'fa-chart-simple')}`;
            }
            body.innerHTML = `${usage}<h6>Codice di SGA <span class="badge badge-type">${E(d.label)}</span></h6>
                <div class="code-card"><div class="code-head"><span>${E(d.name)}</span>
                <button type="button" class="btn btn-sm btn-outline-light" data-copy="${E(d.definition)}"><i class="fa-regular fa-copy"></i> Copia</button></div>
                <pre class="code-block code-tall">${code}</pre></div>`;
            body.querySelector('mark')?.scrollIntoView({ block: 'center' });
        } catch (e) {
            body.innerHTML = `<div class="alert alert-danger">${E(e.message)}</div>`;
        }
    });
})();
