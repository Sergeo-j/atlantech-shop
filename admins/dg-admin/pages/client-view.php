<?php
/**
 * Fiche client (vue DG)
 *
 * Affiche les informations du client SAUF le mot de passe : c'est
 * techniquement impossible d'afficher un mot de passe existant (hachage
 * Argon2id à sens unique, aucune fonction de déchiffrement n'existe).
 * Pour un cas extrême (client important perd son accès), deux actions sont
 * proposées : envoyer un lien de réinitialisation, ou générer un mot de
 * passe temporaire affiché une seule fois puis forcé à être changé.
 */
require_once __DIR__ . '/../includes/auth.php';
require_dg_auth();

$user_id = (int)($_GET['id'] ?? 0);
if (!$user_id) { header('Location: clients-list.php'); exit; }

$page_title = 'Fiche client';

try {
    $st = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $st->execute([$user_id]);
    $client = $st->fetch();
} catch (PDOException $e) {
    error_log('DG client-view lookup: ' . $e->getMessage());
    $client = null;
}

if (!$client) { header('Location: clients-list.php?error=not_found'); exit; }

$orders = [];
try {
    $st = $pdo->prepare("SELECT id, order_number, total_amount, status, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
    $st->execute([$user_id]);
    $orders = $st->fetchAll();
} catch (PDOException $e) { /* table peut ne pas avoir order_number selon le schéma — ignoré silencieusement */ }

// Flash affiché une seule fois (mot de passe temporaire inclus le cas échéant),
// puis IMMÉDIATEMENT effacé de la session pour qu'un rafraîchissement de page
// ne le montre plus jamais.
$flash = $_SESSION['dg_reset_flash'] ?? null;
unset($_SESSION['dg_reset_flash']);

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <a href="clients-list.php" class="btn btn-ghost btn-sm"><i class="fas fa-arrow-left"></i> Retour</a>
    <h2><i class="fas fa-user"></i> Fiche client #<?= (int)$client['id'] ?></h2>
</div>

<?php if ($flash): ?>
<div class="alert alert-<?= htmlspecialchars($flash['type']) ?>">
    <i class="fas fa-<?= $flash['type'] === 'success' ? 'check-circle' : ($flash['type'] === 'warning' ? 'exclamation-triangle' : 'exclamation-circle') ?>"></i>
    <?= $flash['message'] ?>
</div>
<?php if (!empty($flash['temp_password'])): ?>
<div class="card" style="border: 2px solid var(--warning, #f59e0b); margin-bottom:20px">
    <div class="card-header">
        <h3><i class="fas fa-key"></i> Mot de passe temporaire — visible une seule fois</h3>
    </div>
    <p style="font-size: 1.6rem; font-family: monospace; letter-spacing: 2px; background: rgba(245,158,11,.08); border: 1px dashed var(--warning, #f59e0b); border-radius: 8px; padding: 16px; text-align: center; margin: 10px 0;">
        <?= htmlspecialchars($flash['temp_password']) ?>
    </p>
    <p class="text-muted" style="font-size:0.85rem">
        <i class="fas fa-info-circle"></i>
        Communiquez ce mot de passe au client de vive voix ou par un canal sécurisé (pas par email non chiffré).
        Il ne sera plus jamais affiché — actualiser ou quitter cette page l'efface définitivement de l'écran.
        Le client devra le changer dès sa prochaine connexion.
    </p>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-id-card"></i> Informations</h3>
    </div>
    <div class="form-row" style="grid-template-columns: 1fr 1fr 1fr">
        <div>
            <div class="text-muted" style="font-size:0.78rem">Nom</div>
            <div><strong><?= htmlspecialchars($client['name']) ?></strong></div>
        </div>
        <div>
            <div class="text-muted" style="font-size:0.78rem">Email</div>
            <div><?= htmlspecialchars($client['email']) ?></div>
        </div>
        <div>
            <div class="text-muted" style="font-size:0.78rem">Téléphone</div>
            <div><?= htmlspecialchars($client['phone'] ?? '—') ?></div>
        </div>
        <div>
            <div class="text-muted" style="font-size:0.78rem">Statut</div>
            <div>
                <?php if (!empty($client['blocked'])): ?>
                    <span class="badge badge-danger">Bloqué</span>
                <?php elseif ((int)$client['is_active'] === 1): ?>
                    <span class="badge badge-success">Actif</span>
                <?php else: ?>
                    <span class="badge badge-muted">Désactivé</span>
                <?php endif; ?>
                <?php if (!empty($client['email_verified'])): ?>
                    <span class="badge badge-info"><i class="fas fa-check"></i> Email vérifié</span>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <div class="text-muted" style="font-size:0.78rem">Inscrit le</div>
            <div><?= htmlspecialchars(date('d/m/Y H:i', strtotime($client['created_at']))) ?></div>
        </div>
        <div>
            <div class="text-muted" style="font-size:0.78rem">Dernière connexion</div>
            <div><?= $client['last_login'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($client['last_login']))) : 'Jamais' ?></div>
        </div>
        <div>
            <div class="text-muted" style="font-size:0.78rem">Commandes totales</div>
            <div><?= (int)($client['total_orders'] ?? 0) ?></div>
        </div>
        <div>
            <div class="text-muted" style="font-size:0.78rem">Total dépensé</div>
            <div><?= htmlspecialchars(number_format((float)($client['total_spent'] ?? 0), 2, ',', ' ')) ?> HTG</div>
        </div>
        <div>
            <div class="text-muted" style="font-size:0.78rem">Points de fidélité</div>
            <div><?= (int)($client['loyalty_points'] ?? 0) ?></div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-shopping-bag"></i> Commandes récentes</h3>
    </div>
    <?php if (empty($orders)): ?>
        <p class="text-muted text-center" style="padding:20px">Aucune commande trouvée.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="data">
        <thead><tr><th>N° commande</th><th>Date</th><th>Statut</th><th>Montant</th></tr></thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td>#<?= htmlspecialchars($o['order_number'] ?? $o['id']) ?></td>
                <td><?= htmlspecialchars(date('d/m/Y H:i', strtotime($o['created_at']))) ?></td>
                <td><span class="badge badge-info"><?= htmlspecialchars(ucfirst($o['status'] ?? '—')) ?></span></td>
                <td><?= htmlspecialchars(number_format((float)$o['total_amount'], 2, ',', ' ')) ?> HTG</td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<div class="card" style="border-left: 3px solid var(--warning, #f59e0b)">
    <div class="card-header">
        <h3><i class="fas fa-life-ring"></i> Débloquer l'accès du client (cas extrême)</h3>
    </div>
    <p class="text-muted" style="font-size:0.85rem; margin-bottom:16px">
        Réservé aux situations urgentes (client important sans accès à son compte).
        Le mot de passe actuel du client n'est jamais visible ni récupérable — ces actions créent un nouvel accès.
    </p>
    <div style="display:flex; gap:15px; flex-wrap:wrap">
        <form method="POST" action="client-reset-password.php" onsubmit="return confirm('Envoyer un lien de réinitialisation à ' + <?= json_encode($client['email']) ?> + ' ?');">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int)$client['id'] ?>">
            <input type="hidden" name="action" value="link">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-paper-plane"></i> Envoyer un lien de réinitialisation
            </button>
        </form>
        <form method="POST" action="client-reset-password.php" onsubmit="return confirm('Générer un mot de passe temporaire pour ' + <?= json_encode($client['name']) ?> + ' ?\n\nÀ utiliser uniquement si le client est injoignable par email/WhatsApp — ce mot de passe devra être communiqué de vive voix.');">
            <?= csrf_field() ?>
            <input type="hidden" name="user_id" value="<?= (int)$client['id'] ?>">
            <input type="hidden" name="action" value="temp">
            <button type="submit" class="btn btn-danger">
                <i class="fas fa-key"></i> Générer un mot de passe temporaire
            </button>
        </form>
    </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
