/*
 * Finestra "Nuova relazione definita da te" (partials/relation_editor.php).
 * Uso: Matriosga.relationEditor.open({from, to, onSaved(rel)}) oppure un pulsante con data-new-relation
 * (attributi facoltativi data-from, data-to).
 */
(function () {
    'use strict';

    const $ = id => document.getElementById(id);
    const E = Matriosga.esc;
    if (!$('rel-editor')) return;
    const modal = new bootstrap.Modal($('rel-editor'));
    let cols = { from: [], to: [] };
    let onSaved = null;

    const colOptions = (list, selected) => '<option value="">—</option>' + list
        .map(c => `<option value="${E(c.name)}"${c.name === selected ? ' selected' : ''}>${E(c.name)} · ${E(c.type)}</option>`).join('');

    function addPair(a = '', b = '') {
        $('re-pairs').insertAdjacentHTML('beforeend', `<div class="d-flex gap-2 align-items-center mb-2" data-pair>
            <select class="form-select form-select-sm ident" data-side="from">${colOptions(cols.from, a)}</select>
            <span class="text-secondary">=</span>
            <select class="form-select form-select-sm ident" data-side="to">${colOptions(cols.to, b)}</select>
            <button type="button" class="btn btn-sm btn-link text-danger p-0" data-pair-del title="Togli"><i class="fa-solid fa-xmark"></i></button></div>`);
    }

    function relation() {
        const pairs = [...document.querySelectorAll('#re-pairs [data-pair]')]
            .map(p => [p.querySelector('[data-side="from"]').value, p.querySelector('[data-side="to"]').value])
            .filter(([a, b]) => a && b);
        return { from: $('re-from').value.trim(), to: $('re-to').value.trim(), from_cols: pairs.map(p => p[0]), to_cols: pairs.map(p => p[1]), note: $('re-note').value };
    }

    /** Quando sono scelte entrambe le tabelle: carica le colonne e propone le coppie. */
    async function load() {
        const from = $('re-from').value.trim(), to = $('re-to').value.trim();
        $('re-verify').innerHTML = '';
        if (!from || !to) return;
        try {
            const [a, b, sug] = await Promise.all([
                Matriosga.api('/api/tables/columns', { params: { t: from } }),
                Matriosga.api('/api/tables/columns', { params: { t: to } }),
                Matriosga.api('/api/relations/suggest', { params: { from, to } }),
            ]);
            $('re-from').value = a.table; $('re-to').value = b.table;
            cols = { from: a.columns, to: b.columns };
            $('re-pairs').innerHTML = '';
            $('re-add-pair').disabled = false;
            if (sug.length) {
                $('re-suggest').innerHTML = `<div class="small mb-1"><i class="fa-solid fa-lightbulb text-warning"></i> Proposta in base ai nomi delle colonne (controlla prima di salvare):</div>
                    <select class="form-select form-select-sm" id="re-sug">${sug.map((s, i) => `<option value="${i}">${E(s.pairs.map(p => p[0] + ' = ' + p[1]).join(', '))}${s.complete ? '' : ' (incompleta)'}</option>`).join('')}</select>`;
                const apply = () => { $('re-pairs').innerHTML = ''; sug[+$('re-sug').value].pairs.forEach(([x, y]) => addPair(x, y)); };
                $('re-sug').addEventListener('change', apply);
                apply();
            } else {
                $('re-suggest').innerHTML = '<div class="small text-secondary mb-1">Nessuna proposta automatica: scegli tu le colonne.</div>';
                addPair();
            }
        } catch (e) {
            $('re-suggest').innerHTML = `<div class="alert alert-warning py-2 small">${E(e.message)}</div>`;
        }
    }

    function open(opt = {}) {
        onSaved = opt.onSaved || null;
        $('re-from').value = opt.from || '';
        $('re-to').value = opt.to || '';
        ['re-suggest', 're-pairs', 're-verify'].forEach(id => { $(id).innerHTML = ''; });
        $('re-note').value = '';
        $('re-add-pair').disabled = true;
        modal.show();
        load();
    }

    ['re-from', 're-to'].forEach(id => $(id).addEventListener('change', load));
    $('re-add-pair').addEventListener('click', () => addPair());
    $('re-pairs').addEventListener('click', ev => { if (ev.target.closest('[data-pair-del]')) ev.target.closest('[data-pair]').remove(); });

    $('re-verify-btn').addEventListener('click', ev => Matriosga.busy(ev.currentTarget, async () => {
        try {
            const d = await Matriosga.api('/api/relations/verify', { body: relation() });
            const cls = d.pct === null ? 'secondary' : d.pct >= 95 ? 'success' : d.pct >= 50 ? 'warning' : 'danger';
            $('re-verify').innerHTML = `<div class="alert alert-${cls} py-2 small mb-0">${d.pct === null
                ? 'Nessun valore da verificare (colonne vuote nella tabella che punta).'
                : `<strong>${d.pct}%</strong> delle righe campionate (${Matriosga.fmt(d.matched)} su ${Matriosga.fmt(d.sampled)}) trova una corrispondenza nella tabella puntata.`}</div>`;
        } catch (e) { $('re-verify').innerHTML = `<div class="alert alert-danger py-2 small mb-0">${E(e.message)}</div>`; }
    }));

    $('re-save').addEventListener('click', ev => Matriosga.busy(ev.currentTarget, async () => {
        try {
            const rel = await Matriosga.api('/api/relations/user', { body: relation() });
            modal.hide();
            Matriosga.toast('Relazione salvata');
            if (onSaved) onSaved(rel); else location.reload();
        } catch (e) { Matriosga.toast(e.message, 'danger'); }
    }));

    document.addEventListener('click', ev => {
        const btn = ev.target.closest('[data-new-relation]');
        if (btn) open({ from: btn.dataset.from, to: btn.dataset.to });
    });

    // Elimina: <button data-delete-relation="id">
    document.addEventListener('click', async ev => {
        const btn = ev.target.closest('[data-delete-relation]');
        if (!btn || !confirm('Eliminare questa relazione definita da te?')) return;
        try { await Matriosga.api('/api/relations/user/delete', { body: { id: btn.dataset.deleteRelation } }); location.reload(); }
        catch (e) { Matriosga.toast(e.message, 'danger'); }
    });

    Matriosga.relationEditor = { open };
})();
