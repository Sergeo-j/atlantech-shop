<?php
/**
 * AtlanTech Backoffice — Taux de Change USD / HTG
 * Réservé au Directeur Général (DG).
 */
$page_title = "Taux de Change";
$page_icon  = "fa-dollar-sign";

require_once 'config.php';
requireLogin();
require_once __DIR__ . '/includes/csrf.php';

$admin_id   = $_SESSION['admin_id']   ?? null;
$admin_name = $_SESSION['admin_name'] ?? ($_SESSION['admin_username'] ?? 'DG Admin');
$error      = '';
$success    = '';

// ── Initialiser les tables si nécessaire ────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `atl_settings` (
        `setting_key`   VARCHAR(64)  NOT NULL,
        `setting_value` TEXT         NOT NULL,
        `description`   VARCHAR(255) DEFAULT NULL,
        `updated_at`    TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        `updated_by`    INT          DEFAULT NULL,
        PRIMARY KEY (`setting_key`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `atl_atl_settings_history` (
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
} catch (PDOException $e) {
    error_log('backoffice/taux-change init: ' . $e->getMessage());
}

// ── Traitement POST ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    $new_rate = filter_input(INPUT_POST, 'usd_to_htg', FILTER_VALIDATE_FLOAT);

    if ($new_rate === false || $new_rate < 1 || $new_rate > 99999) {
        $error = 'Taux invalide. Entrez un nombre entre 1 et 99 999.';
    } else {
        try {
            $old = (float)$pdo->query("SELECT setting_value FROM atl_settings WHERE setting_key='usd_to_htg'")->fetchColumn();

            if ($old !== $new_rate) {
                $pdo->prepare("UPDATE atl_settings SET setting_value=?, updated_by=?, updated_at=NOW() WHERE setting_key='usd_to_htg'")
                    ->execute([(string)$new_rate, $admin_id]);

                $pdo->prepare("INSERT INTO atl_settings_history (setting_key,old_value,new_value,actor_type,actor_id,actor_name)
                               VALUES ('usd_to_htg',?,?,'admin',?,?)")
                    ->execute([(string)$old, (string)$new_rate, $admin_id, $admin_name]);

                if (function_exists('apcu_delete')) apcu_delete('atl_usd_to_htg');

                $success = "Taux mis à jour : 1 USD = " . number_format($new_rate, 2) . " HTG";
            } else {
                $success = "Le taux est déjà à " . number_format($new_rate, 2) . " HTG — aucune modification.";
            }
        } catch (PDOException $e) {
            error_log('backoffice/taux-change update: ' . $e->getMessage());
            $error = 'Erreur base de données. Réessayez.';
        }
    }
}

// ── Lecture taux actuel + historique ────────────────────────────────
try {
    $current_rate = (float)$pdo->query("SELECT setting_value FROM atl_settings WHERE setting_key='usd_to_htg'")->fetchColumn();
} catch (PDOException $e) { $current_rate = 130.0; }

try {
    $history = $pdo->query("SELECT * FROM atl_settings_history WHERE setting_key='usd_to_htg' ORDER BY changed_at DESC LIMIT 30")->fetchAll();
} catch (PDOException $e) { $history = []; }
?>
<?php include 'includes/admin-header.php'; ?>
<?php include 'includes/admin-sidebar.php'; ?>

<main class="admin-main">
  <div class="admin-content" style="padding:30px;">

    <!-- En-tête -->
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:28px;flex-wrap:wrap;gap:12px;">
      <div>
        <h1 style="font-size:22px;font-weight:700;color:#111;margin:0;">
          <i class="fas fa-dollar-sign" style="color:#e87c1e;margin-right:10px;"></i>
          Taux de Change USD / HTG
        </h1>
        <p style="color:#6b7280;font-size:13px;margin:4px 0 0;">
          Définissez le taux du jour — il s'applique immédiatement sur toute la boutique.
        </p>
      </div>
      <span style="background:#fff3cd;color:#856404;padding:6px 14px;border-radius:20px;font-size:12px;font-weight:600;border:1px solid #ffc107;">
        <i class="fas fa-shield-alt" style="margin-right:5px;"></i>Réservé DG
      </span>
    </div>

    <?php if ($error):   ?><div style="background:#fee2e2;border:1px solid #fca5a5;color:#991b1b;padding:14px 18px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px;"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div style="background:#d1fae5;border:1px solid #6ee7b7;color:#065f46;padding:14px 18px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px;"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <!-- Grille taux + formulaire -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-bottom:28px;">

      <!-- Taux actuel -->
      <div style="background:#fff;border-radius:12px;padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.08);border:1px solid #e5e7eb;">
        <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:#9ca3af;letter-spacing:1px;margin:0 0 10px;">Taux actuel</p>
        <div style="display:flex;align-items:baseline;gap:8px;">
          <span style="font-size:46px;font-weight:900;color:#111;line-height:1;">
            <?= number_format($current_rate, 2) ?>
          </span>
          <span style="font-size:16px;color:#6b7280;font-weight:600;">HTG / $1</span>
        </div>
        <p style="color:#6b7280;font-size:13px;margin:12px 0 0;">
          Exemple : <strong style="color:#111;">10 000 HTG</strong> →
          <strong style="color:#e87c1e;">$<?= number_format(10000 / $current_rate, 0) ?> USD</strong>
          sur la boutique.
        </p>
      </div>

      <!-- Formulaire -->
      <div style="background:#fff;border-radius:12px;padding:28px;box-shadow:0 1px 4px rgba(0,0,0,.08);border:1px solid #e5e7eb;">
        <p style="font-size:11px;font-weight:700;text-transform:uppercase;color:#9ca3af;letter-spacing:1px;margin:0 0 14px;">Mettre à jour le taux du jour</p>
        <form method="POST" action="">
          <?= csrf_field() ?>
          <label style="font-size:13px;font-weight:600;color:#374151;display:block;margin-bottom:8px;">1 USD = ? HTG</label>
          <div style="display:flex;align-items:stretch;border-radius:8px;overflow:hidden;border:1px solid #d1d5db;">
            <span style="background:#f3f4f6;color:#6b7280;padding:12px 14px;font-size:13px;white-space:nowrap;border-right:1px solid #d1d5db;">$1 USD =</span>
            <input type="number"
                   name="usd_to_htg"
                   value="<?= number_format($current_rate, 2, '.', '') ?>"
                   min="1" max="99999" step="0.01"
                   required
                   style="flex:1;border:none;padding:12px 14px;font-size:18px;font-weight:700;color:#111;outline:none;border-right:1px solid #d1d5db;">
            <span style="background:#f3f4f6;color:#6b7280;padding:12px 14px;font-size:13px;">HTG</span>
          </div>
          <button type="submit"
                  style="width:100%;margin-top:14px;padding:13px;background:linear-gradient(135deg,#e87c1e,#f59e0b);color:#fff;border:none;border-radius:8px;font-size:14px;font-weight:700;cursor:pointer;transition:opacity .2s;"
                  onmouseover="this.style.opacity='.88'" onmouseout="this.style.opacity='1'">
            <i class="fas fa-save" style="margin-right:8px;"></i>Enregistrer le taux du jour
          </button>
        </form>
      </div>
    </div>

    <!-- Sources officielles -->
    <div style="background:#eff6ff;border:1px solid #bfdbfe;border-radius:10px;padding:16px 20px;margin-bottom:28px;">
      <p style="font-size:12px;font-weight:700;color:#1e40af;margin:0 0 10px;text-transform:uppercase;letter-spacing:.5px;">
        <i class="fas fa-info-circle" style="margin-right:6px;"></i>Sources officielles du taux
      </p>
      <div style="display:flex;flex-wrap:wrap;gap:8px;">
        <a href="https://www.brh.ht" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;padding:5px 14px;background:#fff;border:1px solid #93c5fd;border-radius:20px;font-size:12px;color:#1d4ed8;text-decoration:none;font-weight:500;">
          <i class="fas fa-external-link-alt" style="font-size:10px;"></i>BRH – Banque de la République d'Haïti</a>
        <a href="https://www.xe.com/currencyconverter/convert/?Amount=1&From=USD&To=HTG" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;padding:5px 14px;background:#fff;border:1px solid #93c5fd;border-radius:20px;font-size:12px;color:#1d4ed8;text-decoration:none;font-weight:500;">
          <i class="fas fa-external-link-alt" style="font-size:10px;"></i>XE.com USD/HTG</a>
        <a href="https://moncashbutton.digicelgroup.com" target="_blank" rel="noopener" style="display:inline-flex;align-items:center;gap:6px;padding:5px 14px;background:#fff;border:1px solid #93c5fd;border-radius:20px;font-size:12px;color:#1d4ed8;text-decoration:none;font-weight:500;">
          <i class="fas fa-external-link-alt" style="font-size:10px;"></i>Digicel MonCash</a>
      </div>
    </div>

    <!-- Historique -->
    <div style="background:#fff;border-radius:12px;padding:24px;box-shadow:0 1px 4px rgba(0,0,0,.08);border:1px solid #e5e7eb;">
      <h2 style="font-size:15px;font-weight:700;color:#111;margin:0 0 18px;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-history" style="color:#6b7280;"></i>Historique des modifications
      </h2>

      <?php if (empty($history)): ?>
        <p style="color:#9ca3af;font-size:14px;text-align:center;padding:30px 0;">Aucune modification enregistrée pour l'instant.</p>
      <?php else: ?>
      <div style="overflow-x:auto;">
        <table style="width:100%;border-collapse:collapse;font-size:13px;">
          <thead>
            <tr style="border-bottom:2px solid #e5e7eb;">
              <th style="padding:10px 14px;text-align:left;color:#6b7280;font-weight:600;">Date & Heure</th>
              <th style="padding:10px 14px;text-align:center;color:#6b7280;font-weight:600;">Ancien taux</th>
              <th style="padding:10px 14px;text-align:center;color:#6b7280;font-weight:600;">Nouveau taux</th>
              <th style="padding:10px 14px;text-align:center;color:#6b7280;font-weight:600;">Variation</th>
              <th style="padding:10px 14px;text-align:left;color:#6b7280;font-weight:600;">Modifié par</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($history as $h):
              $old_v = (float)$h['old_value'];
              $new_v = (float)$h['new_value'];
              $diff  = $new_v - $old_v;
              $pct   = $old_v > 0 ? ($diff / $old_v) * 100 : 0;
            ?>
            <tr style="border-bottom:1px solid #f3f4f6;" onmouseover="this.style.background='#f9fafb'" onmouseout="this.style.background=''">
              <td style="padding:12px 14px;color:#374151;white-space:nowrap;"><?= date('d/m/Y H:i', strtotime($h['changed_at'])) ?></td>
              <td style="padding:12px 14px;text-align:center;color:#6b7280;"><?= number_format($old_v, 2) ?> HTG</td>
              <td style="padding:12px 14px;text-align:center;font-weight:700;color:#111;"><?= number_format($new_v, 2) ?> HTG</td>
              <td style="padding:12px 14px;text-align:center;">
                <?php if ($diff > 0): ?>
                  <span style="background:#fee2e2;color:#dc2626;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">▲ +<?= number_format($pct,1) ?>%</span>
                <?php elseif ($diff < 0): ?>
                  <span style="background:#d1fae5;color:#059669;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700;">▼ <?= number_format($pct,1) ?>%</span>
                <?php else: ?>
                  <span style="color:#9ca3af;">—</span>
                <?php endif; ?>
              </td>
              <td style="padding:12px 14px;">
                <span style="display:inline-flex;align-items:center;gap:7px;">
                  <span style="width:26px;height:26px;background:<?= ($h['actor_type'] ?? '') === 'superadmin' ? '#a855f7' : '#e87c1e' ?>;color:#fff;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;font-size:10px;font-weight:700;">
                    <?= strtoupper(substr($h['actor_name'] ?? 'S', 0, 1)) ?>
                  </span>
                  <span style="color:#374151;"><?= htmlspecialchars($h['actor_name'] ?? 'Système') ?></span>
                  <span style="font-size:10px;padding:2px 8px;border-radius:10px;font-weight:600;<?= ($h['actor_type'] ?? '') === 'superadmin' ? 'background:#f3e8ff;color:#7e22ce;' : 'background:#fff7ed;color:#c2410c;' ?>">
                    <?= ($h['actor_type'] ?? '') === 'superadmin' ? 'Super Admin' : 'DG Admin' ?>
                  </span>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

  </div>
</main>

<?php include 'includes/admin-footer.php'; ?>
