<?php
/**
 * AtlanTech — Helpers d'emails transactionnels pour les commandes
 *
 * Ce fichier fournit 3 fonctions principales :
 *   - sendOrderConfirmationToCustomer($order, $items) : mail au client
 *   - sendOrderAlertToAdmin($order, $items)           : mail à l'admin
 *   - sendOrderStatusEmailToCustomer($order, $oldStatus, $newStatus)
 *
 * Toutes les fonctions :
 *   - renvoient true/false
 *   - n'échouent jamais bruyamment (exception loggée via error_log)
 *   - reposent sur /config/mailer.php (PHPMailer + Gmail SMTP)
 */

require_once __DIR__ . '/mailer.php';

// ── Adresse de notification interne (où arrivent les alertes admin) ──
if (!defined('ADMIN_NOTIFICATION_EMAIL')) {
    define('ADMIN_NOTIFICATION_EMAIL', GMAIL_USER); // par défaut : le même Gmail
}
if (!defined('SHOP_URL_FOR_EMAIL')) {
    // Utilisé pour les liens dans les emails. En prod, à remplacer par le vrai domaine.
    define('SHOP_URL_FOR_EMAIL', 'http://localhost/atlantech-shop');
}
if (!defined('SHOP_NAME_FOR_EMAIL')) {
    define('SHOP_NAME_FOR_EMAIL', 'AtlanTech');
}

// ══════════════════════════════════════════════════════════════════
//  UTILITAIRES
// ══════════════════════════════════════════════════════════════════

function _atlFormatHTG(float $n): string {
    return number_format($n, 2, ',', ' ') . ' HTG';
}

function _atlEscape($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/**
 * Petit wrapper HTML pour toutes nos emails — aspect AtlanTech.
 */
function _atlEmailLayout(string $title, string $bodyHtml, string $footerExtra = ''): string {
    $safeTitle = _atlEscape($title);
    $year = date('Y');
    $shop = _atlEscape(SHOP_NAME_FOR_EMAIL);
    return <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$safeTitle}</title>
</head>
<body style="margin:0;padding:0;font-family:Arial,Helvetica,sans-serif;background:#f3f4f6;color:#111827;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:24px 0;">
<tr><td align="center">
    <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 2px 6px rgba(0,0,0,.06);">
        <!-- Header -->
        <tr><td style="background:linear-gradient(135deg,#8b5cf6,#6d28d9);padding:22px 26px;color:#fff;">
            <table role="presentation" width="100%"><tr>
                <td style="font-size:22px;font-weight:800;letter-spacing:.3px;">🔷 {$shop}</td>
                <td align="right" style="font-size:12px;opacity:.85;">Spécialiste High-Tech — Haïti</td>
            </tr></table>
        </td></tr>
        <!-- Body -->
        <tr><td style="padding:26px 28px;font-size:14.5px;line-height:1.6;color:#1f2937;">
            {$bodyHtml}
        </td></tr>
        <!-- Footer -->
        <tr><td style="background:#f9fafb;padding:16px 26px;border-top:1px solid #e5e7eb;font-size:12px;color:#6b7280;">
            {$footerExtra}
            <div style="margin-top:6px;">© {$year} {$shop} · Les Cayes, Haïti</div>
        </td></tr>
    </table>
</td></tr>
</table>
</body>
</html>
HTML;
}

/**
 * Construit le tableau HTML des articles d'une commande.
 * $items doit être un array de lignes contenant au minimum :
 *   [name, qty|quantity, price|unit_price, total|total_price]
 */
