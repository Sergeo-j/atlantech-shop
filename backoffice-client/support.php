<?php
/**
 * Services Numériques & Assistance - AtlanTech
 */
require_once '../config/config.php';
if (!isLoggedIn()) redirect('../account.php?redirect=support');

$user_id = (int)$_SESSION['user_id'];
$stmt = $mysqli->prepare("SELECT name, email, phone FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf_token = $_SESSION['csrf_token'];

$success = ''; $errors = [];
$tab = $_GET['tab'] ?? 'help'; // help | ticket | faq

// Dernières commandes pour le contexte
$stmt = $mysqli->prepare(
    "SELECT id, order_number, status, total_amount, created_at FROM orders WHERE user_id = ? ORDER BY created_at DESC LIMIT 5"
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$recent_orders = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'ticket') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
        $errors[] = 'Token de sécurité invalide.';
    } else {
        $tab     = 'ticket';
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $order   = trim($_POST['order_ref'] ?? '');

        if (empty($subject)) $errors[] = 'Le sujet est obligatoire.';
        if (strlen($message) < 20) $errors[] = 'Décrivez votre problème en au moins 20 caractères.';

        if (empty($errors)) {
            $full_subject = $order ? "[$order] $subject" : $subject;
            $stmt = $mysqli->prepare(
                "INSERT INTO contact_messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())"
            );
            $stmt->bind_param('ssss', $user['name'], $user['email'], $full_subject, $message);
            $stmt->execute();
            $stmt->close();
            $success = "Votre demande a bien été envoyée. Notre équipe vous répondra à l'adresse {$user['email']} dans les 24h.";
        }
    }
}

$faqs = [
    'commandes' => [
        'titre' => '📦 Commandes & Livraison',
        'items' => [
            ['q' => 'Comment suivre ma commande ?',      'r' => 'Rendez-vous dans <a href="orders.php">Mes Commandes</a> pour voir le statut en temps réel. Vous recevez aussi des mises à jour par e-mail.'],
            ['q' => 'Puis-je annuler une commande ?',    'r' => 'Vous pouvez annuler une commande tant qu\'elle est au statut "En attente". Contactez-nous rapidement via le formulaire ci-dessous.'],
            ['q' => 'Quels sont les délais de livraison ?', 'r' => 'Livraison à Les Cayes : 24-48h. Autres villes : 2-5 jours ouvrables selon l\'accessibilité.'],
            ['q' => 'Puis-je changer mon adresse de livraison ?', 'r' => 'Oui, si la commande n\'est pas encore expédiée. Contactez le support avec votre numéro de commande.'],
        ],
    ],
    'paiement' => [
        'titre' => '💳 Paiement',
        'items' => [
            ['q' => 'Quels moyens de paiement acceptez-vous ?', 'r' => 'MonCash, Zelle, virement bancaire et espèces à la livraison (zones couvertes uniquement).'],
            ['q' => 'Mon paiement MonCash a échoué, que faire ?', 'r' => 'Vérifiez votre solde MonCash et réessayez. Si le problème persiste, contactez-nous avec votre numéro de transaction.'],
            ['q' => 'Puis-je obtenir un remboursement ?',  'r' => 'Oui, pour les articles défectueux ou non conformes dans les 7 jours suivant la réception. Contactez-nous avec photos à l\'appui.'],
        ],
    ],
    'produits' => [
        'titre' => '🖥️ Produits & Garantie',
        'items' => [
            ['q' => 'Les produits sont-ils garantis ?',   'r' => 'Oui, selon le fabricant (6 mois à 2 ans). La garantie couvre les défauts de fabrication, pas la casse accidentelle.'],
            ['q' => 'Comment faire une réclamation garantie ?', 'r' => 'Contactez-nous en précisant votre numéro de commande et la nature du défaut. Nous organiserons un échange ou une réparation.'],
            ['q' => 'Les prix incluent-ils les taxes ?',  'r' => 'Oui, tous nos prix sont affichés TTC.'],
        ],
    ],
    'compte' => [
        'titre' => '👤 Mon Compte',
        'items' => [
            ['q' => 'Comment changer mon mot de passe ?', 'r' => 'Rendez-vous dans <a href="security.php?tab=password">Connexion & Sécurité</a> → onglet Mot de passe.'],
            ['q' => 'Comment supprimer mon compte ?',     'r' => 'Contactez notre service client. La suppression est définitive et irréversible.'],
        ],
    ],
];

