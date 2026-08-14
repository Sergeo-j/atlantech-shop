<?php
/**
 * Page : Création d'un admin (vue DG)
 *
 * Le DG peut créer des comptes admin (client, stock, livraison, etc.) mais
 * PAS un autre Directeur Général ni un super_admin (cette restriction est
 * appliquée côté serveur ET côté formulaire).
 */
require_once __DIR__ . '/../includes/auth.php';
require_dg_auth();

$page_title = 'Créer un admin';

$errors  = [];
$success = '';
$old     = [
    'full_name'     => '',
    'name'          => '',
    'email'         => '',
    'phone'         => '',
    'admin_role_id' => 0,
];

// ─── Liste des rôles disponibles (HORS DG) ───────────────────────────────
$roles = [];
try {
    $st = $pdo->prepare("SELECT id, role_name, role_description FROM admin_roles WHERE is_active = 1 AND id != ? ORDER BY role_name");
    $st->execute([DG_ROLE_ID]);
    $roles = $st->fetchAll();
} catch (PDOException $e) { /* OK */ }

$ROLE_LABELS = [
    'order'       => '🛒 Gestion des commandes (validation paiement)',
    'preparation' => '📦 Préparation des commandes (emballage)',
    'delivery'    => '🚚 Livreur (expédition + livraison)',
    'product'     => '🏷️ Gestion des produits',
    'stock'       => '📊 Gestion des stocks (inventaire)',
    'client'      => '👥 Gestion des clients',
    'marketing'   => '📢 Marketing & promotions',
    'support'     => '🎧 Support client',
];

