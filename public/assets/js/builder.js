/*
 * Costruttore di query. Stato nel browser (localStorage, si ritrova ricaricando la pagina):
 *   tables:  [{name, via: null | {parent, cols_parent, cols_child, join, kind, label}, auto, group, alts?, alt?}]
 *            (i genitori stanno sempre prima dei figli; "group" = tabelle aggiunte insieme con un collegamento)
 *   columns: [{t: nome tabella, c: colonna}]   filters: [{t, c, op, v}]
 * Al server va solo una descrizione (spec) con alias t0, t1…: l'SQL lo costruisce e valida il server.
 */
(function () {
    'use strict';

    const $ = id => document.getElementById(id);
    const E = Matriosga.esc;
    const KEY = 'matriosga.builder.v1';
    const OPS = JSON.parse($('b-operators').textContent);
    const NO_VALUE = ['null', 'notnull'];

    const blank = () => ({ tables: [], columns: [], filters: [], seq: 0, options: { cand: false, hubs: false, all: false } });
    let st = (() => { try { return JSON.parse(localStorage.getItem(KEY)) || blank(); } catch (e) { return blank(); } })();
    const save = () => { try { localStorage.setItem(KEY, JSON.stringify(st)); } catch (e) { /* stato solo in memoria */ } };

    const colCache = {};
    const columnsOf = name => (colCache[name] ??= Matriosga.api('/api/tables/columns', { params: { t: name } }));
    const aliasOf = name => 't' + st.tables.findIndex(t => t.name === name);
    const has = name => st.tables.some(t => t.name === name);

    function spec() {
        return {
            tables: st.tables.map((t, i) => ({ alias: 't' + i, name: t.name, via: t.via ? { ...t.via, parent: aliasOf(t.via.parent) } : undefined })),
            columns: st.columns.filter(c => has(c.t)).map(c => ({ t: aliasOf(c.t), c: c.c })),
            filters: st.filters.filter(f => has(f.t) && f.c).map(f => ({ t: aliasOf(f.t), c: f.c, op: f.op, v: f.v })),
        };
    }

    // ---------- tabelle ----------

    async function addTable() {
        const input = $('b-table');
        const typed = input.value.trim();
        if (!typed) return;
        let full;
        try { full = (await columnsOf(typed)).table; } catch (e) { Matriosga.toast(e.message, 'danger'); return; }
        if (has(full)) { Matriosga.toast('Tabella già presente', 'warning'); return; }
        if (!st.tables.length) {
            st.tables.push({ name: full, via: null, auto: false, group: ++st.seq });
        } else {
            const paths = await Matriosga.busy($('b-add'), () => Matriosga.api('/api/builder/connect', {
                body: { table: full, existing: st.tables.map(t => t.name), ...st.options },
            })).catch(e => { Matriosga.toast(e.message, 'danger'); return null; });
            if (!paths) return;
            if (!paths.length) {
                Matriosga.toast(`Nessun collegamento trovato per ${full}. Prova ad attivare le relazioni candidate o le tabelle hub.`, 'warning');
                return;
            }
            applyPath(paths, 0, ++st.seq, full);
        }
        input.value = '';
        save();
        renderTables();
        refresh();
    }

    /** Aggiunge le tabelle del percorso scelto (le intermedie marcate "auto"); le alternative restano sulla tabella richiesta. */
    function applyPath(paths, index, group, target) {
        for (const s of paths[index].steps) {
            if (has(s.child)) continue;
            st.tables.push({
                name: s.child, auto: s.child !== target, group,
                via: { parent: s.parent, cols_parent: s.cols_parent, cols_child: s.cols_child, join: 'LEFT', kind: s.kind, label: s.label },
            });
        }
        const t = st.tables.find(x => x.name === target);
        if (t) { t.alts = paths; t.alt = index; }
    }

    /** Toglie le tabelle indicate e tutte quelle collegate "sotto" di loro, con colonne e filtri. */
    function removeTables(names) {
        const gone = new Set(names);
        let grew = true;
        while (grew) {
            grew = false;
            for (const t of st.tables) if (t.via && gone.has(t.via.parent) && !gone.has(t.name)) { gone.add(t.name); grew = true; }
        }
        st.tables = st.tables.filter(t => !gone.has(t.name));
        st.columns = st.columns.filter(c => !gone.has(c.t));
        st.filters = st.filters.filter(f => !gone.has(f.t));
    }

    function changePath(target, index) {
        const t = st.tables.find(x => x.name === target);
        const keepCols = st.columns.filter(c => c.t === target);
        const keepFilters = st.filters.filter(f => f.t === target);
        removeTables(st.tables.filter(x => x.group === t.group).map(x => x.name));
        applyPath(t.alts, index, t.group, target);
        st.columns.push(...keepCols);
        st.filters.push(...keepFilters);
        save();
        renderTables();
        refresh();
    }

    function renderTables() {
        const box = $('b-tables');
        box.innerHTML = st.tables.map((t, i) => {
            const v = t.via;
            const pairs = v ? v.cols_parent.map((c, k) => `${E(c)} = ${E(v.cols_child[k])}`).join(', ') : '';
            const alts = t.alts && t.alts.length > 1 ? `<select class="form-select form-select-sm w-auto" data-alt="${E(t.name)}" title="Altri modi di collegarla">
                ${t.alts.map((p, k) => `<option value="${k}"${k === t.alt ? ' selected' : ''}>percorso ${k + 1}: ${E(p.nodes.map(n => n.split('.').pop()).join(' → '))}</option>`).join('')}</select>` : '';
            return `<div class="b-table" data-name="${E(t.name)}">
                <div class="d-flex align-items-center gap-2">
                    <span class="badge text-bg-dark ident">t${i}</span>
                    ${Matriosga.tableLink(t.name)}
                    ${i === 0 ? '<span class="badge text-bg-primary">principale</span>' : ''}
                    ${t.auto ? '<span class="badge text-bg-light border" title="Aggiunta da sola per collegare le tabelle scelte">per collegare</span>' : ''}
                    <button type="button" class="btn btn-sm btn-link text-danger ms-auto p-0" data-remove="${E(t.name)}" title="Togli (con le tabelle collegate sotto)"><i class="fa-solid fa-xmark"></i></button>
                </div>
                ${v ? `<div class="b-via small">
                    <div class="text-secondary">collegata a <span class="ident">${E(v.parent.split('.').pop())}</span>:
                        <span class="ident">${pairs}</span>
                        ${v.kind === 'candidate' ? `<span class="badge badge-cand">${E(v.label)}</span>` : `<span class="badge badge-fk" title="${E(v.label)}">FK</span>`}</div>
                    <div class="d-flex flex-wrap gap-2 mt-1">
                        <select class="form-select form-select-sm w-auto" data-join="${E(t.name)}" title="LEFT: tiene tutte le righe della tabella sopra anche senza corrispondenza. INNER: solo le righe che hanno corrispondenza.">
                            <option value="LEFT"${v.join === 'LEFT' ? ' selected' : ''}>tieni tutte le righe (LEFT JOIN)</option>
                            <option value="INNER"${v.join === 'INNER' ? ' selected' : ''}>solo con corrispondenza (INNER JOIN)</option>
                        </select>${alts}
                    </div></div>` : ''}
                <div class="b-cols mt-2" data-cols="${E(t.name)}"><span class="spinner-border spinner-border-sm text-secondary"></span></div>
            </div>`;
        }).join('');
        st.tables.forEach(t => renderColumns(t.name));
    }

    async function renderColumns(name) {
        const box = document.querySelector(`[data-cols="${CSS.escape(name)}"]`);
        if (!box) return;
        let d;
        try { d = await columnsOf(name); } catch (e) { box.innerHTML = `<span class="text-danger small">${E(e.message)}</span>`; return; }
        const selected = new Set(st.columns.filter(c => c.t === name).map(c => c.c));
        box.innerHTML = `<div class="d-flex gap-2 align-items-center mb-1">
                <input type="search" class="form-control form-control-sm" data-colfilter placeholder="Filtra colonne…">
                <button type="button" class="btn btn-sm btn-light text-nowrap" data-all-cols="1">tutte</button>
                <button type="button" class="btn btn-sm btn-light text-nowrap" data-all-cols="0">nessuna</button>
            </div>
            <div class="b-col-list">${d.columns.map(c => {
                const off = ['binary', 'other'].includes(c.category);
                return `<label class="b-col${off ? ' text-secondary' : ''}" title="${off ? 'Tipo non selezionabile' : ''}">
                    <input type="checkbox" class="form-check-input me-1" value="${E(c.name)}"${selected.has(c.name) ? ' checked' : ''}${off ? ' disabled' : ''}>
                    <span class="ident">${E(c.name)}</span> <span class="text-secondary">${E(c.type)}</span></label>`;
            }).join('')}</div>`;
    }

    // ---------- filtri ----------

    async function renderFilters() {
        const box = $('b-filters');
        if (!st.filters.length) { box.innerHTML = '<div class="text-secondary small">Nessun filtro: vengono tutte le righe.</div>'; return; }
        const lists = await Promise.all(st.tables.map(t => columnsOf(t.name).catch(() => ({ columns: [] }))));
        const options = st.tables.map((t, i) => `<optgroup label="t${i} · ${E(t.name.split('.').pop())}">${lists[i].columns
            .filter(c => !['binary', 'other'].includes(c.category))
            .map(c => `<option value="${E(t.name + '|' + c.name)}">${E(c.name)}</option>`).join('')}</optgroup>`).join('');
        box.innerHTML = st.filters.map((f, i) => `<div class="d-flex flex-wrap gap-2 mb-2" data-filter="${i}">
            <select class="form-select form-select-sm ident" style="max-width:260px" data-f="col"><option value="">— colonna —</option>${options}</select>
            <select class="form-select form-select-sm w-auto" data-f="op">${Object.entries(OPS).map(([k, l]) => `<option value="${E(k)}"${k === f.op ? ' selected' : ''}>${E(l)}</option>`).join('')}</select>
            <input class="form-control form-control-sm" style="max-width:200px" data-f="v" value="${E(f.v)}"${NO_VALUE.includes(f.op) ? ' hidden' : ''} placeholder="valore">
            <button type="button" class="btn btn-sm btn-link text-danger p-0" data-f="del" title="Togli filtro"><i class="fa-solid fa-xmark"></i></button>
        </div>`).join('');
        box.querySelectorAll('[data-filter]').forEach(row => {
            const f = st.filters[+row.dataset.filter];
            row.querySelector('[data-f="col"]').value = f.t && f.c ? f.t + '|' + f.c : '';
        });
    }

    // ---------- risultato ----------

    const refresh = Matriosga.debounce(async () => {
        const ready = st.tables.length && st.columns.some(c => has(c.t));
        $('b-empty').classList.toggle('d-none', !!ready);
        document.querySelectorAll('.b-code').forEach(el => { el.innerHTML = ''; });
        if (!ready) return;
        try {
            const d = await Matriosga.api('/api/builder/sql', { body: { spec: spec() } });
            document.querySelector('.b-code[data-kind="sql"]').innerHTML = Matriosga.codeBlock('SQL', d.sql, 'fa-database');
            document.querySelector('.b-code[data-kind="pq"]').innerHTML = Matriosga.codeBlock('Power Query (Editor avanzato)', d.pq, 'fa-chart-simple');
        } catch (e) {
            document.querySelectorAll('.b-code').forEach(el => { el.innerHTML = `<div class="alert alert-warning mb-0">${E(e.message)}</div>`; });
        }
    }, 300);

    // ---------- eventi ----------

    $('b-add').addEventListener('click', addTable);
    $('b-table').addEventListener('keydown', ev => { if (ev.key === 'Enter' && !ev.defaultPrevented) { ev.preventDefault(); addTable(); } });

    [['b-cand', 'cand'], ['b-hubs', 'hubs'], ['b-all', 'all']].forEach(([id, key]) => {
        $(id).checked = !!st.options[key];
        $(id).addEventListener('change', () => { st.options[key] = $(id).checked; save(); });
    });

    $('b-tables').addEventListener('change', ev => {
        const el = ev.target;
        if (el.matches('.b-col-list input[type=checkbox]')) {
            const t = el.closest('[data-cols]').dataset.cols;
            st.columns = st.columns.filter(c => !(c.t === t && c.c === el.value));
            if (el.checked) st.columns.push({ t, c: el.value });
        } else if (el.dataset.join) {
            st.tables.find(t => t.name === el.dataset.join).via.join = el.value;
        } else if (el.dataset.alt) {
            changePath(el.dataset.alt, +el.value);
            return;
        } else {
            return;
        }
        save();
        refresh();
    });

    $('b-tables').addEventListener('input', ev => {
        if (!ev.target.matches('[data-colfilter]')) return;
        const q = Matriosga.norm(ev.target.value);
        ev.target.closest('[data-cols]').querySelectorAll('.b-col').forEach(l => { l.hidden = !Matriosga.norm(l.textContent).includes(q); });
    });

    $('b-tables').addEventListener('click', ev => {
        const rm = ev.target.closest('[data-remove]');
        if (rm) {
            const isRoot = st.tables[0]?.name === rm.dataset.remove;
            if (isRoot && st.tables.length > 1 && !confirm('È la tabella principale: togliendola si ricomincia da capo. Procedere?')) return;
            isRoot ? (st = { ...blank(), options: st.options }) : removeTables([rm.dataset.remove]);
            save(); renderTables(); renderFilters(); refresh();
            return;
        }
        const all = ev.target.closest('[data-all-cols]');
        if (all) {
            const box = all.closest('[data-cols]');
            box.querySelectorAll('.b-col:not([hidden]) input:not(:disabled)').forEach(cb => {
                if (cb.checked !== (all.dataset.allCols === '1')) { cb.checked = all.dataset.allCols === '1'; cb.dispatchEvent(new Event('change', { bubbles: true })); }
            });
        }
    });

    $('b-add-filter').addEventListener('click', () => {
        if (!st.tables.length) { Matriosga.toast('Prima aggiungi una tabella', 'warning'); return; }
        st.filters.push({ t: '', c: '', op: '=', v: '' });
        save(); renderFilters();
    });

    $('b-filters').addEventListener('change', ev => {
        const row = ev.target.closest('[data-filter]');
        if (!row) return;
        const f = st.filters[+row.dataset.filter];
        const kind = ev.target.dataset.f;
        if (kind === 'col') { [f.t, f.c] = ev.target.value ? ev.target.value.split('|') : ['', '']; }
        if (kind === 'op') { f.op = ev.target.value; row.querySelector('[data-f="v"]').hidden = NO_VALUE.includes(f.op); }
        if (kind === 'v') { f.v = ev.target.value; }
        save(); refresh();
    });
    $('b-filters').addEventListener('click', ev => {
        const del = ev.target.closest('[data-f="del"]');
        if (!del) return;
        st.filters.splice(+del.closest('[data-filter]').dataset.filter, 1);
        save(); renderFilters(); refresh();
    });

    $('b-run').addEventListener('click', ev => Matriosga.busy(ev.currentTarget, async () => {
        try { Matriosga.renderDataTable($('b-preview'), await Matriosga.api('/api/builder/preview', { body: { spec: spec() } })); }
        catch (e) { $('b-preview').innerHTML = `<div class="alert alert-warning">${E(e.message)}</div>`; }
    }));

    $('b-reset').addEventListener('click', () => {
        if (st.tables.length && !confirm('Ricominciare da capo?')) return;
        st = { ...blank(), options: st.options };
        save(); renderTables(); renderFilters(); refresh();
        $('b-preview').innerHTML = '';
    });

    renderTables();
    renderFilters();
    refresh();
})();