$status_labels = ['pending'=>'En attente','paid'=>'Payée','shipped'=>'Expédiée','delivered'=>'Livrée','cancelled'=>'Annulée'];
$status_colors = ['pending'=>'#f59e0b','paid'=>'#3b82f6','shipped'=>'#8b5cf6','delivered'=>'#10b981','cancelled'=>'#ef4444'];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8"/><meta name="viewport" content="width=device-width, initial-scale=1"/>
    <title>Assistance - AtlanTech</title>
    <link rel="shortcut icon" href="../assets/img/favicon.png"/>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="../assets/css/fontawesome.css"/>
    <link rel="stylesheet" href="../assets/css/main.css"/>
    <style>
        body{background:#f3f3f3;}
        .wrap{max-width:960px;margin:40px auto;padding:0 20px 80px;}
        .breadcrumb-nav{font-size:13px;color:#666;margin-bottom:20px;}
        .breadcrumb-nav a{color:#007185;text-decoration:none;}
        .page-title{font-size:26px;font-weight:700;color:#0F1111;margin-bottom:24px;}
        .alert{padding:14px 18px;border-radius:8px;margin-bottom:22px;font-size:14px;}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #fca5a5;}

        .tabs{display:flex;gap:0;border-bottom:2px solid #D5D9D9;margin-bottom:28px;}
        .tab-btn{padding:12px 22px;font-size:14px;font-weight:600;color:#565959;text-decoration:none;border-bottom:3px solid transparent;margin-bottom:-2px;}
        .tab-btn.active{color:#0F1111;border-bottom-color:#e77600;}
        .tab-btn:hover{color:#0F1111;}

        /* Help cards */
        .help-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-bottom:32px;}
        .help-card{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:22px;text-align:center;text-decoration:none;color:#0F1111;transition:box-shadow .2s,border-color .2s;}
        .help-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.1);border-color:#007185;}
        .help-card .icon{font-size:38px;display:block;margin-bottom:12px;}
        .help-card h4{font-size:15px;font-weight:700;margin-bottom:6px;}
        .help-card p{font-size:12px;color:#666;margin:0;}

        /* Contact info */
        .contact-bar{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:20px 24px;display:flex;flex-wrap:wrap;gap:20px;align-items:center;margin-bottom:28px;}
        .contact-item{display:flex;align-items:center;gap:10px;font-size:14px;}
        .contact-item .icon{font-size:20px;}

        /* Ticket form */
        .ticket-card{background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:28px;}
        .form-group{margin-bottom:18px;}
        .form-label{display:block;font-size:13px;font-weight:600;color:#0F1111;margin-bottom:5px;}
        .form-control{width:100%;padding:10px 12px;border:1px solid #888C8C;border-radius:6px;font-size:14px;}
        .form-control:focus{outline:none;border-color:#e77600;box-shadow:0 0 0 3px rgba(231,118,0,.15);}
        .btn-submit{padding:11px 28px;background:#FFD814;border:1px solid #FFA41C;border-radius:8px;font-size:14px;font-weight:700;color:#0F1111;cursor:pointer;}
        .btn-submit:hover{background:#F7CA00;}

        /* FAQ accordion */
        .faq-section{margin-bottom:24px;}
        .faq-group{background:#fff;border:1px solid #D5D9D9;border-radius:8px;overflow:hidden;margin-bottom:14px;}
        .faq-group-title{padding:14px 20px;font-size:15px;font-weight:700;background:#F0F2F2;cursor:pointer;display:flex;justify-content:space-between;align-items:center;user-select:none;}
        .faq-group-title .toggle{font-size:18px;transition:transform .3s;}
        .faq-group.open .toggle{transform:rotate(45deg);}
        .faq-items{display:none;}
        .faq-group.open .faq-items{display:block;}
        .faq-item{border-top:1px solid #E7E7E7;}
        .faq-q{padding:12px 20px;font-size:14px;font-weight:600;cursor:pointer;display:flex;justify-content:space-between;align-items:center;}
        .faq-q:hover{background:#fafafa;}
        .faq-a{padding:0 20px 14px;font-size:13px;color:#555;line-height:1.6;display:none;}
        .faq-a a{color:#007185;}

        /* Commandes récentes dans ticket */
        .order-select{display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:10px;margin-bottom:18px;}
        .order-opt{border:1px solid #D5D9D9;border-radius:6px;padding:10px 12px;cursor:pointer;font-size:13px;}
        .order-opt input{margin-right:6px;}
        .order-opt:has(input:checked){border-color:#e77600;background:#fffde7;}
    </style>
</head>
<body>
<div style="background:#131921;padding:12px 20px;display:flex;align-items:center;gap:20px;">
    <a href="../index.php"><img src="../assets/img/logo/logo.svg" alt="AtlanTech" style="height:40px;"></a>
    <div style="flex:1;"></div>
    <a href="dashboard.php" style="color:#fff;font-size:13px;text-decoration:none;"><i class="fas fa-user-circle"></i>&nbsp;Mon compte</a>
</div>

<div class="wrap">
    <nav class="breadcrumb-nav">
        <a href="../index.php">Accueil</a> &rsaquo; <a href="dashboard.php">Mon compte</a> &rsaquo; <span>Assistance</span>
    </nav>
    <h1 class="page-title">Services Numériques &amp; Assistance</h1>

    <?php if ($success): ?><div class="alert alert-success">✅ <?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <?php if (!empty($errors)): ?><div class="alert alert-error"><?php foreach($errors as $e) echo '<div>'.htmlspecialchars($e).'</div>'; ?></div><?php endif; ?>

    <!-- Barre de contact -->
    <div class="contact-bar">
        <div class="contact-item"><span class="icon">📞</span><div><strong>+509 4466-7553</strong><div style="font-size:11px;color:#666;">Lun–Sam · 8h–18h</div></div></div>
        <div class="contact-item"><span class="icon">✉️</span><div><strong>support@atlantech.ht</strong><div style="font-size:11px;color:#666;">Réponse sous 24h</div></div></div>
        <div class="contact-item"><span class="icon">💬</span><div><strong>WhatsApp</strong><div style="font-size:11px;color:#666;">+509 4466-7553</div></div></div>
        <div class="contact-item"><span class="icon">📍</span><div><strong>Les Cayes, Haïti</strong><div style="font-size:11px;color:#666;">Rue principale, Centre-ville</div></div></div>
    </div>

    <!-- Tabs -->
    <div class="tabs">
        <a href="support.php?tab=help"   class="tab-btn <?php echo $tab==='help'   ? 'active':''; ?>">Aide rapide</a>
        <a href="support.php?tab=ticket" class="tab-btn <?php echo $tab==='ticket' ? 'active':''; ?>">Ouvrir un ticket</a>
        <a href="support.php?tab=faq"    class="tab-btn <?php echo $tab==='faq'    ? 'active':''; ?>">FAQ</a>
    </div>

    <?php if ($tab === 'help'): ?>
        <div class="help-grid">
            <a href="support.php?tab=ticket&type=commande" class="help-card">
                <span class="icon">📦</span><h4>Problème de commande</h4><p>Retard, article manquant, mauvais produit</p>
            </a>
            <a href="support.php?tab=ticket&type=paiement" class="help-card">
                <span class="icon">💳</span><h4>Problème de paiement</h4><p>Paiement refusé, remboursement, double débit</p>
            </a>
            <a href="support.php?tab=ticket&type=garantie" class="help-card">
                <span class="icon">🔧</span><h4>Garantie / SAV</h4><p>Produit défectueux, réclamation garantie</p>
            </a>
            <a href="support.php?tab=ticket&type=compte" class="help-card">
                <span class="icon">👤</span><h4>Mon compte</h4><p>Accès, mot de passe, données personnelles</p>
            </a>
            <a href="support.php?tab=ticket&type=livraison" class="help-card">
                <span class="icon">🚚</span><h4>Livraison</h4><p>Suivi, adresse, délai de livraison</p>
            </a>
            <a href="support.php?tab=faq" class="help-card">
                <span class="icon">❓</span><h4>FAQ</h4><p>Consulter les questions fréquentes</p>
            </a>
        </div>

        <?php if (!empty($recent_orders)): ?>
        <div style="background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:22px;margin-bottom:24px;">
            <div style="font-size:16px;font-weight:700;color:#0F1111;margin-bottom:14px;">Vos commandes récentes</div>
            <?php foreach ($recent_orders as $o): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 0;border-bottom:1px solid #F0F2F2;font-size:13px;">
                    <div>
                        <a href="order-detail.php?id=<?php echo $o['id']; ?>" style="color:#007185;font-weight:700;">
                            #<?php echo htmlspecialchars($o['order_number']); ?>
                        </a>
                        &nbsp;&mdash;&nbsp;<?php echo date('d/m/Y', strtotime($o['created_at'])); ?>
                    </div>
                    <div style="display:flex;align-items:center;gap:14px;">
                        <span style="color:<?php echo $status_colors[$o['status']] ?? '#666'; ?>;font-weight:600;">
                            <?php echo $status_labels[$o['status']] ?? $o['status']; ?>
                        </span>
                        <a href="support.php?tab=ticket&order=<?php echo urlencode($o['order_number']); ?>"
                           style="color:#007185;text-decoration:none;font-size:12px;">Aide pour cette commande</a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

    <?php elseif ($tab === 'ticket'): ?>
        <div class="ticket-card">
            <div style="font-size:17px;font-weight:700;margin-bottom:20px;">Envoyer une demande d'assistance</div>
            <form method="POST" action="support.php?tab=ticket">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrf_token); ?>">
                <input type="hidden" name="action" value="ticket">

                <?php if (!empty($recent_orders)): ?>
                <div class="form-group">
                    <label class="form-label">Commande concernée (optionnel)</label>
                    <select name="order_ref" class="form-control">
                        <option value="">-- Aucune commande spécifique --</option>
                        <?php foreach ($recent_orders as $o): ?>
                            <option value="<?php echo htmlspecialchars($o['order_number']); ?>"
                                <?php echo ($_GET['order'] ?? '') === $o['order_number'] ? 'selected' : ''; ?>>
                                #<?php echo htmlspecialchars($o['order_number']); ?> — <?php echo date('d/m/Y', strtotime($o['created_at'])); ?>
                                (<?php echo $status_labels[$o['status']] ?? $o['status']; ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Type de problème</label>
                    <select name="subject" class="form-control" required>
                        <option value="">-- Choisir un sujet --</option>
                        <?php
                        $type_map = [
                            'commande' => 'Problème avec une commande',
                            'paiement' => 'Problème de paiement',
                            'garantie' => 'Réclamation garantie / SAV',
                            'compte'   => 'Problème avec mon compte',
                            'livraison'=> 'Problème de livraison',
                            ''         => null,
                        ];
                        $subjects = ['Problème avec une commande','Problème de paiement','Réclamation garantie / SAV',
                                     'Produit non conforme à la description','Remboursement','Problème de livraison',
                                     'Problème avec mon compte','Autre'];
                        $preselect = $type_map[$_GET['type'] ?? ''] ?? '';
                        foreach ($subjects as $s):
                        ?>
                            <option value="<?php echo htmlspecialchars($s); ?>"
                                <?php echo $s === $preselect ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($s); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Description du problème <span style="color:#ef4444;">*</span></label>
                    <textarea name="message" class="form-control" rows="5" required
                              placeholder="Décrivez votre problème en détail : que s'est-il passé, depuis quand, ce que vous avez déjà essayé..."></textarea>
                </div>

                <div style="background:#F0F2F2;border-radius:6px;padding:12px 16px;font-size:13px;color:#555;margin-bottom:18px;">
                    <strong>Votre demande sera envoyée à :</strong> <?php echo htmlspecialchars($user['email']); ?><br>
                    Nous répondrons dans les <strong>24 heures</strong> ouvrables.
                </div>

                <button type="submit" class="btn-submit">Envoyer la demande</button>
            </form>
        </div>

    <?php else: // FAQ ?>
        <div class="faq-section">
            <?php foreach ($faqs as $key => $group): ?>
                <div class="faq-group" id="group-<?php echo $key; ?>">
                    <div class="faq-group-title" onclick="toggleGroup('<?php echo $key; ?>')">
                        <span><?php echo $group['titre']; ?></span>
                        <span class="toggle">+</span>
                    </div>
                    <div class="faq-items">
                        <?php foreach ($group['items'] as $i => $item): ?>
                            <div class="faq-item">
                                <div class="faq-q" onclick="toggleQ(this)">
                                    <?php echo htmlspecialchars($item['q']); ?>
                                    <span style="color:#888;font-size:18px;">+</span>
                                </div>
                                <div class="faq-a"><?php echo $item['r']; ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <div style="background:#fff;border:1px solid #D5D9D9;border-radius:8px;padding:20px;text-align:center;font-size:14px;color:#555;">
            Vous n'avez pas trouvé votre réponse ?
            <a href="support.php?tab=ticket" style="color:#007185;font-weight:700;margin-left:6px;">Ouvrir un ticket →</a>
        </div>
    <?php endif; ?>

    <div style="margin-top:24px;"><a href="dashboard.php" style="color:#007185;text-decoration:none;font-size:14px;">&larr; Retour au tableau de bord</a></div>
</div>

<script src="../assets/js/jquery-3.5.1.min.js"></script>
<script src="../assets/js/bootstrap.bundle.min.js"></script>
<script>
function toggleGroup(key) {
    var g = document.getElementById('group-' + key);
    g.classList.toggle('open');
}
function toggleQ(el) {
    var a = el.nextElementSibling;
    var sp = el.querySelector('span');
    if (a.style.display === 'block') { a.style.display = 'none'; sp.textContent = '+'; }
    else { a.style.display = 'block'; sp.textContent = '−'; }
}
// Ouvrir le premier groupe par défaut
document.querySelector('.faq-group')?.classList.add('open');
</script>
</body>
</html>
