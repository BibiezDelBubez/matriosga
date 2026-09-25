/* Impostazioni: campi SQL/Windows e "Testa connessione". */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('settings-form');
    const auth = document.getElementById('c_auth');
    const syncAuth = () => {
        const win = auth.value === 'windows';
        form.querySelectorAll('.auth-sql').forEach(el => el.classList.toggle('d-none', win));
        form.querySelectorAll('.auth-windows').forEach(el => el.classList.toggle('d-none', !win));
    };
    auth.addEventListener('change', syncAuth);
    syncAuth();

    document.getElementById('btn-test').addEventListener('click', async (ev) => {
        const out = document.getElementById('test-result');
        const E = Matriosga.esc;
        try {
            const d = await Matriosga.busy(ev.currentTarget, () => Matriosga.api('/api/settings/test', { method: 'POST', body: new FormData(form) }));
            out.innerHTML = `<div class="alert alert-success"><div class="fw-semibold mb-2"><i class="fa-solid fa-circle-check"></i> Connessione riuscita (${d.ms} ms)</div>
                <dl class="kv small">
                  <dt>Server</dt><dd class="ident">${E(d.server_name)}</dd>
                  <dt>Database</dt><dd class="ident">${E(d.database_name)}</dd>
                  <dt>Utente</dt><dd class="ident">${E(d.login_name)}</dd>
                  <dt>Versione</dt><dd>${E(d.version)} <span class="text-secondary">(${E(d.product_version)} · ${E(d.edition)})</span></dd>
                  <dt>Collation</dt><dd class="ident">${E(d.collation)}</dd>
                </dl></div>`;
        } catch (e) {
            out.innerHTML = `<div class="alert alert-danger"><div class="fw-semibold"><i class="fa-solid fa-circle-xmark"></i> Connessione non riuscita</div><div class="small mt-1">${E(e.message)}</div></div>`;
        }
    });
});
