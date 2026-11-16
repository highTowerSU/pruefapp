<div class="container py-4 py-md-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-7">
            <section class="card shadow-sm" aria-labelledby="companion-expired-title">
                <div class="card-body p-4 p-md-5">
                    <div class="text-center mb-4">
                        <i class="fa-solid fa-link-slash fa-3x text-secondary mb-3" aria-hidden="true"></i>
                        <h1 class="h3" id="companion-expired-title">Companion-Verbindung beendet</h1>
                        <p class="text-body-secondary mb-0">Die Sitzung ist abgelaufen oder wurde am Prüfplatz getrennt. Dieser Link ist nicht mehr gültig.</p>
                    </div>
                    <h2 class="h5">So verbindest du dich erneut</h2>
                    <ol class="mb-4">
                        <li class="mb-2">Öffne am Computer dein Profil oder die gewünschte Prüfung.</li>
                        <li class="mb-2">Erstelle dort unter „Companion“ eine neue Verbindung.</li>
                        <li>Scanne den neuen QR-Code mit deinem Smartphone.</li>
                    </ol>
                    <p class="small text-body-secondary">Bereits übertragene Fotos und Barcodes bleiben am Prüfplatz erhalten. Zuletzt nicht übertragene Eingaben musst du nach dem Verbinden erneut senden.</p>
                    <div class="text-center">
                        <a class="btn btn-primary" href="<?= htmlspecialchars(url_for(''), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-house me-2" aria-hidden="true"></i>Zur Prüfapp</a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
