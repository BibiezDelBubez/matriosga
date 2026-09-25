/* Grafo relazioni con Cytoscape.js (locale). Dati da POST /api/graph. */
(function () {
    'use strict';

    const $ = id => document.getElementById(id);
    const E = Matriosga.esc;
    const form = $('graph-form');
    const info = $('g-info');
    const status = $('g-status');

    if (typeof cytoscape === 'undefined') {
        $('cy').innerHTML = '<div class="empty-state">Libreria Cytoscape non trovata: controllare <span class="ident">config/assets.php</span> e <span class="ident">public/assets/vendor/cytoscape/</span>.</div>';
        return;
    }

    const css = getComputedStyle(document.documentElement);
    const accent = css.getPropertyValue('--mt-accent').trim() || '#4f5bd5';
    const cy = cytoscape({
        container: $('cy'),
        wheelSensitivity: 0.25,
        minZoom: 0.05,
        maxZoom: 3,
        style: [
            { selector: 'node', style: {
                'shape': 'round-rectangle', 'background-color': '#eef0fd', 'border-width': 1, 'border-color': '#c9cdf6',
                'label': 'data(label)', 'font-size': 11, 'font-family': 'Consolas, monospace', 'color': '#1f2433',
                'text-valign': 'center', 'text-halign': 'center', 'width': 'label', 'height': 24, 'padding': '8px',
            } },
            { selector: 'node[?hub]', style: { 'background-color': '#fff4e0', 'border-color': '#f5cf8c' } },
            { selector: 'node[?view]', style: { 'border-style': 'dashed' } },
            { selector: 'node[?center]', style: { 'background-color': accent, 'border-color': accent, 'color': '#fff', 'font-weight': 'bold' } },
            { selector: 'edge', style: {
                'width': 'mapData(count, 1, 10, 1.4, 5)', 'line-color': '#7aa7e6', 'target-arrow-color': '#7aa7e6', 'target-arrow-shape': 'triangle',
                'arrow-scale': 0.8, 'curve-style': 'bezier', 'label': 'data(label)', 'font-size': 9, 'color': '#4b5263',
                'text-background-color': '#fff', 'text-background-opacity': 1, 'text-background-padding': '1px',
            } },
            { selector: 'edge[kind = "candidate"]', style: { 'line-style': 'dashed', 'line-color': '#b58ae6', 'target-arrow-color': '#b58ae6' } },
            { selector: '.faded', style: { 'opacity': 0.12 } },
            { selector: 'node.hl', style: { 'border-width': 3, 'border-color': '#f59e0b' } },
            { selector: 'edge.hl', style: { 'width': 3.5, 'line-color': '#f59e0b', 'target-arrow-color': '#f59e0b', 'opacity': 1 } },
            { selector: ':selected', style: { 'border-width': 3, 'border-color': '#0b4fa8' } },
            { selector: '.hidden', style: { 'display': 'none' } },
        ],
    });

    let center = null;

    function options() {
        return { depth: +$('g-depth').value, cand: $('g-cand').checked, hubs: $('g-hubs').checked };
    }

    function syncUrl() {
        const url = new URL(location.href);
        const o = options();
        url.searchParams.set('t', center || '');
        url.searchParams.set('depth', o.depth);
        o.cand ? url.searchParams.set('cand', 1) : url.searchParams.delete('cand');
        o.hubs ? url.searchParams.set('hubs', 1) : url.searchParams.delete('hubs');
        url.searchParams.delete('nodes');
        url.searchParams.delete('path');
        history.replaceState(null, '', url);
    }

    /** Carica il vicinato di `table`; expand=true aggiunge al grafo esistente. */
    async function load(table, { expand = false, depth = null, extra = [] } = {}) {
        const o = options();
        status.textContent = 'Caricamento…';
        try {
            const existing = expand ? cy.nodes().map(n => n.id()) : [];
            const d = await Matriosga.api('/api/graph', { body: { t: table, depth: depth ?? o.depth, cand: o.cand, hubs: o.hubs, extra: existing.concat(extra) } });
            if (!expand) {
                cy.elements().remove();
                center = d.nodes.find(n => n.data.center)?.data.id ?? table;
            } else {
                d.nodes.forEach(n => { n.data.center = n.data.id === center; });
            }
            cy.add(d.nodes.filter(n => cy.getElementById(n.data.id).empty()));
            cy.add(d.edges.filter(e => cy.getElementById(e.data.id).empty()));
            runLayout();
            let msg = `${cy.nodes().length} tabelle, ${cy.edges().length} relazioni.`;
            if (d.truncated) msg += ' Limite nodi raggiunto: aumenta «Grafo: nodi massimi» in Impostazioni o riduci i livelli.';
            if (d.hidden_hubs) msg += ` ${d.hidden_hubs} tabelle hub nascoste.`;
            status.textContent = msg;
            if (!expand) syncUrl();
        } catch (e) {
            status.textContent = '';
            Matriosga.toast(e.message, 'danger');
        }
    }

    function runLayout() {
        const name = $('g-layout').value;
        const big = cy.nodes().length > 80;
        const opts = { name, animate: !big, fit: true, padding: 30 };
        if (name === 'cose') Object.assign(opts, { nodeRepulsion: () => 9000, idealEdgeLength: () => 90, numIter: big ? 600 : 1000 });
        if (name === 'breadthfirst') Object.assign(opts, { roots: center ? cy.getElementById(center) : undefined, spacingFactor: 1.1, directed: false });
        if (name === 'concentric') Object.assign(opts, { concentric: n => 10 - (n.data('level') ?? 5), levelWidth: () => 1, minNodeSpacing: 20 });
        cy.elements(':visible').layout(opts).run();
    }

    function clearHighlight() {
        cy.elements().removeClass('faded hl');
    }

    function showNode(n) {
        const d = n.data();
        const tableUrl = Matriosga.url('/tables/show', { t: d.id });
        info.innerHTML = `<div class="fw-semibold ident text-break mb-1">${E(d.id)} ${Matriosga.copyBtn(d.id)}</div>
            <div class="mb-2">${d.view ? '<span class="badge text-bg-info">Vista</span> ' : ''}${d.hub ? '<span class="badge text-bg-warning">hub</span> ' : ''}
              <span class="badge badge-type">${d.rows === null ? '—' : Matriosga.fmt(d.rows)} righe</span> <span class="badge badge-type">${d.ncols} colonne</span></div>
            ${d.pk.length ? `<div class="mb-2"><span class="badge badge-pk"><i class="fa-solid fa-key"></i> ${E(d.pk.join(', '))}</span></div>` : ''}
            <div class="text-secondary mb-2">Referenziata da ${Matriosga.fmt(d.refs)} FK · collegamenti visibili: ${n.connectedEdges(':visible').length}</div>
            <div class="d-grid gap-1">
              <a class="btn btn-sm btn-outline-primary" href="${E(tableUrl)}"><i class="fa-solid fa-table"></i> Apri tabella</a>
              <button class="btn btn-sm btn-outline-secondary" data-g="expand"><i class="fa-solid fa-up-right-and-down-left-from-center"></i> Espandi vicini</button>
              <button class="btn btn-sm btn-outline-secondary" data-g="center"><i class="fa-solid fa-crosshairs"></i> Riparti da qui</button>
              <button class="btn btn-sm btn-outline-secondary" data-g="neighbors"><i class="fa-solid fa-circle-nodes"></i> Evidenzia collegate</button>
              <button class="btn btn-sm btn-outline-secondary" data-g="hide"><i class="fa-regular fa-eye-slash"></i> Nascondi</button>
            </div>`;
        info.dataset.node = d.id;
    }

    function showEdge(ed) {
        const d = ed.data();
        const link = t => `<a class="ident text-break" href="${E(Matriosga.url('/tables/show', { t }))}">${E(t)}</a>`;
        const rels = d.rels.map(r => {
            const verify = JSON.stringify({ from: d.source, from_cols: r.from_cols, to: d.target, to_cols: r.to_cols });
            return `<div class="fk-card small">
                <div class="mb-1">${d.kind === 'fk' ? `<span class="badge badge-fk">FK</span> <span class="ident text-secondary text-break">${E(r.name)}</span>` : `<span class="badge badge-cand">candidata ${r.score}%</span>`}</div>
                <div class="ident">(${E(r.from_cols.join(', '))})</div>
                <div class="text-secondary"><i class="fa-solid fa-arrow-down"></i></div>
                <div class="ident mb-1">(${E(r.to_cols.join(', '))})</div>
                <button class="btn btn-sm btn-link p-0" data-copy="${E(r.join)}"><i class="fa-regular fa-copy"></i> JOIN</button>
                ${d.kind === 'candidate' ? `<button class="btn btn-sm btn-outline-secondary py-0 ms-2" data-verify="${E(verify)}"><i class="fa-solid fa-vial"></i> Verifica</button>` : ''}
            </div>`;
        }).join('');
        info.innerHTML = `<div class="mb-2">${link(d.source)}<div class="text-secondary"><i class="fa-solid fa-arrow-down"></i> punta a (${d.count} ${d.count === 1 ? 'relazione' : 'relazioni'})</div>${link(d.target)}</div>${rels}`;
        delete info.dataset.node;
    }

    function highlightPath(targetId) {
        clearHighlight();
        const target = cy.getElementById(targetId);
        if (!center || target.empty()) { Matriosga.toast('Tabella non presente nel grafo', 'warning'); return; }
        const res = cy.elements(':visible').aStar({ root: cy.getElementById(center), goal: target, directed: false });
        if (!res.found) { Matriosga.toast('Nessun percorso visibile nel grafo', 'warning'); return; }
        cy.elements().addClass('faded');
        res.path.removeClass('faded').addClass('hl');
        cy.animate({ fit: { eles: res.path, padding: 60 } }, { duration: 300 });
        status.textContent = `Percorso di ${res.path.edges().length} passaggi evidenziato.`;
    }

    cy.on('tap', 'node', ev => showNode(ev.target));
    cy.on('tap', 'edge', ev => showEdge(ev.target));
    cy.on('tap', ev => { if (ev.target === cy) clearHighlight(); });

    info.addEventListener('click', ev => {
        const act = ev.target.closest('[data-g]')?.dataset.g;
        const id = info.dataset.node;
        if (!act || !id) return;
        const n = cy.getElementById(id);
        if (act === 'expand') load(id, { expand: true, depth: 1 });
        if (act === 'center') { $('g-table').value = id; load(id); }
        if (act === 'hide') { n.addClass('hidden'); info.innerHTML = '<div class="text-secondary">Nodo nascosto.</div>'; }
        if (act === 'neighbors') {
            clearHighlight();
            cy.elements().addClass('faded');
            n.closedNeighborhood().removeClass('faded').addClass('hl');
        }
    });

    form.addEventListener('submit', ev => { ev.preventDefault(); if ($('g-table').value.trim()) load($('g-table').value.trim()); });
    $('g-relayout').addEventListener('click', runLayout);
    $('g-layout').addEventListener('change', runLayout);
    $('g-fit').addEventListener('click', () => cy.fit(cy.elements(':visible'), 30));
    $('g-unhide').addEventListener('click', () => cy.elements('.hidden').removeClass('hidden'));
    $('g-png').addEventListener('click', () => {
        const a = Object.assign(document.createElement('a'), { href: cy.png({ full: true, scale: 2, bg: '#ffffff' }), download: `grafo-${center || 'matriosga'}.png` });
        a.click();
    });
    $('g-find').addEventListener('change', ev => {
        const n = cy.getElementById(ev.target.value.trim());
        if (n.empty()) return;
        cy.elements().unselect();
        n.select();
        cy.animate({ center: { eles: n }, zoom: Math.max(cy.zoom(), 1) }, { duration: 300 });
        showNode(n);
    });
    $('g-path').addEventListener('change', ev => highlightPath(ev.target.value.trim()));
    const graphNodes = () => cy.nodes(':visible').map(n => n.id());
    Matriosga.autocomplete($('g-find'), graphNodes);
    Matriosga.autocomplete($('g-path'), graphNodes);

    // Avvio: da URL (?t=..., eventualmente ?nodes=a,b,c&path=destinazione dalla pagina Percorso)
    const start = $('g-table').value.trim();
    if (start) {
        const extra = (form.dataset.nodes || '').split(',').map(s => s.trim()).filter(Boolean);
        load(start, { extra }).then(() => { if (form.dataset.path) { $('g-path').value = form.dataset.path; highlightPath(form.dataset.path); } });
    }
})();
