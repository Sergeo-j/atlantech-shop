<?php
/**
 * Page : Taux de Change USD / HTG
 * Réservé au Directeur Général — AtlanTech DG Admin
 *
 * Permet de consulter et modifier le taux du jour.
 * Toutes les modifications sont tracées via log_dg_action().
 */
require_once __DIR__ . '/../includes/auth.php';
require_dg_auth();

$page_title = 'Taux de Change USD / HTG';
$dg_id      = (int) $_SESSION['dg_id'];
$dg_name    = $_SESSION['dg_full_name'] ?? ($_SESSION['dg_name'] ?? 'DG');

$flash = ['type' => null, 'message' => null];

// ─── Init tables si nécessaires ─────────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `atl_settings` (
        `setting_key`   VARCHAR(64)  NOT NULL,
        `setting_value` TEXT         NOT NULL,
        `description`   VARCHAR(255) DEFAULT NULL,
        `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_by`    INT          DEFAULT NULL,
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `atl_settings_history` (
        `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
        `setting_key` VARCHAR(64)  NOT NULL,
        `old_value`   TEXT         NOT NULL,
        `new_value`   TEXT         NOT NULL,
        `actor_type`  VARCHAR(20)  NOT NULL DEFAULT 'admin',
        `actor_id`    INT          DEFAULT NULL,
        `actor_name`  VARCHAR(120) DEFAULT NULL,
        `changed_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_key_date` (`setting_key`, `changed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("INSERT IGNORE INTO `atl_settings` (`setting_key`,`setting_value`,`description`)
                VALUES ('usd_to_htg','130','Taux de change du jour : 1 USD = X HTG')");

    // Migration douce : ajouter les colonnes manquantes si la table existait avec l'ancienne structure
    $cols = $pdo->query("SHOW COLUMNS FROM atl_settings_history")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('actor_type', $cols)) {
        $pdo->exec("ALTER TABLE atl_settings_history
            ADD COLUMN `actor_type` VARCHAR(20) NOT NULL DEFAULT 'admin' AFTER `new_value`,
            ADD COLUMN `actor_id`   INT          DEFAULT NULL              AFTER `actor_type`,
            ADD COLUMN `actor_name` VARCHAR(120) DEFAULT NULL              AFTER `actor_id`");
    }
} catch (PDOException $e) {
    error_log('dg-admin/taux-change init: ' . $e->getMessage());
}

// ─── Traitement POST ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $new_rate = filter_input(INPUT_POST, 'usd_to_htg', FILTER_VALIDATE_FLOAT);

    if ($new_rate === false || $new_rate < 1 || $new_rate > 99999) {
        $flash = ['type' => 'danger', 'message' => 'Taux invalide. Entrez un nombre entre 1 et 99 999.'];
    } else {
        try {
            $old = (float) $pdo->query("SELECT setting_value FROM atl_settings WHERE setting_key='usd_to_htg'")->fetchColumn();

            if (abs($old - $new_rate) < 0.001) {
                $flash = ['type' => 'success', 'message' => "Le taux est déjà à " . number_format($new_rate, 2) . " HTG — aucune modification."];
            } else {
                $pdo->prepare("UPDATE atl_settings SET setting_value=?, updated_by=?, updated_at=NOW() WHERE setting_key='usd_to_htg'")
                    ->execute([(string)$new_rate, $dg_id]);

                $pdo->prepare("INSERT INTO atl_settings_history (setting_key,old_value,new_value,actor_type,actor_id,actor_name)
                               VALUES ('usd_to_htg',?,?,'dg',?,?)")
                    ->execute([(string)$old, (string)$new_rate, $dg_id, $dg_name]);

                // Invalider le cache APCu si disponible
                if (function_exists('apcu_delete')) apcu_delete('atl_usd_to_htg');

                log_dg_action($dg_id, 'taux_change_update',
                    "Taux USD/HTG : {$old} → {$new_rate} HTG");

                $flash = ['type' => 'success', 'message' => "Taux mis à jour : 1 USD = " . number_format($new_rate, 2) . " HTG — effectif immédiatement sur la boutique."];
            }
        } catch (PDOException $e) {
            error_log('dg-admin/taux-change update: ' . $e->getMessage());
            $flash = ['type' => 'danger', 'message' => 'Erreur base de données. Réessayez.'];
        }
    }
}

// ─── Lecture taux actuel + historique ───────────────────────────────────────
try {
    $current_rate = (float) $pdo->query("SELECT setting_value FROM atl_settings WHERE setting_key='usd_to_htg'")->fetchColumn();
} catch (PDOException $e) { $current_rate = 130.0; }

try {
    $history = $pdo->query("
        SELECT * FROM atl_settings_history
        WHERE setting_key = 'usd_to_htg'
        ORDER BY changed_at DESC
        LIMIT 30
    ")->fetchAll();
} catch (PDOException $e) { $history = []; }

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-dollar-sign"></i> Taux de Change USD / HTG</h2>
    <span class="text-muted">Taux du jour appliqué sur toute la boutique</span>
</div>

<?php if ($flash['type']): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : 'exclamation-circle' ?>"></i>
    <?= htmlspecialchars($flash['message']) ?>
</div>
<?php endif; ?>

<!-- Grille taux actuel + formulaire -->
<div class="form-row" style="grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px;">

    <!-- Taux actuel -->
    <div class="card" style="margin-bottom:0">
        <div class="card-header">
            <h3><i class="fas fa-chart-line"></i> Taux actuel</h3>
        </div>
        <div style="text-align:center; padding: 28px 20px;">
            <div style="font-size: 56px; font-weight: 900; color: var(--color-primary, #2563eb); line-height: 1;">
                <?= number_format($current_rate, 2) ?>
            </div>
            <div style="font-size: 18px; color: #6b7280; margin: 8px 0 20px; font-weight: 600;">
                HTG par 1 USD
            </div>
            <div style="background: #f9fafb; border-radius: 10px; padding: 16px; font-size: 14px; color: #374151;">
                <div style="margin-bottom: 8px;">
                    <strong>1 000 HTG</strong> →
                    <strong style="color: #10b981;">$<?= number_format(1000 / $current_rate, 2) ?> USD</strong>
                </div>
                <div style="margin-bottom: 8px;">
                    <strong>10 000 HTG</strong> →
                    <strong style="color: #10b981;">$<?= number_format(10000 / $current_rate, 0) ?> USD</strong>
                </div>
                <div>
                    <strong>100 000 HTG</strong> →
                    <strong style="color: #10b981;">$<?= number_format(100000 / $current_rate, 0) ?> USD</strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire de mise à jour -->
    <div class="card" style="margin-bottom:0">
        <div class="card-header">
            <h3><i class="fas fa-edit"></i> Mettre à jour le taux du jour</h3>
        </div>
        <div style="padding: 24px;">
            <form method="POST">
                <?= csrf_field() ?>

                <div class="form-group">
                    <label class="form-label">1 USD = combien de HTG ?</label>
                    <div style="display:flex; align-items:stretch; border-radius:8px; overflow:hidden; border:2px solid #e5e7eb;">
                        <span style="background:#f3f4f6; color:#6b7280; padding:12px 16px; font-size:14px; white-space:nowrap; border-right:1px solid #e5e7eb;">
                            $1 USD =
                        </span>
                        <input type="number"
                               name="usd_to_htg"
                               class="form-input"
                               value="<?= number_format($current_rate, 2, '.', '') ?>"
                               min="1" max="99999" step="0.01"
                               required
                               style="border:none; border-radius:0; font-size:22px; font-weight:700; flex:1; padding:12px 16px; border-right:1px solid #e5e7eb;"
                               oninput="updatePreview(this.value)">
                        <span style="background:#f3f4f6; color:#6b7280; padding:12px 16px; font-size:14px; font-weight:600;">
                            HTG
                        </span>
                    </div>
                    <div id="preview" style="margin-top:10px; font-size:13px; color:#6b7280; min-height:20px;"></div>
                </div>

                <button type="submit" class="btn btn-accent" style="width:100%; padding:14px; font-size:15px; font-weight:700;">
                    <i class="fas fa-save"></i> Enregistrer le taux du jour
                </button>
            </form>

            <!-- Sources officielles -->
            <div style="margin-top:20px; padding:14px; background:#eff6ff; border-radius:8px; border:1px solid #bfdbfe;">
                <p style="font-size:11px; font-weight:700; color:#1e40af; margin:0 0 10px; text-transform:uppercase; letter-spacing:.5px;">
                    Sources officielles du taux
                </p>
                <div style="display:flex; flex-wrap:wrap; gap:6px;">
                    <a href="https://www.brh.ht" target="_blank" rel="noopener"
                       style="font-size:12px; color:#1d4ed8; text-decoration:none; background:#fff; padding:4px 10px; border-radius:14px; border:1px solid #93c5fd;">
                        <i class="fas fa-external-link-alt" style="font-size:9px;"></i> BRH
                    </a>
                    <a href="https://www.xe.com/currencyconverter/convert/?Amount=1&From=USD&To=HTG" target="_blank" rel="noopener"
                       style="font-size:12px; color:#1d4ed8; text-decoration:none; background:#fff; padding:4px 10px; border-radius:14px; border:1px solid #93c5fd;">
                        <i class="fas fa-external-link-alt" style="font-size:9px;"></i> XE.com
                    </a>
                    <a href="https://moncashbutton.digicelgroup.com" target="_blank" rel="noopener"
                       style="font-size:12px; color:#1d4ed8; text-decoration:none; background:#fff; padding:4px 10px; border-radius:14px; border:1px solid #93c5fd;">
                        <i class="fas fa-external-link-alt" style="font-size:9px;"></i> MonCash
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Historique des modifications -->
<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-history"></i> Historique des modifications</h3>
        <span class="text-muted"><?= count($history) ?> entrée(s)</span>
    </div>

    <?php if (empty($history)): ?>
        <p class="text-muted text-center" style="padding:30px;">Aucune modification enregistrée pour l'instant.</p>
    <?php else: ?>
    <table class="data">
        <thead>
            <tr>
                <th>Date & Heure</th>
                <th style="text-align:center;">Ancien taux</th>
                <th style="text-align:center;">Nouveau taux</th>
                <th style="text-align:center;">Variation</th>
                <th>Modifié par</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($history as $h):
                $old_v = (float) $h['old_value'];
                $new_v = (float) $h['new_value'];
                $diff  = $new_v - $old_v;
                $pct   = $old_v > 0 ? ($diff / $old_v) * 100 : 0;
                $actor = $h['actor_type'] ?? 'admin';
            ?>
            <tr>
                <td class="text-muted" style="white-space:nowrap;">
                    <?= date('d/m/Y H:i', strtotime($h['changed_at'])) ?>
                </td>
                <td style="text-align:center; color:#6b7280;">
                    <?= number_format($old_v, 2) ?> HTG
                </td>
                <td style="text-align:center; font-weight:700;">
                    <?= number_format($new_v, 2) ?> HTG
                </td>
                <td style="text-align:center;">
                    <?php if ($diff > 0): ?>
                        <span class="badge badge-danger">▲ +<?= number_format($pct, 1) ?>%</span>
                    <?php elseif ($diff < 0): ?>
                        <span class="badge badge-success">▼ <?= number_format($pct, 1) ?>%</span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span style="display:inline-flex; align-items:center; gap:8px;">
                        <span style="width:28px; height:28px; border-radius:50%; background:<?= $actor === 'superadmin' ? '#a855f7' : ($actor === 'dg' ? '#f59e0b' : '#2563eb') ?>; color:#fff; display:inline-flex; align-items:center; justify-content:center; font-size:11px; font-weight:700;">
                            <?= strtoupper(substr($h['actor_name'] ?? 'S', 0, 1)) ?>
                        </span>
                        <span><?= htmlspecialchars($h['actor_name'] ?? 'Système') ?></span>
                        <span class="badge <?= $actor === 'superadmin' ? 'badge-muted' : 'badge-success' ?>" style="font-size:10px;">
                            <?= $actor === 'superadmin' ? 'Super Admin' : ($actor === 'dg' ? 'DG' : 'Admin') ?>
                        </span>
                    </span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
function updatePreview(val) {
    const rate = parseFloat(val);
    const el   = document.getElementById('preview');
    if (!el || isNaN(rate) || rate <= 0) { el && (el.textContent = ''); return; }
    el.innerHTML =
        '<i class="fas fa-info-circle"></i> ' +
        'Aperçu : 10 000 HTG → <strong>$' + Math.round(10000 / rate).toLocaleString('fr-HT') + ' USD</strong>';
}
// Initialiser au chargement
const input = document.querySelector('input[name="usd_to_htg"]');
if (input) updatePreview(input.value);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