function _atlItemsTable(array $items, float $subtotal, float $shippingCost, float $total): string {
    $rows = '';
    if (empty($items)) {
        $rows = '<tr><td colspan="4" style="padding:10px;color:#9ca3af;text-align:center;">Aucun article</td></tr>';
    } else {
        foreach ($items as $it) {
            $name  = _atlEscape($it['name']      ?? $it['product_name'] ?? 'Produit');
            $qty   = (int)   ($it['qty']       ?? $it['quantity']     ?? 1);
            $price = (float) ($it['price']     ?? $it['unit_price']   ?? 0);
            $tot   = (float) ($it['total']     ?? $it['total_price']  ?? ($price * $qty));
            $rows .= '<tr>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;">' . $name . '</td>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;text-align:center;">× ' . $qty . '</td>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;text-align:right;">' . _atlFormatHTG($price) . '</td>'
                . '<td style="padding:10px 12px;border-bottom:1px solid #f1f5f9;text-align:right;color:#059669;font-weight:600;">' . _atlFormatHTG($tot) . '</td>'
                . '</tr>';
        }
    }

    $ship = $shippingCost == 0 ? 'Gratuite 🎁' : _atlFormatHTG($shippingCost);

    return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse;margin-top:10px;border:1px solid #e5e7eb;border-radius:8px;overflow:hidden;">'
        . '<thead><tr style="background:#f9fafb;color:#4b5563;font-size:12px;text-transform:uppercase;letter-spacing:.4px;">'
        .   '<th align="left"  style="padding:10px 12px;">Produit</th>'
        .   '<th align="center" style="padding:10px 12px;">Qté</th>'
        .   '<th align="right"  style="padding:10px 12px;">Prix</th>'
        .   '<th align="right"  style="padding:10px 12px;">Sous-total</th>'
        . '</tr></thead>'
        . '<tbody>' . $rows . '</tbody>'
        . '<tfoot style="background:#fafafa;font-size:13px;">'
        .   '<tr><td colspan="3" align="right" style="padding:8px 12px;color:#6b7280;">Sous-total</td>'
        .       '<td align="right" style="padding:8px 12px;">' . _atlFormatHTG($subtotal) . '</td></tr>'
        .   '<tr><td colspan="3" align="right" style="padding:8px 12px;color:#6b7280;">Livraison</td>'
        .       '<td align="right" style="padding:8px 12px;">' . $ship . '</td></tr>'
        .   '<tr style="background:#ede9fe;"><td colspan="3" align="right" style="padding:10px 12px;font-weight:700;color:#111827;">TOTAL</td>'
        .       '<td align="right" style="padding:10px 12px;font-weight:800;color:#6d28d9;font-size:15px;">' . _atlFormatHTG($total) . '</td></tr>'
        . '</tfoot>'
        . '</table>';
}