// ─── Traitement POST ─────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $old['full_name']     = trim($_POST['full_name']     ?? '');
    $old['name']          = trim($_POST['name']          ?? '');
    $old['email']         = trim($_POST['email']         ?? '');
    $old['phone']         = trim($_POST['phone']         ?? '');
    $old['admin_role_id'] = (int)($_POST['admin_role_id'] ?? 0);
    $password             = $_POST['password']             ?? '';
    $password_confirm     = $_POST['password_confirm']     ?? '';

    // Validations
    if ($old['full_name'] === '')                                $errors[] = 'Le nom complet est requis.';
    if ($old['name']      === '')                                $errors[] = 'Le pseudo est requis.';
    if ($old['email']     === '')                                $errors[] = 'L\'email est requis.';
    elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL))   $errors[] = 'L\'email n\'est pas valide.';
    if ($old['admin_role_id'] <= 0)                              $errors[] = 'Le rôle est requis.';

    // 🔒 Restriction critique : pas de création de DG via cette page
    if ($old['admin_role_id'] === DG_ROLE_ID) {
        $errors[] = 'Vous ne pouvez pas créer un Directeur Général. Demandez au PDG.';
        log_dg_action(
            (int)$_SESSION['dg_id'],
            'dg_create_attempt_blocked',
            "Tentative de création DG bloquée (email proposé : {$old['email']})"
        );
    }

    // Vérifier que le rôle existe et n'est pas le DG_ROLE_ID
    if (empty($errors)) {
        $st = $pdo->prepare("SELECT id FROM admin_roles WHERE id = ? AND is_active = 1 AND id != ?");
        $st->execute([$old['admin_role_id'], DG_ROLE_ID]);
        if (!$st->fetch()) {
            $errors[] = 'Rôle invalide ou interdit.';
        }
    }

    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
    }
    if ($password !== $password_confirm) {
        $errors[] = 'Les mots de passe ne correspondent pas.';
    }

    // Email déjà utilisé ?
    if (empty($errors)) {
        $st = $pdo->prepare("SELECT id FROM admins WHERE email = ?");
        $st->execute([$old['email']]);
        if ($st->fetch()) {
            $errors[] = 'Cet email est déjà utilisé par un autre admin.';
        }
    }

    // Insertion
    if (empty($errors)) {
        try {
            // Hash Argon2id (ou bcrypt en fallback)
            if (defined('PASSWORD_ARGON2ID')) {
                $hash = password_hash($password, PASSWORD_ARGON2ID, [
                    'memory_cost' => 65536, 'time_cost' => 4, 'threads' => 3,
                ]);
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
            }

            $sql = "
                INSERT INTO admins
                    (full_name, name, email, password, phone, admin_role_id, is_active, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, NOW())
            ";
            $st = $pdo->prepare($sql);
            $st->execute([
                $old['full_name'],
                $old['name'],
                $old['email'],
                $hash,
                $old['phone'] !== '' ? $old['phone'] : null,
                $old['admin_role_id'],
            ]);
            $newId = (int)$pdo->lastInsertId();

            log_dg_action(
                (int)$_SESSION['dg_id'],
                'admin_created',
                "Admin #{$newId} ({$old['full_name']} — {$old['email']}) créé par le DG, rôle id {$old['admin_role_id']}"
            );

            $success = "Admin « " . htmlspecialchars($old['full_name']) . " » créé avec succès.";
            // Réinitialiser le formulaire après succès
            $old = ['full_name'=>'', 'name'=>'', 'email'=>'', 'phone'=>'', 'admin_role_id'=>0];

        } catch (PDOException $e) {
            error_log('DG admin-create: ' . $e->getMessage());
            $errors[] = 'Erreur technique lors de la création. Réessayez.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="page-header">
    <h2><i class="fas fa-user-plus"></i> Créer un admin</h2>
    <a href="admins-list.php" class="btn btn-ghost">
        <i class="fas fa-arrow-left"></i> Retour à la liste
    </a>
</div>

<?php if ($success): ?>
<div class="alert alert-success">
    <i class="fas fa-check-circle"></i> <?= $success /* déjà échappé */ ?>
</div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger">
    <i class="fas fa-exclamation-circle"></i>
    <div>
        <strong>Veuillez corriger :</strong>
        <ul style="margin: 6px 0 0 18px">
            <?php foreach ($errors as $err): ?>
                <li><?= htmlspecialchars($err) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
</div>
<?php endif; ?>

<div class="card">
    <form method="POST" autocomplete="off">
        <?= csrf_field() ?>

        <div class="form-row">
            <div class="form-group">
                <label for="full_name">Nom complet *</label>
                <input type="text" id="full_name" name="full_name" class="form-input"
                       value="<?= htmlspecialchars($old['full_name']) ?>"
                       required maxlength="100" autofocus>
            </div>
            <div class="form-group">
                <label for="name">Pseudo / nom court *</label>
                <input type="text" id="name" name="name" class="form-input"
                       value="<?= htmlspecialchars($old['name']) ?>"
                       required maxlength="100">
            </div>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="email">Adresse email *</label>
                <input type="email" id="email" name="email" class="form-input"
                       value="<?= htmlspecialchars($old['email']) ?>"
                       required maxlength="150">
            </div>
            <div class="form-group">
                <label for="phone">Téléphone</label>
                <input type="tel" id="phone" name="phone" class="form-input"
                       value="<?= htmlspecialchars($old['phone']) ?>"
                       placeholder="+509 XXXX XXXX" maxlength="20">
            </div>
        </div>

        <div class="form-group">
            <label for="admin_role_id">Rôle *</label>
            <select id="admin_role_id" name="admin_role_id" class="form-select" required>
                <option value="">-- Sélectionner un rôle --</option>
                <?php foreach ($roles as $r):
                    $lbl = $ROLE_LABELS[$r['role_name']] ?? ucfirst($r['role_name']);
                ?>
                <option value="<?= (int)$r['id'] ?>" <?= $old['admin_role_id'] === (int)$r['id'] ? 'selected' : '' ?>>
                    <?= htmlspecialchars($lbl) ?>
                </option>
                <?php endforeach; ?>
            </select>
            <p class="text-muted" style="font-size:0.8rem; margin-top:6px">
                <i class="fas fa-info-circle"></i>
                Le rôle <strong>Directeur Général</strong> n'apparaît pas — il est réservé à la création par le PDG.
            </p>
        </div>

        <div class="form-row">
            <div class="form-group">
                <label for="password">Mot de passe * <span class="text-muted" style="font-weight:400">(min. 8 caractères)</span></label>
                <input type="password" id="password" name="password" class="form-input"
                       required minlength="8" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirmer le mot de passe *</label>
                <input type="password" id="password_confirm" name="password_confirm" class="form-input"
                       required minlength="8" autocomplete="new-password">
            </div>
        </div>

        <div class="flex-between" style="margin-top:18px">
            <a href="admins-list.php" class="btn btn-ghost">Annuler</a>
            <button class="btn btn-accent" type="submit">
                <i class="fas fa-user-plus"></i> Créer l'admin
            </button>
        </div>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
