/* Elenco tabelle: dati compatti in JSON, filtro/ordinamento lato client, al massimo MAX righe nel DOM. */
(function () {
    'use strict';

    const MAX = 300;
    // Riga: [nome, tipo, righe, ncolonne, pk, fkOut, fkIn, descrizione, vuota/copia (1/0)]
    const data = JSON.parse(document.getElementById('tables-data').textContent);
    const search = data.map(r => Matriosga.norm(r[0] + ' ' + r[7]));
    const $ = id => document.getElementById(id);
    const filter = $('tables-filter'), type = $('tables-type'), schema = $('tables-schema'), showAll = $('tables-all');
    const tbody = document.querySelector('#tables-list tbody');
    let sortKey = 0, sortDir = 1, limit = MAX;

    function render(keepLimit = false) {
        if (!keepLimit) limit = MAX;
        const tokens = Matriosga.norm(filter.value).split(/\s+/).filter(Boolean);
        let rows = [];
        data.forEach((r, i) => {
            if (type.value && r[1] !== type.value) return;
            if (schema && schema.value && !r[0].startsWith(schema.value + '.')) return;
            if (!showAll.checked && r[8]) return;
            if (!tokens.every(t => search[i].includes(t))) return;
            rows.push(r);
        });
        // Con un filtro testuale, prima i nomi che iniziano con il testo cercato
        const first = tokens[0];
        rows.sort((a, b) => {
            if (first && sortKey === 0) {
                const sa = Matriosga.norm(a[0].split('.').pop()).startsWith(first), sb = Matriosga.norm(b[0].split('.').pop()).startsWith(first);
                if (sa !== sb) return sa ? -1 : 1;
            }
            const x = a[sortKey] ?? -1, y = b[sortKey] ?? -1;
            return (typeof x === 'number' ? x - y : String(x).localeCompare(String(y))) * sortDir;
        });

        const E = Matriosga.esc, dash = '<span class="text-secondary">—</span>';
        tbody.innerHTML = rows.slice(0, limit).map(r => `<tr>
            <td>${Matriosga.tableLink(r[0], r[1] === 'V')}</td>
            <td class="text-end text-nowrap">${r[2] === null ? dash : Matriosga.fmt(r[2])}</td>
            <td class="text-end">${r[3]}</td>
            <td>${r[4] ? `<span class="badge badge-pk ident"><i class="fa-solid fa-key"></i> ${E(r[4])}</span>` : (r[1] === 'U' ? '<span class="text-secondary small">nessuna</span>' : '')}</td>
            <td class="text-end">${r[5] || '<span class="text-secondary">0</span>'}</td>
            <td class="text-end">${r[6] || '<span class="text-secondary">0</span>'}</td>
            <td class="small text-secondary text-truncate" style="max-width:320px">${E(r[7])}</td></tr>`).join('');

        $('tables-count').textContent = rows.length === data.length ? Matriosga.fmt(data.length) : `${Matriosga.fmt(rows.length)} / ${Matriosga.fmt(data.length)}`;
        $('tables-empty').classList.toggle('d-none', rows.length > 0);
        $('tables-more').classList.toggle('d-none', rows.length <= limit);
        $('tables-more').innerHTML = `Mostrate ${Matriosga.fmt(Math.min(limit, rows.length))} di ${Matriosga.fmt(rows.length)}.
            <button type="button" class="btn btn-sm btn-outline-primary ms-2" id="tables-next">Mostra altre ${MAX}</button>
            <span class="ms-2">oppure scrivi nel filtro per restringere.</span>`;
        $('tables-next')?.addEventListener('click', () => { limit += MAX; render(true); });

        const url = new URL(location.href);
        filter.value ? url.searchParams.set('q', filter.value) : url.searchParams.delete('q');
        history.replaceState(null, '', url);
    }

    document.querySelectorAll('#tables-list th.sortable-th').forEach(th => th.addEventListener('click', () => {
        const key = +th.dataset.key;
        sortDir = sortKey === key ? -sortDir : (key === 0 ? 1 : -1); // numeri: prima i più grandi
        sortKey = key;
        document.querySelectorAll('#tables-list th.sortable-th').forEach(h => delete h.dataset.dir);
        th.dataset.dir = sortDir === 1 ? 'asc' : 'desc';
        render();
    }));
    filter.addEventListener('input', Matriosga.debounce(() => render(), 100));
    [type, schema, showAll].forEach(el => el && el.addEventListener('change', () => render()));
    render();
})();
