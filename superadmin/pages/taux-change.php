<?php
/**
 * AtlanTech — Super Admin : Taux de Change USD / HTG
 */
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
check_superadmin_auth();

$superadmin_id   = $_SESSION['superadmin_id'];
$superadmin_name = $_SESSION['superadmin_name'] ?? 'Super Admin';
$success = '';
$error   = '';

// ── Initialiser tables si nécessaire ────────────────────────────────
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
        `actor_type`  ENUM('superadmin','admin') NOT NULL DEFAULT 'superadmin',
        `actor_id`    INT          DEFAULT NULL,
        `actor_name`  VARCHAR(120) DEFAULT NULL,
        `changed_at`  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_key_date` (`setting_key`, `changed_at`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("INSERT IGNORE INTO `atl_settings` (`setting_key`,`setting_value`,`description`)
                VALUES ('usd_to_htg','130','Taux de change du jour : 1 USD = X HTG')");
} catch (PDOException $e) {
    error_log('superadmin/taux-change init: ' . $e->getMessage());
}

// ── Traitement formulaire ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Rechargez la page.';
    } else {
        $new_rate = filter_input(INPUT_POST, 'usd_to_htg', FILTER_VALIDATE_FLOAT);
        if ($new_rate === false || $new_rate < 1 || $new_rate > 99999) {
            $error = 'Taux invalide. Entrez un nombre entre 1 et 99 999.';
        } else {
            try {
                $old = (float)$pdo->query("SELECT setting_value FROM atl_settings WHERE setting_key='usd_to_htg'")->fetchColumn();
                if ($old !== $new_rate) {
                    $pdo->prepare("UPDATE atl_settings SET setting_value=?, updated_by=?, updated_at=NOW() WHERE setting_key='usd_to_htg'")
                        ->execute([(string)$new_rate, $superadmin_id]);
                    $pdo->prepare("INSERT INTO atl_settings_history (setting_key,old_value,new_value,actor_type,actor_id,actor_name) VALUES ('usd_to_htg',?,?,'superadmin',?,?)")
                        ->execute([(string)$old, (string)$new_rate, $superadmin_id, $superadmin_name]);
                    if (function_exists('apcu_delete')) apcu_delete('atl_usd_to_htg');
                    log_superadmin_action($superadmin_id, 'UPDATE_RATE', "Taux USD/HTG : $old → $new_rate", 'settings');
                    $success = "Taux mis à jour : 1 USD = " . number_format($new_rate, 2) . " HTG";
                } else {
                    $success = "Le taux est déjà à " . number_format($new_rate, 2) . " HTG — aucune modification.";
                }
            } catch (PDOException $e) {
                error_log('taux-change update: ' . $e->getMessage());
                $error = 'Erreur base de données.';
            }
        }
    }
}

// ── Données ──────────────────────────────────────────────────────────
try {
    $current_rate = (float)$pdo->query("SELECT setting_value FROM atl_settings WHERE setting_key='usd_to_htg'")->fetchColumn();
} catch (PDOException $e) { $current_rate = 130.0; }

try {
    $history = $pdo->query("SELECT * FROM atl_settings_history WHERE setting_key='usd_to_htg' ORDER BY changed_at DESC LIMIT 30")->fetchAll();
} catch (PDOException $e) { $history = []; }

$csrf_token = generate_csrf_token();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Taux de Change — Super Admin | AtlanTech</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#0f0f1a;color:#e2e8f0;min-height:100vh;display:flex}
/* ── Sidebar (copie du style dashboard) ── */
.sidebar{width:240px;min-height:100vh;background:linear-gradient(180deg,#1a0533 0%,#0d0221 100%);border-right:1px solid rgba(168,85,247,.2);display:flex;flex-direction:column;position:fixed;top:0;left:0;bottom:0;z-index:100}
.sidebar-logo{padding:24px 20px;font-size:16px;font-weight:800;color:#a855f7;letter-spacing:2px;border-bottom:1px solid rgba(168,85,247,.2);display:flex;align-items:center;gap:10px}
.sidebar-menu{list-style:none;padding:16px 12px;flex:1}
.sidebar-menu li{margin-bottom:4px}
.sidebar-menu a{display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:8px;color:#94a3b8;text-decoration:none;font-size:14px;font-weight:500;transition:.2s}
.sidebar-menu a:hover,.sidebar-menu a.active{background:rgba(168,85,247,.15);color:#a855f7}
.sidebar-menu a i{width:18px;text-align:center}
.sidebar-footer{padding:16px;border-top:1px solid rgba(168,85,247,.2)}
.sidebar-user{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.sidebar-user-avatar{width:36px;height:36px;background:#a855f7;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:700;color:#fff}
.sidebar-user-name{font-size:13px;font-weight:600;color:#e2e8f0}
.sidebar-user-role{font-size:11px;color:#64748b}
.btn-logout{display:flex;align-items:center;gap:8px;padding:10px 14px;background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);border-radius:8px;color:#f87171;text-decoration:none;font-size:13px;font-weight:600;transition:.2s}
.btn-logout:hover{background:rgba(239,68,68,.2)}
/* ── Main ── */
.main-content{margin-left:240px;flex:1;padding:32px;min-height:100vh}
.page-header{margin-bottom:28px}
.page-header h1{font-size:22px;font-weight:800;color:#e2e8f0;display:flex;align-items:center;gap:10px}
.page-header h1 i{color:#a855f7}
.page-header p{color:#64748b;font-size:13px;margin-top:5px}
/* Cards */
.grid-2{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px}
.card{background:#1e1e2e;border:1px solid rgba(168,85,247,.15);border-radius:12px;padding:24px}
/* Alert */
.alert{padding:14px 18px;border-radius:8px;margin-bottom:20px;display:flex;align-items:center;gap:10px;font-size:14px}
.alert-success{background:rgba(16,185,129,.1);border:1px solid rgba(16,185,129,.3);color:#6ee7b7}
.alert-error{background:rgba(239,68,68,.1);border:1px solid rgba(239,68,68,.3);color:#fca5a5}
/* Rate display */
.rate-big{font-size:48px;font-weight:900;color:#a855f7;line-height:1}
.rate-label{font-size:13px;color:#64748b;font-weight:600;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px}
.rate-example{font-size:13px;color:#64748b;margin-top:12px}
/* Form */
.form-label{font-size:13px;font-weight:600;color:#94a3b8;display:block;margin-bottom:8px;text-transform:uppercase;letter-spacing:.5px}
.input-group{display:flex;align-items:stretch;border-radius:8px;overflow:hidden;border:1px solid rgba(168,85,247,.3)}
.input-group-text{background:#2d1b4e;color:#94a3b8;padding:12px 16px;font-size:13px;white-space:nowrap;border:none}
.input-group input{flex:1;background:#1a0f2e;border:none;color:#e2e8f0;padding:12px 16px;font-size:18px;font-weight:700;outline:none}
.input-group input:focus{background:#2d1b4e}
.btn-save{width:100%;padding:13px;background:linear-gradient(135deg,#a855f7,#7c3aed);border:none;border-radius:8px;color:#fff;font-size:14px;font-weight:700;cursor:pointer;margin-top:14px;letter-spacing:.5px;transition:.2s}
.btn-save:hover{opacity:.88}
/* Refs */
.refs{background:rgba(168,85,247,.06);border:1px solid rgba(168,85,247,.15);border-radius:10px;padding:16px 20px;margin-bottom:24px}
.refs-title{font-size:12px;font-weight:700;color:#a855f7;margin-bottom:10px;text-transform:uppercase;letter-spacing:1px}
.refs a{display:inline-flex;align-items:center;gap:6px;padding:5px 14px;background:rgba(168,85,247,.1);border:1px solid rgba(168,85,247,.25);border-radius:20px;font-size:12px;color:#c4b5fd;text-decoration:none;margin:3px;transition:.2s}
.refs a:hover{background:rgba(168,85,247,.2)}
/* History table */
.history-card{background:#1e1e2e;border:1px solid rgba(168,85,247,.15);border-radius:12px;padding:24px}
.section-title{font-size:15px;font-weight:700;color:#e2e8f0;margin-bottom:18px;display:flex;align-items:center;gap:8px}
.section-title i{color:#a855f7}
table{width:100%;border-collapse:collapse;font-size:13px}
th{padding:10px 14px;text-align:left;color:#64748b;font-weight:600;border-bottom:1px solid rgba(168,85,247,.1)}
td{padding:12px 14px;border-bottom:1px solid rgba(255,255,255,.04);color:#94a3b8}
tr:hover td{background:rgba(168,85,247,.04)}
.badge{display:inline-block;padding:3px 10px;border-radius:12px;font-size:11px;font-weight:700}
.badge-up{background:rgba(239,68,68,.15);color:#f87171}
.badge-down{background:rgba(16,185,129,.15);color:#6ee7b7}
.badge-role{background:rgba(168,85,247,.15);color:#c4b5fd;font-size:10px;padding:2px 8px;border-radius:10px;margin-left:6px}
.badge-role-dg{background:rgba(234,179,8,.15);color:#fde047;font-size:10px;padding:2px 8px;border-radius:10px;margin-left:6px}
.empty{text-align:center;padding:40px;color:#4b5563}
@media(max-width:768px){.grid-2{grid-template-columns:1fr}.sidebar{transform:translateX(-100%)}.main-content{margin-left:0}}
</style>
    <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>

<!-- Mobile top bar (hamburger) -->
<div class="sa-mobile-header">
    <span class="sa-mobile-logo"><i class="fas fa-shield-alt" style="margin-right:6px;color:#ffd700;-webkit-text-fill-color:#ffd700"></i>ATLANTECH SA</span>
    <button class="sa-hamburger" id="sa-hamburger-btn" aria-label="Ouvrir le menu">
        <i class="fas fa-bars"></i>
    </button>
</div>
<!-- Sidebar overlay -->
<div class="sa-sidebar-overlay" id="sa-sidebar-overlay"></div>

<div class="sidebar">
    <!-- Close button (mobile) -->
    <button class="sa-sidebar-close" id="sa-sidebar-close-btn" aria-label="Fermer">
        <i class="fas fa-times"></i>
    </button>

    <div class="sidebar-logo"><i class="fas fa-crown"></i>SUPER ADMIN</div>
    <ul class="sidebar-menu">
        <li><a href="dashboard.php"><i class="fas fa-home"></i><span>Dashboard</span></a></li>
        <li><a href="admins-list.php"><i class="fas fa-user-shield"></i><span>Administrateurs</span></a></li>
        <li><a href="admin-create.php"><i class="fas fa-user-plus"></i><span>Créer Admin</span></a></li>
        <li><a href="manage_users.php"><i class="fas fa-users"></i><span>Clients</span></a></li>
        <li><a href="manage_products.php"><i class="fas fa-box"></i><span>Produits</span></a></li>
        <li><a href="manage_orders.php"><i class="fas fa-shopping-cart"></i><span>Commandes</span></a></li>
        <li style="margin-top:15px;border-top:1px solid rgba(168,85,247,.2);padding-top:15px;">
            <a href="taux-change.php" class="active"><i class="fas fa-dollar-sign"></i><span>Taux de Change</span></a>
        </li>
        <li><a href="system-logs.php"><i class="fas fa-history"></i><span>Journaux</span></a></li>
        <li><a href="settings.php"><i class="fas fa-cog"></i><span>Paramètres</span></a></li>
    </ul>
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-user-avatar"><?= strtoupper(substr($superadmin_name,0,1)) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= htmlspecialchars($superadmin_name) ?></div>
                <div class="sidebar-user-role">Super Admin</div>
            </div>
        </div>
        <a href="../logout.php" class="btn-logout"><i class="fas fa-sign-out-alt"></i>Déconnexion</a>
    </div>
</div>

<main class="main-content">
    <div class="page-header">
        <h1><i class="fas fa-dollar-sign"></i>Taux de Change USD / HTG</h1>
        <p>Définissez le taux du jour — il s'applique immédiatement sur toute la boutique.</p>
    </div>

    <?php if ($error):   ?><div class="alert alert-error"><i class="fas fa-exclamation-circle"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>

    <div class="grid-2">
        <!-- Taux actuel -->
        <div class="card">
            <div class="rate-label">Taux actuel</div>
            <div style="display:flex;align-items:baseline;gap:10px;">
                <span class="rate-big"><?= number_format($current_rate, 2) ?></span>
                <span style="font-size:16px;color:#64748b;font-weight:700;">HTG / $1</span>
            </div>
            <p class="rate-example">
                Exemple : <strong style="color:#e2e8f0;">10,000 HTG</strong> →
                <strong style="color:#a855f7;">$<?= number_format(10000 / $current_rate, 0) ?> USD</strong>
            </p>
        </div>

        <!-- Formulaire -->
        <div class="card">
            <div class="rate-label">Mettre à jour le taux du jour</div>
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
                <label class="form-label">1 USD = ? HTG</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-dollar-sign"></i> 1 USD =</span>
                    <input type="number" name="usd_to_htg"
                           value="<?= number_format($current_rate, 2, '.', '') ?>"
                           min="1" max="99999" step="0.01" required
                           placeholder="130.00">
                    <span class="input-group-text">HTG</span>
                </div>
                <button type="submit" class="btn-save">
                    <i class="fas fa-save" style="margin-right:8px;"></i>Enregistrer le taux
                </button>
            </form>
        </div>
    </div>

    <!-- Références -->
    <div class="refs">
        <div class="refs-title"><i class="fas fa-info-circle" style="margin-right:6px;"></i>Sources officielles du taux</div>
        <a href="https://www.brh.ht" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i>BRH – Banque de la République d'Haïti</a>
        <a href="https://www.xe.com/currencyconverter/convert/?Amount=1&From=USD&To=HTG" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i>XE.com USD/HTG</a>
        <a href="https://moncashbutton.digicelgroup.com" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i>Digicel MonCash</a>
    </div>

    <!-- Historique -->
    <div class="history-card">
        <div class="section-title"><i class="fas fa-history"></i>Historique des modifications</div>
        <?php if (empty($history)): ?>
            <div class="empty"><i class="fas fa-clock" style="font-size:32px;color:#374151;margin-bottom:10px;display:block;"></i>Aucune modification enregistrée.</div>
        <?php else: ?>
        <div style="overflow-x:auto;">
            <table>
                <thead>
                    <tr>
                        <th>Date & Heure</th>
                        <th>Ancien taux</th>
                        <th>Nouveau taux</th>
                        <th>Variation</th>
                        <th>Modifié par</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($history as $h):
                    $old_v = (float)$h['old_value'];
                    $new_v = (float)$h['new_value'];
                    $diff  = $new_v - $old_v;
                    $pct   = $old_v > 0 ? ($diff / $old_v) * 100 : 0;
                ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($h['changed_at'])) ?></td>
                    <td style="color:#64748b;"><?= number_format($old_v,2) ?> HTG</td>
                    <td style="color:#e2e8f0;font-weight:700;"><?= number_format($new_v,2) ?> HTG</td>
                    <td>
                        <?php if ($diff > 0): ?>
                            <span class="badge badge-up">▲ +<?= number_format($pct,1) ?>%</span>
                        <?php elseif ($diff < 0): ?>
                            <span class="badge badge-down">▼ <?= number_format($pct,1) ?>%</span>
                        <?php else: ?>
                            <span style="color:#4b5563;">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?= htmlspecialchars($h['actor_name'] ?? 'Système') ?>
                        <?php if (($h['actor_type'] ?? '') === 'superadmin'): ?>
                            <span class="badge-role">Super Admin</span>
                        <?php else: ?>
                            <span class="badge-role-dg">DG Admin</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</main>
<script>
(function(){
    var overlay   = document.getElementById('sa-sidebar-overlay');
    var sidebar   = document.querySelector('.sidebar');
    var hamburger = document.getElementById('sa-hamburger-btn');
    var closeBtn  = document.getElementById('sa-sidebar-close-btn');
    function openSidebar()  { if(sidebar){sidebar.classList.add('sa-open');}    if(overlay){overlay.classList.add('active');} }
    function closeSidebar() { if(sidebar){sidebar.classList.remove('sa-open');} if(overlay){overlay.classList.remove('active');} }
    if(hamburger) hamburger.addEventListener('click', openSidebar);
    if(closeBtn)  closeBtn.addEventListener('click', closeSidebar);
    if(overlay)   overlay.addEventListener('click', closeSidebar);
})();
</script>
</body>
</html>
