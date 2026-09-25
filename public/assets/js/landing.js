/* Landing page: ricerca istantanea fra le funzioni (dati da config 'menu', card rese dal server). */
(function () {
    'use strict';

    const STOPWORDS = new Set(['il', 'lo', 'la', 'i', 'gli', 'le', 'un', 'uno', 'una', 'di', 'a', 'da', 'in', 'con', 'su', 'per', 'tra', 'fra',
        'e', 'o', 'che', 'del', 'della', 'dei', 'delle', 'nel', 'nella', 'al', 'alla', 'quali', 'quale', 'sono', 'cosa', 'come',
        'questa', 'questo', 'queste', 'questi', 'mi', 'ci', 'ha', 'hanno', 'un\'', 'l\'', 'voglio', 'vedere', 'trovare']);
    // Peso di ogni campo: il nome della funzione conta più delle domande.
    const WEIGHTS = { label: 10, keywords: 6, questions: 4, desc: 3 };
    const MIN_RELATIVE_SCORE = 0.4;

    const tools = JSON.parse(document.getElementById('tools-data').textContent);
    const input = document.getElementById('tool-search');
    const grid = document.getElementById('tool-grid');
    const empty = document.getElementById('tool-empty');
    const count = document.getElementById('tool-count');
    const cards = new Map([...grid.querySelectorAll('[data-tool]')].map(el => [el.dataset.tool, el]));

    const norm = Matriosga.norm;
    const tokenize = q => norm(q).split(/[^a-z0-9_]+/).filter(t => t.length > 1 && !STOPWORDS.has(t));

    // Indice pre-normalizzato
    const index = tools.map(t => ({
        path: t.path,
        fields: {
            label: [norm(t.label)],
            keywords: t.keywords.map(norm),
            questions: t.questions.map(norm),
            desc: [norm(t.desc)],
        },
        questions: t.questions,
    }));

    function score(entry, tokens, phrase) {
        let total = 0, matched = 0;
        for (const tok of tokens) {
            let best = 0;
            for (const [field, texts] of Object.entries(entry.fields)) {
                if (texts.some(tx => tx.includes(tok))) best = Math.max(best, WEIGHTS[field]);
            }
            if (best > 0) matched++;
            total += best;
        }
        if (matched === 0) return { score: 0, question: null };
        if (matched === tokens.length) total *= 1.5; // tutte le parole trovate
        if (Object.values(entry.fields).flat().some(tx => tx.includes(phrase))) total += 8; // frase intera

        // Domanda più pertinente da mostrare sotto la card
        let question = null, qBest = 0;
        entry.fields.questions.forEach((qn, i) => {
            const hits = tokens.filter(tok => qn.includes(tok)).length;
            if (hits > qBest) { qBest = hits; question = entry.questions[i]; }
        });
        return { score: total, question };
    }

    let active = -1;
    const visibleCards = () => [...grid.children].filter(el => !el.classList.contains('d-none'));
    const readyCards = () => visibleCards().filter(el => el.tagName === 'A');

    function setActive(i) {
        const list = readyCards();
        grid.querySelectorAll('.is-active').forEach(el => el.classList.remove('is-active'));
        active = list.length ? (i + list.length) % list.length : -1;
        if (active >= 0) list[active].classList.add('is-active');
    }

    function render() {
        const q = input.value.trim();
        const tokens = tokenize(q);
        const results = index.map((entry, order) => ({ entry, order, ...(tokens.length ? score(entry, tokens, norm(q)) : { score: 1, question: null }) }));
        if (tokens.length) results.sort((a, b) => b.score - a.score || a.order - b.order);
        // Nasconde i risultati molto meno pertinenti del migliore (es. parole generiche come "tabelle").
        const threshold = (results[0]?.score || 0) * MIN_RELATIVE_SCORE;

        let shown = 0;
        for (const r of results) {
            const card = cards.get(r.entry.path);
            const visible = r.score > 0 && r.score >= threshold;
            card.classList.toggle('d-none', !visible);
            const match = card.querySelector('.tool-match');
            match.classList.toggle('d-none', !r.question);
            match.querySelector('span').textContent = r.question || '';
            grid.appendChild(card); // riordina
            if (visible) shown++;
        }
        empty.classList.toggle('d-none', shown > 0 || q.length >= 2);
        count.textContent = tokens.length ? `${shown} ${shown === 1 ? 'funzione trovata' : 'funzioni trovate'}` : 'Tutte le funzioni';
        setActive(tokens.length ? 0 : -1);

        // Azioni rapide: usa il testo digitato come valore, nome tabella o nome colonna
        document.getElementById('quick-actions').classList.toggle('d-none', q.length < 2);
        document.getElementById('quick-text').textContent = q.length > 40 ? q.slice(0, 40) + '…' : q;
        document.querySelectorAll('[data-quick]').forEach(a => { a.href = Matriosga.url(a.dataset.quick, { q }); });

        const url = new URL(location.href);
        q ? url.searchParams.set('q', q) : url.searchParams.delete('q');
        history.replaceState(null, '', url);
    }

    input.addEventListener('input', render);
    input.addEventListener('keydown', ev => {
        if (ev.key === 'ArrowDown') { ev.preventDefault(); setActive(active + 1); }
        else if (ev.key === 'ArrowUp') { ev.preventDefault(); setActive(active - 1); }
        else if (ev.key === 'Enter') {
            // nessuna funzione corrisponde: il testo è probabilmente un nome di tabella
            const target = readyCards()[Math.max(active, 0)] ?? document.querySelector('[data-quick="/tables"]:not(.d-none)');
            if (target?.href) location.href = target.href;
        } else if (ev.key === 'Escape') { input.value = ''; render(); }
    });
    document.addEventListener('keydown', ev => {
        if (ev.key === '/' && document.activeElement !== input) { ev.preventDefault(); input.focus(); input.select(); }
    });
    document.querySelectorAll('[data-example]').forEach(btn => btn.addEventListener('click', () => {
        input.value = btn.dataset.example;
        render();
        input.focus();
    }));

    render();
})();
