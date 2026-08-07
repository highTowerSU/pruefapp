<?php

declare(strict_types=1);

use RedBeanPHP\R;

/** Superadmin configuration for inspection types and their required qualifications. */
final class InspectionTypeController
{
    public static function index(array $params, bool $isHx): array
    {
        if (!current_user_is_superadmin()) return forbidden_response();
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $types = R::getAll('SELECT * FROM inspection_type ORDER BY sort_order, name');
            foreach ($types as $type) {
                $code = (string) $type['code'];
                R::exec('UPDATE inspection_type SET active = ?, default_interval_days = ?, updated_at = ? WHERE id = ?', [
                    isset($_POST['type_active'][$code]) ? 1 : 0,
                    max(1, (int) ($_POST['interval'][$code] ?? $type['default_interval_days'])),
                    date(DATE_ATOM), (int) $type['id'],
                ]);
            }
            foreach (R::getAll('SELECT * FROM inspection_type_requirement ORDER BY id') as $requirement) {
                $id = (int) $requirement['id'];
                R::exec('UPDATE inspection_type_requirement SET active = ?, validity_days = ?, requires_confirmation = ? WHERE id = ?', [
                    isset($_POST['requirement_active'][$id]) ? 1 : 0,
                    max(0, (int) ($_POST['validity_days'][$id] ?? $requirement['validity_days'] ?? 0)) ?: null,
                    isset($_POST['requires_confirmation'][$id]) ? 1 : 0,
                    $id,
                ]);
            }
            audit_log('pruefarten_konfiguriert', ['actor_id' => (int) current_user()->id]);
            $_SESSION['meldung'] = 'Prüfarten und Nachweisanforderungen wurden gespeichert.';
            return [303, ['Location' => url_for('admin/pruefarten')], ''];
        }
        $types = R::getAll('SELECT * FROM inspection_type ORDER BY sort_order, name');
        $requirements = R::getAll('SELECT * FROM inspection_type_requirement ORDER BY inspection_type_code, sort_order, id');
        $byType = [];
        foreach ($requirements as $requirement) $byType[(string) $requirement['inspection_type_code']][] = $requirement;
        $content = render_template('inspection_type_admin.php', compact('types', 'byType'));
        return [200, [], render_template('layout.php', ['title' => 'Prüfarten & Befähigungen', 'content' => $content])];
    }
}
