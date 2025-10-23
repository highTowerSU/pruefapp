<?php

use RedBeanPHP\SimpleModel;

class Model_Teilnehmer extends SimpleModel
{
    public function update(): void
    {
        $bean = $this->bean;

        $bean->vorname = trim((string) ($bean->vorname ?? ''));
        $bean->nachname = trim((string) ($bean->nachname ?? ''));
        $bean->geburtsdatum = normalize_birthdate((string) ($bean->geburtsdatum ?? ''));
        $bean->geburtsort = trim((string) ($bean->geburtsort ?? ''));
        $bean->benutzername = sanitize_username((string) ($bean->benutzername ?? ''));
        $bean->email = normalize_email_address((string) ($bean->email ?? ''));
        $bean->passwort = (string) ($bean->passwort ?? '');

        if ($bean->vorname === '') {
            throw new \InvalidArgumentException('Bitte gib einen Vornamen an.');
        }

        if ($bean->nachname === '') {
            throw new \InvalidArgumentException('Bitte gib einen Nachnamen an.');
        }

        if ($bean->geburtsdatum === '') {
            throw new \InvalidArgumentException('Bitte gib ein Geburtsdatum an.');
        }

        $kurs = $bean->fetchAs('kurs')->kurs;
        if ($kurs && (int) $kurs->feld_geburtsort_aktiv === 1) {
            if ($bean->geburtsort === '') {
                throw new \InvalidArgumentException('Bitte gib einen Geburtsort an.');
            }
        }

        if ($bean->benutzername === '' && $bean->vorname !== '' && $bean->nachname !== '') {
            $bean->benutzername = generate_username($bean->vorname, $bean->nachname);
        }

        if ($bean->benutzername === '') {
            throw new \InvalidArgumentException('Es konnte kein Benutzername erzeugt werden.');
        }

        $bean->benutzername = ensure_unique_username(
            $bean->benutzername,
            isset($bean->id) ? (int) $bean->id : null
        );

        if ($bean->passwort === '') {
            $bean->passwort = generate_password();
        }

        if ($bean->email === '' && $bean->benutzername !== '') {
            $bean->email = generate_email($bean->benutzername);
        }

        $bean->email = normalize_email_address((string) $bean->email);

        if ($bean->email === '') {
            throw new \InvalidArgumentException('Bitte gib eine E-Mail-Adresse an.');
        }

        if (isset($bean->moodle_user_id)) {
            $moodleUserId = (int) $bean->moodle_user_id;
            $bean->moodle_user_id = $moodleUserId > 0 ? $moodleUserId : null;
        }

        if (isset($bean->moodle_username)) {
            $moodleUsername = sanitize_username((string) $bean->moodle_username);
            $bean->moodle_username = $moodleUsername !== '' ? $moodleUsername : null;
        }

        if (isset($bean->moodle_idnumber)) {
            $idnumber = trim((string) $bean->moodle_idnumber);
            $bean->moodle_idnumber = $idnumber !== '' ? $idnumber : null;
        }

        if (isset($bean->moodle_last_sync_at)) {
            $syncValue = trim((string) $bean->moodle_last_sync_at);
            if ($syncValue === '') {
                $bean->moodle_last_sync_at = null;
            } else {
                try {
                    $timestamp = new \DateTimeImmutable($syncValue);
                    $bean->moodle_last_sync_at = $timestamp->format(DATE_ATOM);
                } catch (\Throwable) {
                    $bean->moodle_last_sync_at = null;
                }
            }
        }
    }
}
