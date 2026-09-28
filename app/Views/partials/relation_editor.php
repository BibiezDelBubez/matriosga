<?php /** Finestra "Nuova relazione" (gestita da js/relation-editor.js). Includere una volta nella pagina. */ ?>
<div class="modal fade" id="rel-editor" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fa-solid fa-user-pen text-success"></i> Nuova relazione definita da te</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p class="small text-secondary">Per i collegamenti che conosci ma che il database non dichiara. Una volta salvata, la relazione
                    viene usata da Percorso, Grafo e Costruttore di query (con priorità sulle FK).</p>
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <label class="form-label" for="re-from">Tabella che punta</label>
                        <input class="form-control ident" id="re-from" data-table-picker placeholder="es. T2ViaggiVettoriClienti">
                        <div class="form-text">Quella che contiene le colonne "di collegamento".</div>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label" for="re-to">Tabella puntata</label>
                        <input class="form-control ident" id="re-to" data-table-picker placeholder="es. T2ViaggiClienti">
                        <div class="form-text">Di solito quella di cui si usa la chiave primaria.</div>
                    </div>
                </div>
                <div id="re-suggest" class="mb-2"></div>
                <div id="re-pairs"></div>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="re-add-pair" disabled><i class="fa-solid fa-plus"></i> Coppia di colonne</button>
                <div class="mt-3">
                    <label class="form-label" for="re-note">Nota <span class="text-secondary">(facoltativa)</span></label>
                    <input class="form-control" id="re-note" maxlength="200" placeholder="es. riga del viaggio vettore → ordine cliente">
                </div>
                <div id="re-verify" class="mt-3"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" id="re-verify-btn"><i class="fa-solid fa-vial"></i> Verifica sui dati</button>
                <button type="button" class="btn btn-success" id="re-save"><i class="fa-solid fa-floppy-disk"></i> Salva relazione</button>
            </div>
        </div>
    </div>
</div>
