<?php
/**
 * Compte Professionnel - AtlanTech E-commerce
 */

require_once '../config/config.php';

if (!isLoggedIn()) {
    redirect('../account.php?redirect=business');
}

$user_id = (int)$_SESSION['user_id'];

// Charger l'utilisateur
$stmt = $mysqli->prepare("SELECT name, email, phone FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$success = false;
$errors  = [];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $company   = trim($_POST['company_name']   ?? '');
        $nif       = trim($_POST['nif']            ?? '');
        $sector    = trim($_POST['sector']         ?? '');
        $employees = trim($_POST['employees']      ?? '');
        $message   = trim($_POST['message']        ?? '');

        if (empty($company)) $errors[] = 'Le nom de l\'entreprise est obligatoire.';
        if (empty($sector))  $errors[] = 'Le secteur d\'activité est obligatoire.';

        if (empty($errors)) {
            // Enregistrer la demande dans les logs d'activité admin
            $subject = "Demande compte pro : $company";
            $body    = "Utilisateur ID $user_id ({$user['name']}, {$user['email']})\n"
                     . "Entreprise : $company\nNIF : $nif\nSecteur : $sector\n"
                     . "Effectif : $employees\nMessage : $message";
            // Log interne (simple log fichier)
            error_log("[COMPTE PRO] $body");
            $success = true;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no"/>
    <title>Compte Professionnel - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png" type="image/x-icon"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        body { background: #f3f3f3; }
        .biz-wrap { max-width: 860px; margin: 40px auto; padding: 0 20px 80px; }
        .breadcrumb-nav { font-size: 13px; color: #666; margin-bottom: 20px; }
        .breadcrumb-nav a { color: #007185; text-decoration: none; }
        .breadcrumb-nav a:hover { text-decoration: underline; }
        .page-title { font-size: 26px; font-weight: 700; color: #0F1111; margin-bottom: 6px; }
        .page-sub   { font-size: 15px; color: #565959; margin-bottom: 28px; }

        /* Hero */
        .hero-band {
            background: linear-gradient(135deg, #0F1111 0%, #1a2740 100%);
            color: #fff; border-radius: 12px; padding: 36px 40px;
            margin-bottom: 32px; display: flex; gap: 32px; align-items: center; flex-wrap: wrap;
        }
        .hero-band .icon { font-size: 64px; }
        .hero-band h2 { font-size: 22px; font-weight: 800; margin-bottom: 8px; }
        .hero-band p  { font-size: 14px; color: #ccc; margin: 0; }

        /* Avantages */
        .benefits-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 16px; margin-bottom: 32px; }
        .benefit {
            background: #fff; border: 1px solid #D5D9D9; border-radius: 8px;
            padding: 20px; display: flex; gap: 14px; align-items: flex-start;
        }
        .benefit .icon { font-size: 28px; flex-shrink: 0; }
        .benefit h4 { font-size: 14px; font-weight: 700; color: #0F1111; margin-bottom: 4px; }
        .benefit p  { font-size: 12px; color: #666; margin: 0; }

        /* Formulaire */
        .form-card { background: #fff; border: 1px solid #D5D9D9; border-radius: 8px; padding: 32px; }
        .form-card h3 { font-size: 18px; font-weight: 700; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid #E7E7E7; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 0; }
        .form-group.full { grid-column: 1 / -1; }
        .form-label { display: block; font-size: 13px; font-weight: 600; color: #0F1111; margin-bottom: 6px; }
        .form-control {
            width: 100%; padding: 10px 12px; border: 1px solid #888C8C;
            border-radius: 6px; font-size: 14px; color: #0F1111;
        }
        .form-control:focus { outline: none; border-color: #e77600; box-shadow: 0 0 0 3px rgba(231,118,0,0.15); }
        .btn-submit {
            margin-top: 24px; padding: 12px 32px; background: #FFD814; border: 1px solid #FFA41C;
            border-radius: 8px; font-size: 14px; font-weight: 700; color: #0F1111; cursor: pointer;
        }
        .btn-submit:hover { background: #F7CA00; }
        .alert { padding: 14px 18px; border-radius: 8px; margin-bottom: 22px; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; border: 1px solid #6ee7b7; }
        .alert-error   { background: #fee2e2; color: #991b1b; border: 1px solid #fca5a5; }

        @media (max-width: 600px) { .form-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<div style="background:#131921; padding:12px 20px; display:flex; align-items:center; gap:20px;">
    <a href="../index.php"><img src="../assets/img/logo/logo.svg" alt="AtlanTech" style="height:40px;"></a>
    <div style="flex:1;"></div>
    <a href="dashboard.php" style="color:#fff; font-size:13px; text-decoration:none;">
        <i class="fas fa-user-circle"></i>&nbsp;Mon compte
    </a>
</div>

<div class="biz-wrap">
    <nav class="breadcrumb-nav">
        <a href="../index.php">Accueil</a> &rsaquo;
        <a href="dashboard.php">Mon compte</a> &rsaquo;
        <span>Compte Professionnel</span>
    </nav>

    <!-- Hero -->
    <div class="hero-band">
        <div class="icon">💼</div>
        <div>
            <h2>AtlanTech Business</h2>
            <p>Accédez à des tarifs préférentiels, une facturation B2B, et un service dédié<br>
               pour les entreprises et professionnels en Haïti.</p>
        </div>
    </div>

    <!-- Avantages -->
    <h2 style="font-size:18px; font-weight:700; margin-bottom:16px;">Pourquoi passer au compte professionnel ?</h2>
    <div class="benefits-grid">
        <div class="benefit">
            <div class="icon">🏷️</div>
            <div><h4>Tarifs négociés</h4><p>Prix réduits sur les commandes en volume et les achats réguliers</p></div>
        </div>
        <div class="benefit">
            <div class="icon">🧾</div>
            <div><h4>Facturation B2B</h4><p>Factures avec NIF, raison sociale et mentions légales</p></div>
        </div>
        <div class="benefit">
            <div class="icon">🚚</div>
            <div><h4>Livraison prioritaire</h4><p>Traitement express de vos commandes professionnelles</p></div>
        </div>
        <div class="benefit">
            <div class="icon">📞</div>
            <div><h4>Account Manager</h4><p>Un interlocuteur dédié pour vos besoins spécifiques</p></div>
        </div>
        <div class="benefit">
            <div class="icon">📊</div>
            <div><h4>Rapports d'achats</h4><p>Tableau de bord et exports de vos dépenses</p></div>
        </div>
        <div class="benefit">
            <div class="icon">💳</div>
            <div><h4>Paiement différé</h4><p>Option de paiement à 30 jours pour les clients qualifiés</p></div>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <strong>✅ Demande envoyée !</strong><br>
            Notre équipe commerciale vous contactera sous 48h à l'adresse <strong><?php echo htmlspecialchars($user['email']); ?></strong>.
        </div>
    <?php else: ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-error">
                <?php foreach ($errors as $e): ?><div><?php echo htmlspecialchars($e); ?></div><?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Formulaire de demande -->
        <div class="form-card">
            <h3>Demander un compte professionnel</h3>
            <form method="POST" action="business.php">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">

                <div class="form-grid">
                    <div class="form-group">
                        <label class="form-label">Nom complet du contact <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="contact_name" class="form-control"
                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email <span style="color:#ef4444;">*</span></label>
                        <input type="email" name="email" class="form-control"
                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nom de l'entreprise <span style="color:#ef4444;">*</span></label>
                        <input type="text" name="company_name" class="form-control" required
                               placeholder="Ex : TechHaïti SARL">
                    </div>
                    <div class="form-group">
                        <label class="form-label">NIF (Numéro d'identification fiscale)</label>
                        <input type="text" name="nif" class="form-control" placeholder="Ex : 123-456-789">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Secteur d'activité <span style="color:#ef4444;">*</span></label>
                        <select name="sector" class="form-control" required>
                            <option value="">-- Choisir --</option>
                            <option>Informatique / Tech</option>
                            <option>Éducation</option>
                            <option>Santé</option>
                            <option>Commerce de détail</option>
                            <option>ONG / Association</option>
                            <option>Administration publique</option>
                            <option>Hôtellerie / Tourisme</option>
                            <option>Autre</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Nombre d'employés</label>
                        <select name="employees" class="form-control">
                            <option value="">-- Choisir --</option>
                            <option>1–10</option>
                            <option>11–50</option>
                            <option>51–200</option>
                            <option>200+</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label class="form-label">Message (optionnel)</label>
                        <textarea name="message" class="form-control" rows="3"
                                  placeholder="Décrivez vos besoins..."></textarea>
                    </div>
                </div>

                <button type="submit" class="btn-submit">Envoyer ma demande</button>
            </form>
        </div>
    <?php endif; ?>

    <div style="margin-top:24px;">
        <a href="dashboard.php" style="color:#007185; text-decoration:none; font-size:14px;">
            &larr; Retour au tableau de bord
        </a>
    </div>
</div>

<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>