// ══════════════════════════════════════════════════════════════════
//  1) CONFIRMATION AU CLIENT (à la création de la commande)
// ══════════════════════════════════════════════════════════════════
function sendOrderConfirmationToCustomer(array $order, array $items): bool {
    $to = trim($order['customer_email'] ?? '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $num      = _atlEscape($order['order_number'] ?? '');
    $name     = _atlEscape($order['customer_name'] ?? 'Cher client');
    $addr     = _atlEscape($order['shipping_address'] ?? '—');
    $paymt    = _atlEscape($order['payment_method'] ?? '');
    $proc     = _atlEscape($order['payment_processor'] ?? '');
    if ($proc && $proc !== $paymt) $paymt .= ' — ' . $proc;

    $subtotal = (float)($order['subtotal'] ?? 0);
    $ship     = (float)($order['shipping_cost'] ?? 0);
    $total    = (float)($order['total_amount'] ?? $subtotal + $ship);

    $itemsTable = _atlItemsTable($items, $subtotal, $ship, $total);

    $body = '<h2 style="margin:0 0 8px;color:#6d28d9;">Merci pour votre commande, ' . $name . ' !</h2>'
          . '<p style="margin:0 0 12px;color:#4b5563;">Votre commande <strong style="color:#6d28d9;">#' . $num . '</strong> a bien été enregistrée.</p>'
          . '<div style="background:#fef3c7;border-left:4px solid #f59e0b;padding:12px 14px;border-radius:0 8px 8px 0;margin:14px 0;font-size:13.5px;color:#92400e;">'
          .   '⏳ <strong>Statut :</strong> En attente de paiement / validation. Vous recevrez un nouveau message dès que la commande passera à l\'étape suivante.'
          . '</div>'
          . '<h3 style="margin:18px 0 6px;font-size:14px;color:#374151;">📦 Récapitulatif</h3>'
          . $itemsTable
          . '<h3 style="margin:20px 0 6px;font-size:14px;color:#374151;">🚚 Livraison</h3>'
          . '<p style="margin:4px 0;color:#4b5563;">' . $addr . '</p>'
          . '<h3 style="margin:20px 0 6px;font-size:14px;color:#374151;">💳 Paiement</h3>'
          . '<p style="margin:4px 0;color:#4b5563;">' . $paymt . '</p>'
          . '<p style="margin:22px 0 0;font-size:12.5px;color:#6b7280;">Besoin d\'aide ? Répondez simplement à ce message.</p>';

    $html = _atlEmailLayout('Commande #' . $num . ' confirmée', $body,
        '<div>Conservez ce message — il vous servira de preuve d\'achat.</div>');

    return sendMailSMTP($to, '✅ Commande #' . ($order['order_number'] ?? '') . ' confirmée — AtlanTech', $html);
}

// ══════════════════════════════════════════════════════════════════
//  2) ALERTE ADMIN (à la création d'une nouvelle commande)
// ══════════════════════════════════════════════════════════════════
function sendOrderAlertToAdmin(array $order, array $items): bool {
    $to = ADMIN_NOTIFICATION_EMAIL;
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $num      = _atlEscape($order['order_number'] ?? '');
    $cname    = _atlEscape($order['customer_name'] ?? '—');
    $cemail   = _atlEscape($order['customer_email'] ?? '—');
    $cphone   = _atlEscape($order['customer_phone'] ?? '—');
    $addr     = _atlEscape($order['shipping_address'] ?? '—');
    $paymt    = _atlEscape($order['payment_method'] ?? '');
    $proc     = _atlEscape($order['payment_processor'] ?? '');
    $txid     = _atlEscape($order['payment_transaction_id'] ?? '');
    if ($proc && $proc !== $paymt) $paymt .= ' · ' . $proc;

    $subtotal = (float)($order['subtotal'] ?? 0);
    $ship     = (float)($order['shipping_cost'] ?? 0);
    $total    = (float)($order['total_amount'] ?? $subtotal + $ship);

    $itemsTable = _atlItemsTable($items, $subtotal, $ship, $total);
    $adminLink  = SHOP_URL_FOR_EMAIL . '/admins/order-admin/order-details.php?id=' . (int)($order['id'] ?? 0);

    $body = '<h2 style="margin:0 0 8px;color:#6d28d9;">🔔 Nouvelle commande reçue</h2>'
          . '<p style="margin:0 0 6px;color:#4b5563;">Commande <strong style="color:#6d28d9;">#' . $num . '</strong> — total <strong>' . _atlFormatHTG($total) . '</strong></p>'
          . '<a href="' . _atlEscape($adminLink) . '" style="display:inline-block;background:#8b5cf6;color:#fff !important;text-decoration:none;padding:10px 18px;border-radius:8px;font-weight:700;font-size:13px;margin:10px 0 16px;">👁 Ouvrir dans l\'admin</a>'
          . '<h3 style="margin:14px 0 6px;font-size:14px;color:#374151;">👤 Client</h3>'
          . '<table role="presentation" cellpadding="0" cellspacing="0" style="font-size:13px;"><tr><td style="color:#6b7280;padding:2px 16px 2px 0;">Nom</td><td>' . $cname . '</td></tr>'
          . '<tr><td style="color:#6b7280;padding:2px 16px 2px 0;">Email</td><td>' . $cemail . '</td></tr>'
          . '<tr><td style="color:#6b7280;padding:2px 16px 2px 0;">Téléphone</td><td>' . $cphone . '</td></tr></table>'
          . '<h3 style="margin:14px 0 6px;font-size:14px;color:#374151;">💳 Paiement</h3>'
          . '<p style="margin:4px 0;color:#4b5563;">' . $paymt . ($txid ? ' — réf : <code>' . $txid . '</code>' : '') . '</p>'
          . '<h3 style="margin:14px 0 6px;font-size:14px;color:#374151;">🏠 Adresse de livraison</h3>'
          . '<p style="margin:4px 0;color:#4b5563;">' . $addr . '</p>'
          . '<h3 style="margin:18px 0 6px;font-size:14px;color:#374151;">🛍️ Articles</h3>'
          . $itemsTable;

    $html = _atlEmailLayout('Nouvelle commande #' . $num, $body);

    return sendMailSMTP($to, '🔔 [AtlanTech] Nouvelle commande #' . ($order['order_number'] ?? ''), $html);
}

// ══════════════════════════════════════════════════════════════════
//  3) EMAIL DE CHANGEMENT DE STATUT (au client)
// ══════════════════════════════════════════════════════════════════
function sendOrderStatusEmailToCustomer(array $order, string $oldStatus, string $newStatus, string $adminNote = ''): bool {
    $to = trim($order['customer_email'] ?? '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $statusMeta = [
        'pending'    => ['icon' => '⏳', 'label' => 'En attente',      'color' => '#f59e0b',
                         'msg'   => 'Votre commande est enregistrée et en attente de paiement/validation.'],
        'paid'       => ['icon' => '✅', 'label' => 'Payée',            'color' => '#10b981',
                         'msg'   => 'Paiement confirmé ! Nous préparons votre commande.'],
        'processing' => ['icon' => '🔧', 'label' => 'En préparation',  'color' => '#06b6d4',
                         'msg'   => 'Vos produits sont en cours d\'emballage. Départ imminent !'],
        'ready_for_delivery' => ['icon' => '🎁', 'label' => 'Prête pour la livraison', 'color' => '#3b82f6',
                         'msg'   => 'Votre colis est emballé et prêt — un livreur va le prendre en charge sous peu.'],
        'shipped'    => ['icon' => '🚚', 'label' => 'Expédiée',        'color' => '#8b5cf6',
                         'msg'   => 'Bonne nouvelle : votre colis vient d\'être expédié.'],
        'delivered'  => ['icon' => '📦', 'label' => 'Livrée',           'color' => '#059669',
                         'msg'   => 'Votre commande a été livrée. Merci de votre confiance !'],
        'cancelled'  => ['icon' => '❌', 'label' => 'Annulée',          'color' => '#ef4444',
                         'msg'   => 'Votre commande a été annulée. Si c\'est une erreur, contactez-nous vite.'],
    ];
    $meta = $statusMeta[$newStatus] ?? ['icon' => '📝', 'label' => ucfirst($newStatus), 'color' => '#6b7280', 'msg' => ''];

    $num  = _atlEscape($order['order_number'] ?? '');
    $name = _atlEscape($order['customer_name'] ?? 'Cher client');
    $total = (float)($order['total_amount'] ?? 0);

    $noteBlock = '';
    if ($adminNote !== '') {
        $noteBlock = '<div style="background:#eff6ff;border-left:4px solid #3b82f6;padding:10px 14px;border-radius:0 8px 8px 0;margin:12px 0;font-size:13.5px;color:#1e3a8a;">'
                   . '<strong>Message de l\'équipe AtlanTech :</strong><br>' . nl2br(_atlEscape($adminNote)) . '</div>';
    }

    $body = '<h2 style="margin:0 0 8px;color:' . $meta['color'] . ';">' . $meta['icon'] . ' Commande ' . strtolower(_atlEscape($meta['label'])) . '</h2>'
          . '<p style="margin:0 0 12px;color:#4b5563;">Bonjour ' . $name . ',</p>'
          . '<p style="margin:0 0 12px;color:#4b5563;">Votre commande <strong style="color:#6d28d9;">#' . $num . '</strong> est maintenant : '
          . '<span style="background:' . $meta['color'] . ';color:#fff;padding:3px 10px;border-radius:20px;font-weight:700;font-size:12px;">' . $meta['icon'] . ' ' . _atlEscape($meta['label']) . '</span></p>'
          . '<p style="margin:12px 0;color:#374151;">' . _atlEscape($meta['msg']) . '</p>'
          . $noteBlock
          . '<p style="margin:18px 0 0;font-size:13px;color:#6b7280;">Total de la commande : <strong>' . _atlFormatHTG($total) . '</strong></p>'
          . '<p style="margin:6px 0 0;font-size:12.5px;color:#9ca3af;">Changement effectué le ' . date('d/m/Y à H:i') . '.</p>';

    $html = _atlEmailLayout('Commande #' . $num . ' — ' . $meta['label'], $body);

    return sendMailSMTP($to, $meta['icon'] . ' Commande #' . ($order['order_number'] ?? '') . ' : ' . $meta['label'], $html);
}

/**
 * Email de BIENVENUE à l'inscription : combine vérification email + code promo.
 * Récupère automatiquement le meilleur code de bienvenue actif en BD
 * (priorité : code 'BIENVENUE*', sinon premier code actif disponible).
 *
 * @param  string   $to            Email du nouveau client
 * @param  string   $name          Nom du nouveau client
 * @param  string   $verify_link   URL de vérification email
 * @param  mysqli   $mysqli        Connexion BD (pour lire le code actif)
 * @return bool
 */
function sendWelcomeEmail(string $to, string $name, string $verify_link, mysqli $mysqli): bool
{
    $to = trim($to);
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    // 1) Chercher un code de bienvenue actif (priorité aux codes contenant "BIENVENUE" ou "WELCOME")
    $promo = null;
    try {
        $today = date('Y-m-d');
        $st = $mysqli->prepare("
            SELECT code, description, discount_percent, valid_until
            FROM promo_codes
            WHERE is_active = 1
              AND (valid_from  IS NULL OR valid_from  <= ?)
              AND (valid_until IS NULL OR valid_until >= ?)
            ORDER BY
                CASE WHEN code LIKE 'BIENVENUE%' OR code LIKE 'WELCOME%' THEN 0 ELSE 1 END,
                discount_percent DESC
            LIMIT 1
        ");
        if ($st) {
            $st->bind_param('ss', $today, $today);
            $st->execute();
            $res = $st->get_result();
            $promo = $res ? $res->fetch_assoc() : null;
            $st->close();
        }
    } catch (\Throwable $e) {
        error_log('sendWelcomeEmail promo lookup: ' . $e->getMessage());
    }

    $safe_name  = _atlEscape($name ?: 'Cher client');
    $safe_link  = _atlEscape($verify_link);
    $shop_link  = _atlEscape(SHOP_URL_FOR_EMAIL);

    // Bloc code promo (si trouvé)
    $promo_block = '';
    if ($promo) {
        $safe_code   = _atlEscape($promo['code']);
        $safe_desc   = _atlEscape($promo['description'] ?? 'Réduction de bienvenue');
        $percent     = (int) round((float)$promo['discount_percent']);
        $valid_block = '';
        if (!empty($promo['valid_until'])) {
            $valid_block = '<p style="margin:8px 0 0;font-size:12.5px;color:#9ca3af;">Valable jusqu\'au ' . date('d/m/Y', strtotime($promo['valid_until'])) . '</p>';
        }
        $promo_block = '
        <div style="margin:24px 0;padding:24px 22px;background:linear-gradient(135deg,#6d28d9,#f59e0b);border-radius:14px;color:#fff;text-align:center;box-shadow:0 8px 20px rgba(109,40,217,0.25);">
            <p style="margin:0 0 8px;font-size:13px;letter-spacing:1px;text-transform:uppercase;opacity:0.9;font-weight:700;">🎁 Votre cadeau de bienvenue</p>
            <p style="margin:0 0 14px;font-size:15px;opacity:0.95;">' . $safe_desc . '</p>
            <div style="display:inline-block;background:rgba(0,0,0,0.25);padding:14px 28px;border-radius:10px;border:2px dashed rgba(255,255,255,0.5);">
                <p style="margin:0;font-family:Courier,monospace;font-size:24px;font-weight:800;letter-spacing:3px;">' . $safe_code . '</p>
                <p style="margin:6px 0 0;font-size:13px;opacity:0.9;">–' . $percent . '% sur votre première commande</p>
            </div>
            ' . $valid_block . '
        </div>';
    }

    $body = '<h2 style="margin:0 0 8px;color:#6d28d9;">🎉 Bienvenue chez AtlanTech, ' . $safe_name . ' !</h2>'
          . '<p style="margin:0 0 12px;color:#4b5563;">Votre compte vient d\'être créé. Merci de nous rejoindre !</p>'
          . $promo_block
          . '<h3 style="margin:24px 0 10px;color:#374151;font-size:16px;">📧 Confirmez votre email</h3>'
          . '<p style="margin:0 0 14px;color:#4b5563;font-size:14px;">Pour activer toutes les fonctionnalités (suivi de commandes, notifications), vérifiez votre adresse email :</p>'
          . '<p style="text-align:center;margin:18px 0;"><a href="' . $safe_link . '" style="display:inline-block;background:#6d28d9;color:#fff;padding:12px 28px;border-radius:8px;text-decoration:none;font-weight:700;">Vérifier mon email</a></p>'
          . '<p style="margin:14px 0 0;font-size:12.5px;color:#9ca3af;">Si le bouton ne fonctionne pas, copiez ce lien dans votre navigateur :<br><span style="word-break:break-all;color:#6d28d9;">' . $safe_link . '</span></p>'
          . '<p style="margin:24px 0 0;color:#4b5563;font-size:14px;">À très vite,<br><strong style="color:#6d28d9;">L\'équipe AtlanTech</strong></p>';

    $footer = 'Découvrez nos promotions : <a href="' . $shop_link . '/promotions.php" style="color:#6d28d9;text-decoration:none;font-weight:600;">' . $shop_link . '/promotions.php</a>';

    $html = _atlEmailLayout('Bienvenue chez AtlanTech', $body, $footer);

    return sendMailSMTP($to, '🎉 Bienvenue chez AtlanTech — Votre code cadeau à l\'intérieur !', $html);
}

/**
 * Email spécial "Paiement reçu à la livraison" — envoyé quand le livreur
 * confirme avoir reçu le paiement Cash en main propre lors de la remise
 * du colis. Distinct de l'email "Commande livrée" car cumule les deux
 * événements (paiement + livraison) en un seul message.
 *
 * @param  array  $order  Ligne orders (au moins customer_email, customer_name, order_number, total_amount)
 * @return bool
 */
function sendCashPaymentReceivedEmail(array $order): bool
{
    $to = trim($order['customer_email'] ?? '');
    if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $num   = _atlEscape($order['order_number'] ?? '');
    $name  = _atlEscape($order['customer_name'] ?? 'Cher client');
    $total = (float)($order['total_amount'] ?? 0);

    $body = '<h2 style="margin:0 0 8px;color:#059669;">💵 Paiement reçu — merci !</h2>'
          . '<p style="margin:0 0 12px;color:#4b5563;">Bonjour ' . $name . ',</p>'
          . '<p style="margin:0 0 12px;color:#4b5563;">Nous confirmons avoir bien reçu votre paiement <strong>en espèces</strong> '
          . 'lors de la livraison de votre commande <strong style="color:#6d28d9;">#' . $num . '</strong>.</p>'
          . '<div style="background:#ecfdf5;border-left:4px solid #10b981;padding:12px 16px;border-radius:0 8px 8px 0;margin:14px 0;">'
          . '<p style="margin:0 0 4px;color:#065f46;font-weight:700;">Montant payé</p>'
          . '<p style="margin:0;font-size:22px;font-weight:700;color:#047857;">' . _atlFormatHTG($total) . '</p>'
          . '</div>'
          . '<p style="margin:14px 0;color:#374151;">Votre commande est désormais marquée comme <strong>livrée et payée</strong>. '
          . 'Vous recevrez votre reçu sur demande au +509 4466-7553.</p>'
          . '<p style="margin:18px 0 0;font-size:13px;color:#6b7280;">Merci pour votre confiance — à très bientôt sur AtlanTech !</p>';

    $html = _atlEmailLayout('Paiement reçu — commande #' . $num, $body);

    return sendMailSMTP($to, '💵 Paiement reçu pour la commande #' . ($order['order_number'] ?? ''), $html);
}
