<?php
/**
 * Mes Adresses de Livraison - AtlanTech E-commerce
 * CRUD : ajout, modification, suppression, adresse par défaut
 */

require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('../account.php?redirect=addresses');
}

$user_id = (int)$_SESSION['user_id'];

// Générer le token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$errors  = [];
$success = '';

// ──────────────────────────────────────────────
// Actions POST
// ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $errors[] = 'Token de sécurité invalide.';
    } elseif ($action === 'add' || $action === 'edit') {

        $address_id       = (int)($_POST['address_id'] ?? 0);
        $address_type     = in_array($_POST['address_type'] ?? '', ['home','work','other']) ? $_POST['address_type'] : 'home';
        $label            = trim($_POST['label']            ?? '');
        $recipient_name   = trim($_POST['recipient_name']   ?? '');
        $recipient_phone  = trim($_POST['recipient_phone']  ?? '');
        $address_line1    = trim($_POST['address_line1']    ?? '');
        $address_line2    = trim($_POST['address_line2']    ?? '');
        $city             = trim($_POST['city']             ?? '');
        $state            = trim($_POST['state']            ?? '');
        $postal_code      = trim($_POST['postal_code']      ?? '');
        $country          = trim($_POST['country']          ?? 'Haïti');
        $delivery_instructions = trim($_POST['delivery_instructions'] ?? '');
        $is_default       = isset($_POST['is_default']) ? 1 : 0;

        if (empty($recipient_name))  $errors[] = 'Le nom du destinataire est obligatoire.';
        if (empty($address_line1))   $errors[] = "L'adresse est obligatoire.";
        if (empty($city))            $errors[] = 'La ville est obligatoire.';

        if (empty($errors)) {
            // Si on définit par défaut, enlever l'ancien défaut
            if ($is_default) {
                $stmt = $mysqli->prepare(
                    "UPDATE addresses SET is_default = 0 WHERE user_id = ?"
                );
                $stmt->bind_param('i', $user_id);
                $stmt->execute();
                $stmt->close();
            }

            if ($action === 'add') {
                $stmt = $mysqli->prepare(
                    "INSERT INTO addresses
                     (user_id, address_type, label, recipient_name, recipient_phone,
                      address_line1, address_line2, city, state, postal_code,
                      country, delivery_instructions, is_default)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                );
                $stmt->bind_param(
                    'isssssssssssi',
                    $user_id, $address_type, $label, $recipient_name, $recipient_phone,
                    $address_line1, $address_line2, $city, $state, $postal_code,
                    $country, $delivery_instructions, $is_default
                );
                $stmt->execute();
                $stmt->close();
                $success = 'Adresse ajoutée avec succès.';
            } else {
                // Vérifier que l'adresse appartient bien à cet utilisateur
                $stmt = $mysqli->prepare(
                    "SELECT id FROM addresses WHERE id = ? AND user_id = ? LIMIT 1"
                );
                $stmt->bind_param('ii', $address_id, $user_id);
                $stmt->execute();
                if ($stmt->get_result()->num_rows === 0) {
                    $errors[] = 'Adresse introuvable.';
                } else {
                    $stmt->close();
                    $stmt = $mysqli->prepare(
                        "UPDATE addresses SET
                             address_type = ?, label = ?, recipient_name = ?, recipient_phone = ?,
                             address_line1 = ?, address_line2 = ?, city = ?, state = ?,
                             postal_code = ?, country = ?, delivery_instructions = ?, is_default = ?
                         WHERE id = ? AND user_id = ?"
                    );
                    $stmt->bind_param(
                        'ssssssssssssii',
                        $address_type, $label, $recipient_name, $recipient_phone,
                        $address_line1, $address_line2, $city, $state,
                        $postal_code, $country, $delivery_instructions, $is_default,
                        $address_id, $user_id
                    );
                    $stmt->execute();
                    $success = 'Adresse modifiée avec succès.';
                }
                $stmt->close();
            }
        }

    } elseif ($action === 'delete') {
        $address_id = (int)($_POST['address_id'] ?? 0);
        $stmt = $mysqli->prepare(
            "DELETE FROM addresses WHERE id = ? AND user_id = ?"
        );
        $stmt->bind_param('ii', $address_id, $user_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $success = 'Adresse supprimée.';
        } else {
            $errors[] = 'Impossible de supprimer cette adresse.';
        }
        $stmt->close();

    } elseif ($action === 'set_default') {
        $address_id = (int)($_POST['address_id'] ?? 0);
        // Retirer tous les défauts
        $stmt = $mysqli->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
        $stmt->bind_param('i', $user_id);
        $stmt->execute();
        $stmt->close();
        // Définir le nouveau défaut
        $stmt = $mysqli->prepare(
            "UPDATE addresses SET is_default = 1 WHERE id = ? AND user_id = ?"
        );
        $stmt->bind_param('ii', $address_id, $user_id);
        $stmt->execute();
        $stmt->close();
        $success = 'Adresse par défaut mise à jour.';
    }
}

