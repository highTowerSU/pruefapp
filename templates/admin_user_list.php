<?php
/** @var array<int, array<string, mixed>> $users */
/** @var array<string, string> $roleOptions */
?>
<div class="card shadow-sm">
    <div class="card-body">
        <p class="text-body-secondary">Verwalte hier die Rollen aller angemeldeten Nutzer*innen und öffne bei Bedarf den jeweiligen Eintrag im Keycloak-Adminbereich.</p>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col">Nutzer</th>
                        <th scope="col" class="text-nowrap">Letzter Login</th>
                        <th scope="col" class="text-nowrap">Rolle</th>
                        <th scope="col" class="text-end text-nowrap">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="4" class="text-center text-body-secondary py-4">Es wurden noch keine Nutzer synchronisiert.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="fw-semibold">
                                    <?= htmlspecialchars($user['name']) ?>
                                    <?php if (!empty($user['role']) && isset($roleOptions[$user['role']])): ?>
                                        <span class="badge text-bg-secondary ms-2" title="Rolle: <?= htmlspecialchars($roleOptions[$user['role']]) ?>">
                                            <?= htmlspecialchars($roleOptions[$user['role']]) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-body-secondary">
                                    <?php if (!empty($user['email'])): ?>
                                        <div><?= htmlspecialchars($user['email']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($user['preferred_username'])): ?>
                                        <div><?= htmlspecialchars($user['preferred_username']) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($user['sub'])): ?>
                                        <div class="text-break">ID: <?= htmlspecialchars($user['sub']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <div class="small text-body-secondary mt-1">Logins: <?= htmlspecialchars((string) $user['login_count']) ?></div>
                            </td>
                            <td>
                                <?php if ($user['last_login_at'] instanceof \DateTimeImmutable): ?>
                                    <?= htmlspecialchars($user['last_login_at']->format('d.m.Y H:i')) ?> Uhr
                                <?php elseif (!empty($user['raw_last_login_at'])): ?>
                                    <?= htmlspecialchars($user['raw_last_login_at']) ?>
                                <?php else: ?>
                                    <span class="text-body-secondary">Nie</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="post" action="<?= htmlspecialchars(url_for('admin/nutzer/' . $user['id'] . '/rolle'), ENT_QUOTES) ?>" class="d-flex align-items-center gap-2 flex-wrap">
                                    <label class="visually-hidden" for="role-<?= (int) $user['id'] ?>">Rolle</label>
                                    <select name="role" id="role-<?= (int) $user['id'] ?>" class="form-select form-select-sm w-auto">
                                        <?php foreach ($roleOptions as $value => $label): ?>
                                            <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= $value === $user['selected_role'] ? ' selected' : '' ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
                                </form>
                                <?php if (!empty($user['role_missing'])): ?>
                                    <div class="small text-warning mt-1">Keine explizite Rolle gesetzt – Standard &bdquo;Betrachter/in&ldquo; aktiv.</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <?php if (!empty($user['keycloak_url'])): ?>
                                    <a href="<?= htmlspecialchars($user['keycloak_url'], ENT_QUOTES) ?>" class="btn btn-outline-secondary btn-sm" target="_blank" rel="noopener">
                                        <i class="fa-solid fa-arrow-up-right-from-square me-1" aria-hidden="true"></i>
                                        Keycloak öffnen
                                    </a>
                                <?php else: ?>
                                    <span class="text-body-secondary small">Kein Keycloak-Link verfügbar</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
