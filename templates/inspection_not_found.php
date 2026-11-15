<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-8 col-xl-7">
            <section class="card shadow-sm" aria-labelledby="inspection-not-found-title">
                <div class="card-body p-4 p-md-5 text-center">
                    <i class="fa-solid fa-file-circle-question fa-3x text-secondary mb-3" aria-hidden="true"></i>
                    <p class="text-body-secondary mb-2">Fehler 404</p>
                    <h1 class="h3" id="inspection-not-found-title">Diese Prüfung ist nicht verfügbar</h1>
                    <p class="text-body-secondary mb-4">Der Link ist möglicherweise veraltet. Die Prüfung wurde eventuell gelöscht oder ist für dein Konto nicht zugänglich.</p>
                    <div class="d-flex justify-content-center gap-2 flex-wrap">
                        <a class="btn btn-primary" href="<?= htmlspecialchars(url_for('pruefungen'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-clipboard-check me-2" aria-hidden="true"></i>Zur Prüfungsübersicht</a>
                        <a class="btn btn-outline-secondary" href="<?= htmlspecialchars(url_for('geraete'), ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-plug me-2" aria-hidden="true"></i>Zur Geräteübersicht</a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</div>
