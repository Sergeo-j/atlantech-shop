<?php
/**
 * Page : Liste des clients (vue DG)
 *
 * Le DG peut consulter la fiche de n'importe quel client et, en cas
 * extrême (client important injoignable, perte d'accès), déclencher une
 * réinitialisation d'accès depuis la fiche — jamais consulter un mot de
 * passe existant, ce qui est de toute façon techniquement impossible
 * (hachage Argon2id à sens unique).
 */
require_once __DIR__ . '/../includes/auth.php';
require_dg_auth();

$page_title = 'Clients';

$filter_search = trim($_GET['q'] ?? '');
$filter_status = $_GET['status'] ?? ''; // 'active' | 'blocked' | ''

$where  = ['1=1'];
$params = [];

if ($filter_search !== '') {
    $where[]  = '(name LIKE ? OR email LIKE ? OR phone LIKE ?)';
    $like = '%' . $filter_search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like;
}
if ($filter_status === 'active') {
    $where[] = 'is_active = 1 AND (blocked IS NULL OR blocked = 0)';
} elseif ($filter_status === 'blocked') {
    $where[] = 'blocked = 1';
} elseif ($filter_status === 'inactive') {
    $where[] = 'is_active = 0';
}

$where_sql = implode(' AND ', $where);

$clients = [];
try {
    $sql = "
        SELECT id, name, email, phone, is_active, blocked, email_verified,
               account_tier, total_orders, total_spent, last_login, created_at
        FROM users
        WHERE $where_sql
        ORDER BY created_at DESC
        LIMIT 200
    ";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $clients = $st->fetchAll();
} catch (PDOException $e) {
    error_log('DG clients-list query: ' . $e->getMessage());
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-users"></i> Clients</h2>
</div>

<p class="text-muted" style="font-size:0.85rem; margin-bottom:16px">
    <i class="fas fa-shield-alt"></i>
    Rappel sécurité : aucun mot de passe client n'est jamais consultable ici (hachage Argon2id à sens unique). En cas extrême, la fiche client permet d'envoyer un lien de réinitialisation ou de générer un mot de passe temporaire à usage unique.
</p>

<!-- Filtres -->
<div class="card">
    <form method="GET" class="form-row" style="grid-template-columns: 2fr 1fr auto; align-items: end">
        <div class="form-group" style="margin-bottom:0">
            <label>Recherche</label>
            <input type="text" name="q" class="form-input" value="<?= htmlspecialchars($filter_search) ?>" placeholder="Nom, email, téléphone…">
        </div>
        <div class="form-group" style="margin-bottom:0">
            <label>Statut</label>
            <select name="status" class="form-select">
                <option value="">Tous</option>
                <option value="active"   <?= $filter_status === 'active'   ? 'selected' : '' ?>>Actifs</option>
                <option value="blocked"  <?= $filter_status === 'blocked'  ? 'selected' : '' ?>>Bloqués</option>
                <option value="inactive" <?= $filter_status === 'inactive' ? 'selected' : '' ?>>Désactivés</option>
            </select>
        </div>
        <div class="form-group" style="margin-bottom:0">
            <button class="btn btn-primary" type="submit"><i class="fas fa-filter"></i> Filtrer</button>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-header">
        <h3><i class="fas fa-list"></i> <?= count($clients) ?> client(s)</h3>
    </div>

    <?php if (empty($clients)): ?>
        <p class="text-muted text-center" style="padding:30px">Aucun client ne correspond aux filtres.</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="data">
        <thead>
        <tr>
            <th>Nom</th>
            <th>Email</th>
            <th>Téléphone</th>
            <th>Statut</th>
            <th>Commandes</th>
            <th>Total dépensé</th>
            <th>Dernière connexion</th>
            <th class="actions">Actions</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($clients as $c): ?>
        <tr>
            <td><strong><?= htmlspecialchars($c['name']) ?></strong></td>
            <td><?= htmlspecialchars($c['email']) ?></td>
            <td class="text-muted"><?= htmlspecialchars($c['phone'] ?? '—') ?></td>
            <td>
                <?php if (!empty($c['blocked'])): ?>
                    <span class="badge badge-danger">Bloqué</span>
                <?php elseif ((int)$c['is_active'] === 1): ?>
                    <span class="badge badge-success">Actif</span>
                <?php else: ?>
                    <span class="badge badge-muted">Désactivé</span>
                <?php endif; ?>
            </td>
            <td><?= (int)($c['total_orders'] ?? 0) ?></td>
            <td><?= htmlspecialchars(number_format((float)($c['total_spent'] ?? 0), 2, ',', ' ')) ?> HTG</td>
            <td class="text-muted" style="font-size:0.85rem">
                <?= $c['last_login'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($c['last_login']))) : 'Jamais' ?>
            </td>
            <td class="actions">
                <a href="client-view.php?id=<?= (int)$c['id'] ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-eye"></i> Fiche
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