// ──────────────────────────────────────────────
// Charger les adresses
// ──────────────────────────────────────────────
$stmt = $mysqli->prepare(
    "SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, id ASC"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$addresses = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Adresse en cours d'édition ?
$edit_address = null;
$view = $_GET['view'] ?? 'list'; // list | add | edit
if ($view === 'edit' && isset($_GET['id'])) {
    $edit_id = (int)$_GET['id'];
    foreach ($addresses as $a) {
        if ($a['id'] === $edit_id) { $edit_address = $a; break; }
    }
    if (!$edit_address) $view = 'list';
}

$type_labels = ['home' => '🏠 Domicile', 'work' => '🏢 Bureau', 'other' => '📍 Autre'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Mes Adresses - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png" type="image/x-icon"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        .addr-wrap { max-width: 900px; margin: 40px auto; padding: 0 20px 80px; }
        .page-title { font-size: 26px; font-weight: 700; color: #0F1111; margin-bottom: 6px; }
        .breadcrumb-nav { font-size: 13px; color: #666; margin-bottom: 28px; }
        .breadcrumb-nav a { color: #007185; text-decoration: none; }
        .breadcrumb-nav a:hover { text-decoration: underline; }

        /* Alert */
        .alert {
            padding: 12px 16px; border-radius: 6px; margin-bottom: 22px;
            font-size: 14px; display: flex; align-items: flex-start; gap: 10px;
        }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        /* Grid d'adresses */
        .addr-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .addr-card {
            background: #fff; border: 1px solid #D5D9D9;
            border-radius: 8px; padding: 18px; position: relative;
        }
        .addr-card.default-card { border-color: #e77600; box-shadow: 0 0 0 2px rgba(231,118,0,0.15); }
        .default-badge {
            display: inline-block; background: #e77600; color: #fff;
            font-size: 11px; font-weight: 700; padding: 2px 10px;
            border-radius: 12px; margin-bottom: 10px;
        }
        .addr-type { font-size: 13px; color: #565959; margin-bottom: 6px; }
        .addr-name { font-size: 15px; font-weight: 700; color: #0F1111; margin-bottom: 4px; }
        .addr-detail { font-size: 13px; color: #565959; line-height: 1.5; }
        .addr-actions { display: flex; gap: 10px; margin-top: 14px; flex-wrap: wrap; }
        .btn-sm-link { font-size: 13px; color: #007185; text-decoration: none; cursor: pointer; }
        .btn-sm-link:hover { text-decoration: underline; }
        .btn-sm-link.danger { color: #ef4444; }
        .separator { color: #D5D9D9; }

        /* Carte "Ajouter" */
        .addr-card-add {
            border: 2px dashed #D5D9D9; border-radius: 8px; padding: 18px;
            display: flex; flex-direction: column; align-items: center;
            justify-content: center; gap: 10px; cursor: pointer; transition: border-color 0.2s;
            text-decoration: none; color: #007185; min-height: 160px;
        }
        .addr-card-add:hover { border-color: #007185; }
        .addr-card-add .icon { font-size: 32px; }

        /* Formulaire */
        .form-section { background: #fff; border: 1px solid #D5D9D9; border-radius: 8px; padding: 28px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 18px; }
        .form-group.full { grid-column: 1 / -1; }
        .form-label { display: block; font-size: 14px; font-weight: 600; color: #0F1111; margin-bottom: 6px; }
        .form-control {
            width: 100%; padding: 10px 12px; border: 1px solid #888C8C;
            border-radius: 6px; font-size: 14px; color: #0F1111;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .form-control:focus {
            outline: none; border-color: #e77600;
            box-shadow: 0 0 0 3px rgba(231,118,0,0.15);
        }
        .form-hint { font-size: 12px; color: #888C8C; margin-top: 4px; }

        .checkbox-row { display: flex; align-items: center; gap: 10px; }
        .checkbox-row input[type="checkbox"] { width: 16px; height: 16px; accent-color: #e77600; }

        .btn-submit {
            padding: 11px 28px; background: #FFD814; border: 1px solid #FFA41C;
            border-radius: 8px; font-size: 14px; font-weight: 700; color: #0F1111;
            cursor: pointer; transition: background 0.2s;
        }
        .btn-submit:hover { background: #F7CA00; }
        .btn-cancel {
            padding: 11px 20px; background: #fff; border: 1px solid #D5D9D9;
            border-radius: 8px; font-size: 14px; color: #0F1111;
            text-decoration: none; transition: background 0.2s;
        }
        .btn-cancel:hover { background: #F0F2F2; }

        .section-subtitle { font-size: 20px; font-weight: 700; color: #0F1111; margin-bottom: 18px; }

        @media (max-width: 600px) {
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<!-- ========= HEADER SIMPLIFIÉ ========= -->
<div style="background:#131921; padding:12px 20px; display:flex; align-items:center; gap:20px;">
    <a href="../index.php">
        <img src="../assets/img/logo/logo.svg" alt="AtlanTech" style="height:40px;">
    </a>
    <div style="flex:1;"></div>
    <a href="dashboard.php" style="color:#fff; font-size:13px; text-decoration:none;">
        <i class="fas fa-user-circle"></i>&nbsp;Mon compte
    </a>
    <a href="../cart.php" style="color:#fff; font-size:13px; text-decoration:none; margin-left:16px;">
        <i class="fas fa-shopping-cart"></i>
    </a>
</div>

<!-- ========= CONTENU ========= -->
<div class="addr-wrap">

    <nav class="breadcrumb-nav">
        <a href="../index.php">Accueil</a> &rsaquo;
        <a href="dashboard.php">Mon compte</a> &rsaquo;
        <span>Mes adresses</span>
    </nav>

    <h1 class="page-title">Mes Adresses</h1>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-error">
            <i class="fas fa-exclamation-circle"></i>
            <div>
                <?php foreach ($errors as $e): ?>
                    <div><?php echo htmlspecialchars($e); ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- ── VUE : LISTE ── -->
    <?php if ($view === 'list'): ?>

        <div class="addr-grid">
            <!-- Carte "Ajouter une adresse" -->
            <a href="addresses.php?view=add" class="addr-card-add">
                <span class="icon">➕</span>
                <strong>Ajouter une adresse</strong>
            </a>

            <?php foreach ($addresses as $addr): ?>
                <div class="addr-card <?php echo $addr['is_default'] ? 'default-card' : ''; ?>">
                    <?php if ($addr['is_default']): ?>
                        <span class="default-badge">⭐ Par défaut</span>
                    <?php endif; ?>

                    <div class="addr-type">
                        <?php echo $type_labels[$addr['address_type']] ?? '📍 Adresse'; ?>
                        <?php if (!empty($addr['label'])): ?>
                            &mdash; <?php echo htmlspecialchars($addr['label']); ?>
                        <?php endif; ?>
                    </div>

                    <div class="addr-name">
                        <?php echo htmlspecialchars($addr['recipient_name']); ?>
                    </div>

                    <div class="addr-detail">
                        <?php echo htmlspecialchars($addr['address_line1']); ?>
                        <?php if (!empty($addr['address_line2'])): ?>
                            <br><?php echo htmlspecialchars($addr['address_line2']); ?>
                        <?php endif; ?>
                        <br><?php echo htmlspecialchars($addr['city']); ?>
                        <?php if (!empty($addr['state'])): ?>,
                            <?php echo htmlspecialchars($addr['state']); ?>
                        <?php endif; ?>
                        <br><?php echo htmlspecialchars($addr['country']); ?>
                        <?php if (!empty($addr['recipient_phone'])): ?>
                            <br><i class="fas fa-phone" style="font-size:11px;"></i>
                            <?php echo htmlspecialchars($addr['recipient_phone']); ?>
                        <?php endif; ?>
                        <?php if (!empty($addr['delivery_instructions'])): ?>
                            <br><em style="color:#888;">
                                <?php echo htmlspecialchars($addr['delivery_instructions']); ?>
                            </em>
                        <?php endif; ?>
                    </div>

                    <div class="addr-actions">
                        <a href="addresses.php?view=edit&id=<?php echo $addr['id']; ?>" class="btn-sm-link">Modifier</a>
                        <span class="separator">|</span>
                        <form method="POST" style="display:inline;"
                              onsubmit="return confirm('Supprimer cette adresse ?');">
                            <input type="hidden" name="csrf_token"  value="<?php echo htmlspecialchars($csrf_token); ?>">
                            <input type="hidden" name="action"      value="delete">
                            <input type="hidden" name="address_id"  value="<?php echo $addr['id']; ?>">
                            <button type="submit" class="btn-sm-link danger" style="border:none;background:none;padding:0;">
                                Supprimer
                            </button>
                        </form>
                        <?php if (!$addr['is_default']): ?>
                            <span class="separator">|</span>
                            <form method="POST" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                                <input type="hidden" name="action"     value="set_default">
                                <input type="hidden" name="address_id" value="<?php echo $addr['id']; ?>">
                                <button type="submit" class="btn-sm-link" style="border:none;background:none;padding:0;">
                                    Définir par défaut
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

    <!-- ── VUE : FORMULAIRE (ajout ou édition) ── -->
    <?php else: ?>
        <?php $is_edit = ($view === 'edit' && $edit_address); ?>

        <h2 class="section-subtitle">
            <?php echo $is_edit ? 'Modifier l\'adresse' : 'Ajouter une adresse'; ?>
        </h2>

        <div class="form-section">
            <form method="POST" action="addresses.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action"
                       value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
                <?php if ($is_edit): ?>
                    <input type="hidden" name="address_id"
                           value="<?php echo (int)$edit_address['id']; ?>">
                <?php endif; ?>

                <div class="form-grid">
                    <!-- Type -->
                    <div class="form-group">
                        <label class="form-label" for="address_type">Type d'adresse</label>
                        <select id="address_type" name="address_type" class="form-control">
                            <?php foreach ($type_labels as $key => $lbl): ?>
                                <option value="<?php echo $key; ?>"
                                    <?php echo ($is_edit && $edit_address['address_type'] === $key) ? 'selected' : ''; ?>>
                                    <?php echo $lbl; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Libellé -->
                    <div class="form-group">
                        <label class="form-label" for="label">Libellé <span style="font-weight:400;color:#888;">(optionnel)</span></label>
                        <input type="text" id="label" name="label" class="form-control"
                               value="<?php echo htmlspecialchars($is_edit ? ($edit_address['label'] ?? '') : ''); ?>"
                               maxlength="80" placeholder="Ex : Maison principale">
                    </div>

                    <!-- Nom destinataire -->
                    <div class="form-group">
                        <label class="form-label" for="recipient_name">Nom du destinataire <span style="color:#ef4444;">*</span></label>
                        <input type="text" id="recipient_name" name="recipient_name" class="form-control"
                               value="<?php echo htmlspecialchars($is_edit ? ($edit_address['recipient_name'] ?? '') : ($_SESSION['user_name'] ?? '')); ?>"
                               required maxlength="100">
                    </div>

                    <!-- Téléphone -->
                    <div class="form-group">
                        <label class="form-label" for="recipient_phone">Téléphone</label>
                        <input type="tel" id="recipient_phone" name="recipient_phone" class="form-control"
                               value="<?php echo htmlspecialchars($is_edit ? ($edit_address['recipient_phone'] ?? '') : ''); ?>"
                               maxlength="20" placeholder="+509 XXXX-XXXX">
                    </div>

                    <!-- Adresse ligne 1 -->
                    <div class="form-group full">
                        <label class="form-label" for="address_line1">Adresse <span style="color:#ef4444;">*</span></label>
                        <input type="text" id="address_line1" name="address_line1" class="form-control"
                               value="<?php echo htmlspecialchars($is_edit ? ($edit_address['address_line1'] ?? '') : ''); ?>"
                               required maxlength="200" placeholder="Numéro et nom de rue">
                    </div>

                    <!-- Adresse ligne 2 -->
                    <div class="form-group full">
                        <label class="form-label" for="address_line2">Complément d'adresse</label>
                        <input type="text" id="address_line2" name="address_line2" class="form-control"
                               value="<?php echo htmlspecialchars($is_edit ? ($edit_address['address_line2'] ?? '') : ''); ?>"
                               maxlength="200" placeholder="Appartement, étage, quartier...">
                    </div>

                    <!-- Ville -->
                    <div class="form-group">
                        <label class="form-label" for="city">Ville <span style="color:#ef4444;">*</span></label>
                        <input type="text" id="city" name="city" class="form-control"
                               value="<?php echo htmlspecialchars($is_edit ? ($edit_address['city'] ?? '') : ''); ?>"
                               required maxlength="100">
                    </div>

                    <!-- Département -->
                    <div class="form-group">
                        <label class="form-label" for="state">Département / État</label>
                        <input type="text" id="state" name="state" class="form-control"
                               value="<?php echo htmlspecialchars($is_edit ? ($edit_address['state'] ?? '') : ''); ?>"
                               maxlength="100" placeholder="Ex : Sud">
                    </div>

                    <!-- Code postal -->
                    <div class="form-group">
                        <label class="form-label" for="postal_code">Code postal</label>
                        <input type="text" id="postal_code" name="postal_code" class="form-control"
                               value="<?php echo htmlspecialchars($is_edit ? ($edit_address['postal_code'] ?? '') : ''); ?>"
                               maxlength="20">
                    </div>

                    <!-- Pays -->
                    <div class="form-group">
                        <label class="form-label" for="country">Pays</label>
                        <input type="text" id="country" name="country" class="form-control"
                               value="<?php echo htmlspecialchars($is_edit ? ($edit_address['country'] ?? 'Haïti') : 'Haïti'); ?>"
                               maxlength="100">
                    </div>

                    <!-- Instructions livraison -->
                    <div class="form-group full">
                        <label class="form-label" for="delivery_instructions">
                            Instructions de livraison <span style="font-weight:400;color:#888;">(optionnel)</span>
                        </label>
                        <textarea id="delivery_instructions" name="delivery_instructions"
                                  class="form-control" rows="2" maxlength="500"
                                  placeholder="Ex : Sonner à l'interphone, laisser à la garderie..."><?php
                            echo htmlspecialchars($is_edit ? ($edit_address['delivery_instructions'] ?? '') : '');
                        ?></textarea>
                    </div>

                    <!-- Adresse par défaut -->
                    <div class="form-group full">
                        <label class="checkbox-row">
                            <input type="checkbox" name="is_default" value="1"
                                <?php echo ($is_edit && $edit_address['is_default']) ? 'checked' : ''; ?>
                                <?php echo (!$is_edit && empty($addresses)) ? 'checked' : ''; ?>>
                            <span style="font-size:14px; color:#0F1111;">
                                Définir comme adresse par défaut
                            </span>
                        </label>
                    </div>
                </div>

                <div style="display:flex; gap:12px; align-items:center; margin-top:8px;">
                    <button type="submit" class="btn-submit">
                        <?php echo $is_edit ? 'Enregistrer les modifications' : 'Ajouter l\'adresse'; ?>
                    </button>
                    <a href="addresses.php" class="btn-cancel">Annuler</a>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <div style="margin-top:24px;">
        <a href="dashboard.php" style="color:#007185; text-decoration:none; font-size:14px;">
            &larr; Retour au tableau de bord
        </a>
    </div>
</div>

<!-- Scripts -->
<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
