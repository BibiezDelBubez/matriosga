<?php /** Schede di Impostazioni. $current = 'connection'|'environment' */ ?>
<ul class="nav nav-pills mb-3">
    <li class="nav-item"><a class="nav-link<?= $current === 'connection' ? ' active' : '' ?>" href="<?= e(url('/settings')) ?>"><i class="fa-solid fa-database"></i> Connessione e limiti</a></li>
    <li class="nav-item"><a class="nav-link<?= $current === 'environment' ? ' active' : '' ?>" href="<?= e(url('/settings/environment')) ?>"><i class="fa-solid fa-server"></i> Ambiente server</a></li>
</ul>
