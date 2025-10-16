<?php
require_once 'lib/lib.inc.php';

if (!isset($_GET['kurs']) || !($kurs = R::load('kurs', $_GET['kurs'])) || !$kurs->id) {
    die("Ungültiger Kurs");
}

// Token neu generieren?
if (isset($_GET['neu'])) {
    $kurs->token = bin2hex(random_bytes(8));
    $kurs->uebermittlung_aktiv = 1; // automatisch aktivieren
    R::store($kurs);
    header("Location: link_erzeugen.php?kurs=" . $kurs->id);
    exit;
}

// Status umschalten?
if (isset($_GET['umschalten'])) {
    $kurs->uebermittlung_aktiv = $kurs->uebermittlung_aktiv ? 0 : 1;
    R::store($kurs);
    header("Location: link_erzeugen.php?kurs=" . $kurs->id);
    exit;
}

$link = 'https://' . $_SERVER['HTTP_HOST'] . '/moodle_user_gen/uebermitteln.php?token=' . $kurs->token;

$status = $kurs->uebermittlung_aktiv
    ? '<span class="badge bg-success">Link aktiv</span>'
    : '<span class="badge bg-danger">Link deaktiviert</span>';

$umschaltenText = $kurs->uebermittlung_aktiv ? 'deaktivieren' : 'aktivieren';

$content = <<<HTML
<p>Gib diesen Link an deine Kunden weiter:</p>
<div class="mb-2"><code>$link</code></div>
<p>Status: $status</p>

<a href="?kurs={$kurs->id}&umschalten=1" class="btn btn-outline-primary">
  Link $umschaltenText
</a>
<a href="?kurs={$kurs->id}&neu=1" class="btn btn-warning">Neuen Link erzeugen</a>
<a href="/kurse" class="btn btn-link">Zurück</a>
HTML;

echo render_template('layout.php', [
    'title' => 'Link zur Teilnehmerdateneingabe – ' . $kurs->name,
    'content' => $content
]);
