/* Dettaglio tabella: anteprima dati caricata solo quando si apre la scheda; scheda attiva nell'URL. */
(function () {
    'use strict';

    const preview = document.getElementById('preview');
    let loaded = false;

    async function loadPreview() {
        if (loaded || !preview) return;
        loaded = true;
        try {
            const data = await Matriosga.api('/api/tables/preview', { params: { t: preview.dataset.table } });
            Matriosga.renderDataTable(preview, data);
        } catch (e) {
            loaded = false;
            preview.innerHTML = `<div class="alert alert-danger mb-0"><i class="fa-solid fa-circle-xmark"></i> ${Matriosga.esc(e.message)}
                <button type="button" class="btn btn-sm btn-outline-danger ms-2" id="preview-retry">Riprova</button></div>`;
            document.getElementById('preview-retry').addEventListener('click', loadPreview);
        }
    }

    document.querySelectorAll('[data-bs-toggle="tab"]').forEach(btn => btn.addEventListener('shown.bs.tab', () => {
        const tab = btn.id.replace('tab-', '');
        const url = new URL(location.href);
        url.searchParams.set('tab', tab);
        history.replaceState(null, '', url);
        if (tab === 'data') loadPreview();
    }));

    if (document.getElementById('tab-data')?.classList.contains('active')) loadPreview();
})();
