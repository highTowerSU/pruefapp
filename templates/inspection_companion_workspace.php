<section class="card shadow-sm" id="companion-workspace-root">
  <div class="card-header fw-semibold"><i class="fa-solid fa-mobile-screen-button me-2" aria-hidden="true"></i>Companion verbunden</div>
  <div class="card-body">
    <div class="alert alert-success"><i class="fa-solid fa-link me-1" aria-hidden="true"></i>Dieses Smartphone ist für den aktuellen Arbeitstag mit deinem Prüfplatz verbunden.</div>
    <p class="text-body-secondary">Du kannst jetzt auf dem Computer Geräte anlegen oder eine Prüfung öffnen. Der Companion bleibt verbunden; Barcode- und Fotoerfassung werden dem gerade gewählten Gerät oder der Prüfung zugeordnet.</p>
    <section class="border rounded-3 p-3"><h2 class="h6"><i class="fa-solid fa-barcode me-2" aria-hidden="true"></i>Barcode bereithalten</h2><p class="small text-body-secondary">Sobald du am Prüfplatz ein Gerät oder eine Prüfung auswählst, kann der Barcode hierüber übertragen werden.</p><form hx-post="<?= htmlspecialchars(url_for('companion/' . $session['token'] . '/barcode'), ENT_QUOTES) ?>" hx-target="#companion-workspace-result" hx-swap="innerHTML" class="input-group"><input class="form-control" name="barcode" placeholder="Barcode eingeben" autocomplete="off"><button class="btn btn-primary" type="submit"><i class="fa-solid fa-check me-1" aria-hidden="true"></i>Senden</button></form><div id="companion-workspace-result" class="mt-2"></div></section>
  </div>
</section>
