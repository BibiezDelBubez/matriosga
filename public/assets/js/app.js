/* Matriosga — funzioni JS comuni. Le pagine usano Matriosga.* invece di riscriverle. */
(function () {
    'use strict';

    const base = document.body.dataset.base || '';

    const Matriosga = {
        /** URL applicativo: Matriosga.url('/api/x', {a: 1}) */
        url(path, params) {
            const qs = params ? new URLSearchParams(Object.entries(params).filter(([, v]) => v !== null && v !== undefined && v !== '')).toString() : '';
            return base + '/' + String(path).replace(/^\//, '') + (qs ? '?' + qs : '');
        },

        /** Chiamata JSON: ritorna data, oppure lancia Error con il messaggio del server. */
        async api(path, { method = 'GET', params = null, body = null, signal = null } = {}) {
            const opts = { method, signal, headers: { Accept: 'application/json' } };
            if (body !== null) {
                opts.method = method === 'GET' ? 'POST' : method;
                if (body instanceof FormData) {
                    opts.body = body;
                } else {
                    opts.headers['Content-Type'] = 'application/json';
                    opts.body = JSON.stringify(body);
                }
            }
            const res = await fetch(Matriosga.url(path, params), opts);
            let json;
            try { json = await res.json(); } catch (e) { throw new Error('Risposta non valida dal server (HTTP ' + res.status + ')'); }
            if (!json.ok) throw new Error(json.error || 'Errore sconosciuto');
            return json.data;
        },

        esc(value) {
            return String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
        },

        fmt(n) {
            return n === null || n === undefined ? '—' : Number(n).toLocaleString('it-IT');
        },

        async copy(text) {
            try {
                await navigator.clipboard.writeText(text);
            } catch (e) {
                const ta = Object.assign(document.createElement('textarea'), { value: text });
                ta.style.position = 'fixed'; ta.style.opacity = '0';
                document.body.appendChild(ta); ta.select(); document.execCommand('copy'); ta.remove();
            }
        },

        /** HTML di un pulsante copia (stessa resa di partials/copy.php). */
        copyBtn(text, title = 'Copia') {
            return `<button type="button" class="btn-copy" data-copy="${Matriosga.esc(text)}" title="${Matriosga.esc(title)}"><i class="fa-regular fa-copy"></i></button>`;
        },

        /** Link al dettaglio tabella + copia (stessa resa di partials/table_link.php). */
        tableLink(full, isView = false) {
            const E = Matriosga.esc;
            return `<span class="text-nowrap">${isView ? '<i class="fa-regular fa-eye text-secondary me-1" title="Vista"></i>' : ''}<a class="ident" href="${E(Matriosga.url('/tables/show', { t: full }))}">${E(full)}</a>${Matriosga.copyBtn(full, 'Copia nome tabella')}</span>`;
        },

        /** Blocco codice copiabile (stessa resa di partials/code_block.php). */
        codeBlock(title, code, icon = 'fa-code') {
            const E = Matriosga.esc;
            return `<div class="code-card mb-3"><div class="code-head"><span><i class="fa-solid ${E(icon)}"></i> ${E(title)}</span>
                <button type="button" class="btn btn-sm btn-outline-light" data-copy="${E(code)}"><i class="fa-regular fa-copy"></i> Copia</button></div>
                <pre class="code-block">${E(code)}</pre></div>`;
        },

        toast(message, type = 'success') {
            const el = document.createElement('div');
            el.className = `toast align-items-center text-bg-${type} border-0`;
            el.innerHTML = `<div class="d-flex"><div class="toast-body">${Matriosga.esc(message)}</div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button></div>`;
            document.getElementById('toasts').appendChild(el);
            const t = new bootstrap.Toast(el, { delay: type === 'danger' ? 6000 : 2500 });
            el.addEventListener('hidden.bs.toast', () => el.remove());
            t.show();
        },

        debounce(fn, ms = 200) {
            let h;
            return (...args) => { clearTimeout(h); h = setTimeout(() => fn(...args), ms); };
        },

        /** Testo normalizzato per confronti: minuscolo, senza accenti. */
        norm(value) {
            return String(value ?? '').normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();
        },

        /**
         * Griglia di dati (anteprima righe, righe trovate...).
         * data = {columns: [{name, type}], rows: [[...]], limit}
         */
        renderDataTable(container, data) {
            const E = Matriosga.esc;
            if (!data.rows.length) {
                container.innerHTML = '<div class="empty-state"><i class="fa-regular fa-folder-open"></i>Nessuna riga.</div>';
                return;
            }
            const head = data.columns.map(c => `<th title="${E(c.type)}">${E(c.name)}</th>`).join('');
            const body = data.rows.map(r => '<tr>' + r.map(v => v === null
                ? '<td class="cell-null">NULL</td>'
                : `<td>${E(typeof v === 'boolean' ? (v ? 1 : 0) : v)}</td>`).join('') + '</tr>').join('');
            container.innerHTML = `<div class="small text-secondary mb-2">${Matriosga.fmt(data.rows.length)} righe${data.rows.length >= data.limit ? ' (limite ' + data.limit + ')' : ''}</div>
                <div class="table-responsive data-grid"><table class="table table-sm table-bordered table-sticky mb-0"><thead><tr>${head}</tr></thead><tbody>${body}</tbody></table></div>`;
        },

        /** Mostra stato "occupato" su un pulsante durante una promise. */
        async busy(button, promiseFactory) {
            const html = button.innerHTML;
            button.disabled = true;
            button.innerHTML = '<span class="spinner-border spinner-border-sm"></span> ' + button.textContent.trim();
            try { return await promiseFactory(); } finally { button.disabled = false; button.innerHTML = html; }
        },
    };

    // Delega globale: qualunque [data-copy] copia il proprio testo.
    document.addEventListener('click', async (ev) => {
        const btn = ev.target.closest('[data-copy]');
        if (!btn) return;
        ev.preventDefault();
        await Matriosga.copy(btn.dataset.copy);
        btn.classList.add('copied');
        setTimeout(() => btn.classList.remove('copied'), 1200);
        Matriosga.toast('Copiato: ' + (btn.dataset.copy.length > 60 ? btn.dataset.copy.slice(0, 60) + '…' : btn.dataset.copy));
    });

    // Sidebar mobile
    document.addEventListener('click', (ev) => {
        if (ev.target.closest('[data-toggle-sidebar]')) document.getElementById('sidebar').classList.toggle('show');
    });

    // "Aggiorna metadata" in topbar
    document.addEventListener('click', async (ev) => {
        const btn = ev.target.closest('[data-refresh-metadata]');
        if (!btn) return;
        try {
            const data = await Matriosga.busy(btn, () => Matriosga.api('/api/metadata/refresh', { method: 'POST', body: {} }));
            Matriosga.toast(`Metadata aggiornati: ${Matriosga.fmt(data.tables)} tabelle in ${data.seconds}s`);
            setTimeout(() => location.reload(), 600);
        } catch (e) {
            Matriosga.toast(e.message, 'danger');
        }
    });

    /*
     * Filtro istantaneo generico per tabelle HTML:
     *  - <input data-filter-table="#id">                    testo (tutte le parole devono comparire nella riga)
     *  - <select data-filter-table="#id" data-filter-key="k"> confronta con data-k della riga
     *  - [data-filter-count="#id"] conteggio, [data-filter-empty="#id"] messaggio "nessun risultato"
     */
    const filterGroups = new Map();
    document.querySelectorAll('[data-filter-table]').forEach(ctrl => {
        const sel = ctrl.dataset.filterTable;
        if (!filterGroups.has(sel)) filterGroups.set(sel, []);
        filterGroups.get(sel).push(ctrl);
    });
    filterGroups.forEach((controls, sel) => {
        const table = document.querySelector(sel);
        if (!table) return;
        const textOf = new WeakMap(); // testo normalizzato per riga (le righe possono essere aggiunte dopo)
        const counter = document.querySelector(`[data-filter-count="${sel}"]`);
        const empty = document.querySelector(`[data-filter-empty="${sel}"]`);
        const apply = () => {
            const rows = [...table.tBodies[0].rows];
            const texts = rows.map(r => textOf.get(r) ?? (textOf.set(r, Matriosga.norm(r.textContent)), textOf.get(r)));
            let shown = 0;
            const tokens = [], keys = [];
            controls.forEach(c => {
                if (c.dataset.filterKey) { if (c.value) keys.push([c.dataset.filterKey, c.value]); }
                else tokens.push(...Matriosga.norm(c.value).split(/\s+/).filter(Boolean));
            });
            rows.forEach((row, i) => {
                const ok = tokens.every(t => texts[i].includes(t)) && keys.every(([k, v]) => row.dataset[k] === v);
                row.hidden = !ok;
                if (ok) shown++;
            });
            if (counter) counter.textContent = shown === rows.length ? `${Matriosga.fmt(rows.length)}` : `${Matriosga.fmt(shown)} / ${Matriosga.fmt(rows.length)}`;
            if (empty) empty.classList.toggle('d-none', shown > 0 || rows.length === 0);
        };
        const debounced = Matriosga.debounce(apply, 120);
        controls.forEach(c => c.addEventListener(c.tagName === 'SELECT' ? 'change' : 'input', debounced));
        apply();
    });

    // Ordinamento: click su <th data-sort> (data-sort="num" per numeri; valore da data-value se presente)
    document.addEventListener('click', ev => {
        const th = ev.target.closest('table.sortable th[data-sort]');
        if (!th) return;
        const table = th.closest('table');
        const idx = [...th.parentNode.children].indexOf(th);
        const numeric = th.dataset.sort === 'num';
        const dir = th.dataset.dir === 'asc' ? 'desc' : 'asc';
        table.querySelectorAll('th[data-sort]').forEach(h => delete h.dataset.dir);
        th.dataset.dir = dir;
        const val = row => { const c = row.cells[idx]; const v = c.dataset.value ?? c.textContent.trim(); return numeric ? parseFloat(v) || 0 : Matriosga.norm(v); };
        const rows = [...table.tBodies[0].rows].sort((a, b) => {
            const x = val(a), y = val(b);
            return (x < y ? -1 : x > y ? 1 : 0) * (dir === 'asc' ? 1 : -1);
        });
        table.tBodies[0].append(...rows);
    });

    // Checkbox "seleziona tutte": <input data-check-all="nome[]"> (solo righe visibili)
    document.addEventListener('change', ev => {
        const all = ev.target.closest('[data-check-all]');
        if (!all) return;
        all.closest('form').querySelectorAll(`input[name="${all.dataset.checkAll}"]`).forEach(cb => {
            if (!cb.closest('tr')?.hidden) cb.checked = all.checked;
        });
    });

    // Verifica dati di una relazione: <button data-verify='{"from":..,"from_cols":[..],"to":..,"to_cols":[..]}'>
    document.addEventListener('click', async ev => {
        const btn = ev.target.closest('[data-verify]');
        if (!btn) return;
        try {
            const d = await Matriosga.busy(btn, () => Matriosga.api('/api/relations/verify', { body: JSON.parse(btn.dataset.verify) }));
            let cls = 'text-bg-secondary', text = 'nessun valore da verificare';
            if (d.pct !== null) {
                cls = d.pct >= 95 ? 'text-bg-success' : d.pct >= 50 ? 'text-bg-warning' : 'text-bg-danger';
                text = `${d.pct}% trovati`;
            }
            btn.outerHTML = `<span class="badge ${cls}" title="${Matriosga.fmt(d.matched)} su ${Matriosga.fmt(d.sampled)} righe campionate (max ${Matriosga.fmt(d.sample)}, colonne non NULL)">${text}</span>`;
        } catch (e) {
            Matriosga.toast(e.message, 'danger');
        }
    });

    /*
     * Autocompletamento con menu SOTTO il campo (il <datalist> nativo può aprirsi sopra e coprire il testo).
     * getItems: () => array di stringhe (anche Promise). Alla scelta imposta il valore e lancia 'change'.
     */
    Matriosga.autocomplete = function (input, getItems, max = 12) {
        const menu = document.createElement('div');
        menu.className = 'mt-ac dropdown-menu';
        document.body.appendChild(menu);
        input.setAttribute('autocomplete', 'off');
        let items = [], active = -1;

        const hide = () => { menu.classList.remove('show'); active = -1; };
        const place = () => {
            const r = input.getBoundingClientRect();
            Object.assign(menu.style, { left: r.left + scrollX + 'px', top: r.bottom + scrollY + 2 + 'px', minWidth: r.width + 'px' });
        };
        const mark = (text, tokens) => {
            let html = Matriosga.esc(text);
            tokens.forEach(t => { html = html.replace(new RegExp('(' + t.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + ')', 'ig'), '<mark>$1</mark>'); });
            return html;
        };
        const paint = () => menu.querySelectorAll('.dropdown-item').forEach((el, i) => el.classList.toggle('active', i === active));
        const select = value => {
            input.value = value;
            hide();
            input.dispatchEvent(new Event('change', { bubbles: true }));
        };
        const render = async () => {
            const tokens = Matriosga.norm(input.value).split(/\s+/).filter(Boolean);
            if (!tokens.length) return hide();
            const all = await getItems();
            const first = tokens[0];
            // Priorità: nome esatto, nome (senza schema) che inizia col testo, poi il resto
            const rank = v => { const n = Matriosga.norm(v), short = n.split('.').pop(); return short === first || n === first ? 0 : short.startsWith(first) ? 1 : n.startsWith(first) ? 2 : 3; };
            items = all.filter(v => { const n = Matriosga.norm(v); return tokens.every(t => n.includes(t)); })
                .sort((a, b) => rank(a) - rank(b) || a.length - b.length || a.localeCompare(b)).slice(0, max);
            if (!items.length) return hide();
            active = 0;
            menu.innerHTML = items.map((v, i) => `<button type="button" class="dropdown-item ident" data-i="${i}">${mark(v, tokens)}</button>`).join('');
            paint();
            place();
            menu.classList.add('show');
        };

        input.addEventListener('input', Matriosga.debounce(render, 80));
        input.addEventListener('focus', () => { if (input.value.trim()) render(); });
        input.addEventListener('blur', () => setTimeout(hide, 150));
        input.addEventListener('keydown', ev => {
            if (!menu.classList.contains('show')) return;
            if (ev.key === 'ArrowDown') { ev.preventDefault(); active = (active + 1) % items.length; paint(); }
            else if (ev.key === 'ArrowUp') { ev.preventDefault(); active = (active - 1 + items.length) % items.length; paint(); }
            else if (ev.key === 'Enter' && active >= 0) { ev.preventDefault(); select(items[active]); }
            else if (ev.key === 'Escape') { hide(); }
        });
        menu.addEventListener('mousedown', ev => {
            const btn = ev.target.closest('[data-i]');
            if (!btn) return;
            ev.preventDefault(); // non togliere il focus al campo
            select(items[+btn.dataset.i]);
        });
        window.addEventListener('resize', hide);
    };

    // Campi tabella: <input data-table-picker> (elenco nomi caricato una volta sola, al primo uso)
    let namesPromise = null;
    const tableNames = () => (namesPromise ??= Matriosga.api('/api/tables/names').catch(e => { namesPromise = null; return []; }));
    document.querySelectorAll('[data-table-picker]').forEach(input => Matriosga.autocomplete(input, tableNames));

    // <input data-autosubmit>: invia il form quando cambia. <button data-swap="#a,#b">: scambia i valori di due campi.
    document.addEventListener('change', ev => { if (ev.target.matches('[data-autosubmit]')) ev.target.form.submit(); });
    document.addEventListener('click', ev => {
        const btn = ev.target.closest('[data-swap]');
        if (!btn) return;
        const [a, b] = btn.dataset.swap.split(',').map(s => document.querySelector(s.trim()));
        [a.value, b.value] = [b.value, a.value];
    });

    // Tooltip Bootstrap
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));

    window.Matriosga = Matriosga;
})();
