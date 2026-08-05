<?php
/** @var array<int, array<string, mixed>> $users */
/** @var array<string, string> $roleOptions */
/** @var array<int, object> $customers */
/** @var bool $canManageUsers */
?>
<div class="card shadow-sm">
    <div class="card-body">
        <p class="text-body-secondary">Verwalte hier die Rollen aller angemeldeten Nutzer*innen und öffne bei Bedarf den jeweiligen Eintrag im Keycloak-Adminbereich.</p>
        <div class="table-responsive">
            <table class="table table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th scope="col"><i class="fa-solid fa-user me-1" aria-hidden="true"></i>Nutzer</th>
                        <th scope="col"><i class="fa-solid fa-building me-1" aria-hidden="true"></i>Kundenzugriff</th>
                        <th scope="col" class="text-nowrap"><i class="fa-solid fa-clock-rotate-left me-1" aria-hidden="true"></i>Letzter Login</th>
                        <th scope="col" class="text-nowrap"><i class="fa-solid fa-user-shield me-1" aria-hidden="true"></i>Rolle</th>
                        <th scope="col" class="text-end text-nowrap">Aktionen</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="5" class="text-center text-body-secondary py-4">Es wurden noch keine Nutzer synchronisiert.</td>
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
                                <?php if ($canManageUsers): ?><form method="post" action="<?= htmlspecialchars(url_for('admin/nutzer/' . $user['id'] . '/kunden'), ENT_QUOTES) ?>">
                                    <div class="border rounded p-2 overflow-auto" style="max-height:12rem" aria-label="Kundenzugriff">
                                        <?php foreach ($customers as $customer): $customerId = (int) $customer->id; $assigned = in_array($customerId, $user['customer_ids'] ?? [], true); ?>
                                            <div class="mb-2">
                                                <label class="form-check mb-1"><input class="form-check-input" type="checkbox" name="customer_ids[]" value="<?= $customerId ?>"<?= $assigned ? ' checked' : '' ?>><span class="form-check-label"><i class="fa-solid fa-building me-1" aria-hidden="true"></i><?= htmlspecialchars(($customer->code ? $customer->code . ' · ' : '') . $customer->name) ?></span></label>
                                                <label class="form-check ms-4 small"><input class="form-check-input" type="checkbox" name="include_descendants[<?= $customerId ?>]" value="1"<?= !empty($user['customer_access'][$customerId]) ? ' checked' : '' ?>><span class="form-check-label">Unterkunden einbeziehen</span></label>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <button type="submit" class="btn btn-outline-primary btn-sm mt-2"><i class="fa-solid fa-floppy-disk me-1" aria-hidden="true"></i>Zugriff speichern</button>
                                </form><?php else: ?><span class="text-body-secondary small"><?php $assigned = array_values(array_filter(array_map(static fn($customer): string => ($customer->code ? $customer->code . ' · ' : '') . (string) $customer->name, $customers), static fn($label, $index): bool => in_array((int) ($customers[$index]->id ?? 0), $user['customer_ids'] ?? [], true), ARRAY_FILTER_USE_BOTH)); ?><?= htmlspecialchars($assigned === [] ? 'Keine Zuordnung' : implode(', ', $assigned)) ?></span><?php endif; ?>
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
                                <?php if ($canManageUsers): ?><form method="post" action="<?= htmlspecialchars(url_for('admin/nutzer/' . $user['id'] . '/rolle'), ENT_QUOTES) ?>" class="d-flex align-items-center gap-2 flex-wrap">
                                    <label class="visually-hidden" for="role-<?= (int) $user['id'] ?>">Rolle</label>
                                    <select name="role" id="role-<?= (int) $user['id'] ?>" class="form-select form-select-sm w-auto">
                                        <?php foreach ($roleOptions as $value => $label): ?>
                                            <option value="<?= htmlspecialchars($value, ENT_QUOTES) ?>"<?= $value === $user['selected_role'] ? ' selected' : '' ?>>
                                                <?= htmlspecialchars($label) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" class="btn btn-primary btn-sm">Speichern</button>
                                </form><?php else: ?><span class="badge text-bg-secondary"><?= htmlspecialchars($roleOptions[$user['selected_role']] ?? $user['selected_role']) ?></span><?php endif; ?>
                                <?php if (!empty($user['role_missing'])): ?>
                                    <div class="small text-warning mt-1">Keine explizite Rolle gesetzt – Standard &bdquo;Betrachter/in&ldquo; aktiv.</div>
                                <?php endif; ?>
                            </td>
                            <td class="text-end">
                                <a href="<?= htmlspecialchars(url_for('admin/nutzer/' . (int) $user['id'] . '/profil'), ENT_QUOTES) ?>" class="btn btn-outline-primary btn-sm mb-1">
                                    <i class="fa-solid fa-id-card me-1" aria-hidden="true"></i>Profil ansehen<?= $canManageUsers ? '/bearbeiten' : '' ?>
                                </a>
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
