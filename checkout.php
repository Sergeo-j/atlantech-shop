<?php
/**
 * Checkout — AtlanTech E-commerce
 * Gère le paiement depuis le panier normal ou l'achat direct (buy_now)
 */

require_once 'config/config.php';
require_once 'includes/header_counters.php';
require_once __DIR__ . '/includes/checkout_security.php';
require_once __DIR__ . '/config/promo_codes.php';

// ── Rediriger si non connecté ────────────────────────────────────────
if (!isLoggedIn()) {
    redirect('account.php?redirect=checkout');
}

// ──────────────────────────────────────────────────────────────────────
// ── Endpoint AJAX : validation d'un code promo (avant la soumission)
// ──────────────────────────────────────────────────────────────────────
//   GET ?action=validate_promo&code=XXX&subtotal=YYY
//   Renvoie un JSON {valid, error, discount_percent, discount_amount, ...}
if (isset($_GET['action']) && $_GET['action'] === 'validate_promo') {
    header('Content-Type: application/json');
    $code     = $_GET['code']     ?? '';
    $subtotal = (float) ($_GET['subtotal'] ?? 0);
    if ($subtotal <= 0) {
        // Si pas de sous-total fourni, recalculer depuis la session (sécurité)
        $cart = $_SESSION['cart'] ?? [];
        $subtotal = 0.0;
        foreach ($cart as $item) {
            $subtotal += (float)$item['price'] * (int)$item['qty'];
        }
    }
    echo json_encode(promo_validate_mysqli($mysqli, (string)$code, $subtotal));
    exit;
}

// ── Token CSRF (toujours disponible côté vue) ────────────────────────
$csrf_token = checkout_csrf_token();

// ── Clé d'idempotence (générée 1× par chargement de la page) ─────────
if (empty($_SESSION['_checkout_idem']) || $_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['_checkout_idem'] = bin2hex(random_bytes(16));
}
$client_idempotency_key = $_SESSION['_checkout_idem'];

// ── Mode : panier normal ou achat direct ─────────────────────────────
$buy_now_requested = ($_GET['mode'] ?? '') === 'buy_now';
$mode     = $buy_now_requested ? 'buy_now' : 'cart';
$buy_now  = $_SESSION['buy_now'] ?? null;

// Si "achat direct" a été demandé (mode=buy_now dans l'URL) mais que la
// session ne contient plus l'article (session expirée, onglet resté ouvert
// trop longtemps, nouvelle connexion entre-temps...), on NE BASCULE JAMAIS
// silencieusement sur le panier normal : le client verrait un total/produit
// différent de ce qu'il a choisi, sans le savoir. On affiche un message
// clair à la place (voir $buy_now_expired plus bas dans le template).
$buy_now_expired = $buy_now_requested && empty($buy_now);

// Construire la liste des articles selon le mode
$items   = [];
$subtotal = 0.0;

if ($buy_now_expired) {
    // Rien à construire : le template affichera l'écran "sélection expirée".
} elseif ($mode === 'buy_now' && !empty($buy_now)) {
    $items[] = [
        'id'         => $buy_now['product_id'],
        'name'       => $buy_now['name'],
        'price'      => $buy_now['price'],
        'image'      => $buy_now['image'],
        'qty'        => $buy_now['qty'],
        'total'      => $buy_now['total'],
        'color_id'   => $buy_now['color_id']   ?? null,
        'color_name' => $buy_now['color_name'] ?? null,
    ];
    $subtotal = (float)$buy_now['total'];
} else {
    $mode = 'cart';
    $cart = $_SESSION['cart'] ?? [];
    if (empty($cart)) {
        redirect('cart.php');
    }
    foreach ($cart as $item) {
        $line = [
            'id'         => $item['id'],
            'name'       => $item['name'],
            'price'      => $item['price'],
            'image'      => $item['image'],
            'qty'        => $item['qty'],
            'total'      => $item['price'] * $item['qty'],
            'color_id'   => $item['color_id']   ?? null,
            'color_name' => $item['color_name'] ?? null,
        ];
        $items[]   = $line;
        $subtotal += $line['total'];
    }
}

if (empty($items) && !$buy_now_expired) {
    redirect('cart.php');
}

// ── Pré-charger le stock disponible pour chaque article ─────────────
//    Sert à alimenter le max= des boutons +/- côté client.
//    (Le serveur revalide quand même au POST, voir phases 6 et 7.)
$stock_map = [];
$ids_for_stock = array_filter(array_map(fn($i) => (int)$i['id'], $items));
if (!empty($ids_for_stock)) {
    $in = implode(',', array_map('intval', $ids_for_stock));
    try {
        $r = $mysqli->query("SELECT id, stock, min_order_qty, max_order_qty FROM products WHERE id IN ($in)");
        if ($r) {
            while ($row = $r->fetch_assoc()) {
                $stock_map[(int)$row['id']] = [
                    'stock'   => (int)$row['stock'],
                    'min_qty' => max(1, (int)$row['min_order_qty']),
                    'max_qty' => $row['max_order_qty'] !== null ? (int)$row['max_order_qty'] : (int)$row['stock'],
                ];
            }
            $r->close();
        }
    } catch (\Throwable $e) {
        error_log('checkout stock prefetch: ' . $e->getMessage());
    }
}

// ─────────────────────────────────────────────────────────────────────
// ── Frais de livraison : lus dynamiquement depuis `shipping_rates` ──
// ─────────────────────────────────────────────────────────────────────
// 1) Charger la liste des villes actives + leur tarif (utilisée par le <select>
//    ET pour le calcul serveur du frais quand le client a choisi une ville).
$shipping_rates_map = [];   // ['Port-au-Prince' => 200.00, ...]
$shipping_cities    = [];   // [['city'=>..., 'department'=>..., 'price'=>...], ...]
try {
    $rs = $mysqli->query("SELECT city, department, price_htg FROM shipping_rates WHERE is_active = 1 ORDER BY department, city");
    if ($rs) {
        while ($row = $rs->fetch_assoc()) {
            $shipping_cities[]                 = $row;
            $shipping_rates_map[$row['city']]  = (float) $row['price_htg'];
        }
        $rs->close();
    }
} catch (\Throwable $e) {
    error_log('checkout shipping_rates load: ' . $e->getMessage());
}

// 2) Helper : prix de livraison pour une ville (0 si ville inconnue ou absente)
function checkout_resolve_shipping_cost(array $rates_map, string $city): float {
    $city = trim($city);
    if ($city === '') return 0.0;
    if (isset($rates_map[$city])) return (float) $rates_map[$city];
    // Recherche insensible à la casse (filets de sécurité)
    foreach ($rates_map as $name => $price) {
        if (mb_strtolower($name) === mb_strtolower($city)) return (float) $price;
    }
    return 0.0;
}

// 3) Frais initial : utilise la ville déjà soumise (POST) sinon 0
//    (le total final sera recalculé au POST + dans le JS)
$selected_city_initial = $_POST['city'] ?? '';
$shipping_cost         = checkout_resolve_shipping_cost($shipping_rates_map, $selected_city_initial);
$total                 = $subtotal + $shipping_cost;

// ── Infos utilisateur connecté ───────────────────────────────────────
$user_id         = (int)$_SESSION['user_id'];
$user_name       = $_SESSION['user_name'] ?? '';
$user_first_name = $user_name ? explode(' ', $user_name)[0] : '';
$user_email      = $_SESSION['user_email'] ?? '';
$user_phone      = $_SESSION['user_phone'] ?? '';

// ── Catégories pour le header ────────────────────────────────────────
try {
    $r = $mysqli->query("SELECT id, name, slug, icon FROM categories WHERE parent_id IS NULL AND is_active = 1 ORDER BY display_order ASC, name ASC LIMIT 6");
    $rootCategories = $r ? $r->fetch_all(MYSQLI_ASSOC) : [];
} catch (Exception $e) { $rootCategories = []; }

// ════════════════════════════════════════════════════════════════════
// TRAITEMENT DU FORMULAIRE (POST) — flux durci en phases
// ════════════════════════════════════════════════════════════════════
//
// Phases successives. Dès qu'une phase remplit $errors, les suivantes
// sont court-circuitées. Chaque échec est journalisé dans checkout_attempts.
//
//   1. CSRF              ← contre la soumission inter-site
//   2. Rate limiting     ← contre le spam
//   3. Idempotence       ← contre le double-clic
//   4. Validation champs ← email, téléphone, ville (whitelist), notes
//   5. Validation paiement ← méthode + ref + banque
//   6. Re-fetch produits ← anti-tampering : on prend la vérité de la BD
//   7. Vérification stock
//   8. Transaction DB    ← orders + items + history + décrément stock
//   9. Emails            ← hors transaction
// ════════════════════════════════════════════════════════════════════

$errors        = [];
$order_success = false;
$order_number  = '';
$insert_ok     = false;
$order_id_new  = 0;
$moncash_error = '';   // avertissement si la passerelle MonCash échoue (commande quand même créée)

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$buy_now_expired) {

    $customer_ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent  = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

    // ╔═══════════════════════════════════════════════════════════════╗
    // ║ PHASE 1 — CSRF                                                ║
    // ╚═══════════════════════════════════════════════════════════════╝
    if (!checkout_csrf_verify($_POST['csrf_token'] ?? '')) {
        $errors[] = "Votre session a expiré. Veuillez recharger la page et réessayer.";
        checkout_log_attempt($mysqli, $user_id, 'csrf_fail', '', 0.0, null,
            'CSRF mismatch', $customer_ip, $user_agent);
    }

    // ╔═══════════════════════════════════════════════════════════════╗
    // ║ PHASE 2 — Rate limiting                                       ║
    // ╚═══════════════════════════════════════════════════════════════╝
    if (empty($errors) && checkout_rate_limited()) {
        $errors[] = "Trop de tentatives en peu de temps. Veuillez patienter une minute.";
        checkout_log_attempt($mysqli, $user_id, 'rate_limited', '', 0.0, null,
            'Too many attempts', $customer_ip, $user_agent);
    }

    // ╔═══════════════════════════════════════════════════════════════╗
    // ║ PHASE 3 — Idempotence (double-clic / refresh)                 ║
    // ╚═══════════════════════════════════════════════════════════════╝
    $idempotency_key = checkout_idempotency_key($_POST['idempotency_key'] ?? null);
    if (empty($errors)) {
        $dup = checkout_find_by_idempotency($mysqli, $user_id, $idempotency_key);
        if ($dup) {
            // Commande déjà créée — on affiche le succès sans dupliquer
            $order_success = true;
            $order_number  = $dup['order_number'];
            $order_id_new  = (int)$dup['id'];
            checkout_log_attempt($mysqli, $user_id, 'duplicate', '', 0.0, $order_number,
                'Idempotent retry', $customer_ip, $user_agent);
            // Nettoie le panier au cas où
            if ($mode === 'buy_now') unset($_SESSION['buy_now']);
            else $_SESSION['cart'] = [];
        }
    }

    // Variables qui seront utilisées par les phases suivantes
    $customer_name   = trim($_POST['customer_name']   ?? '');
    $customer_email  = trim($_POST['customer_email']  ?? '');
    $customer_phone_raw = trim($_POST['customer_phone'] ?? '');
    $customer_phone  = $customer_phone_raw; // sera normalisé en phase 4
    $address_line    = trim($_POST['address_line']    ?? '');
    $city            = trim($_POST['city']            ?? '');
    $payment_method  = trim($_POST['payment_method']  ?? '');
    $transaction_id  = trim($_POST['transaction_id']  ?? '');
    $bank_name       = trim($_POST['bank_name']       ?? '');
    $notes_client    = trim($_POST['notes_client']    ?? '');

    // ── Quantités modifiées par le client via les boutons +/- ─────────
    //    Le serveur revalide stock/min/max via les phases 6 et 7.
    if (!$order_success && !empty($_POST['qty_override']) && is_array($_POST['qty_override'])) {
        foreach ($items as $k => $it) {
            $pid = (int)($it['id'] ?? 0);
            if (isset($_POST['qty_override'][$pid])) {
                $new_qty = max(1, (int)$_POST['qty_override'][$pid]);
                $items[$k]['qty']   = $new_qty;
                $items[$k]['total'] = (float)$it['price'] * $new_qty;
            }
        }
        // Recalcul provisoire (sera écrasé par la phase 6/7 avec prix BD)
        $subtotal = (float)array_sum(array_column($items, 'total'));

        // Synchroniser aussi la session selon le mode pour cohérence en cas
        // d'erreur de validation et rechargement de la page.
        if ($mode === 'cart' && !empty($_SESSION['cart'])) {
            foreach ($_SESSION['cart'] as $k => $cit) {
                $cpid = (int)($cit['id'] ?? 0);
                if (isset($_POST['qty_override'][$cpid])) {
                    $_SESSION['cart'][$k]['qty'] = max(1, (int)$_POST['qty_override'][$cpid]);
                }
            }
        } elseif ($mode === 'buy_now' && !empty($_SESSION['buy_now'])) {
            $bpid = (int)$_SESSION['buy_now']['product_id'];
            if (isset($_POST['qty_override'][$bpid])) {
                $new_q = max(1, (int)$_POST['qty_override'][$bpid]);
                $_SESSION['buy_now']['qty']   = $new_q;
                $_SESSION['buy_now']['total'] = (float)$_SESSION['buy_now']['price'] * $new_q;
            }
        }
    }

    // ╔═══════════════════════════════════════════════════════════════╗
    // ║ PHASE 4 — Validation des champs personnels                    ║
    // ╚═══════════════════════════════════════════════════════════════╝
    if (!$order_success && empty($errors)) {
        if (!v_text($customer_name, 2, 100))   $errors[] = 'Nom complet invalide (2-100 caractères).';
        if (!v_email($customer_email))         $errors[] = 'Adresse email invalide.';

        $normalized_phone = v_phone_ht($customer_phone_raw);
        if ($normalized_phone === null) {
            $errors[] = 'Numéro de téléphone haïtien invalide (ex. +509 4466-7553).';
        } else {
            $customer_phone = $normalized_phone;
        }

        if (!v_text($address_line, 5, 500))    $errors[] = 'Adresse de livraison invalide (5-500 caractères).';
        if (!in_array($city, checkout_cities_whitelist(), true)) {
            $errors[] = 'Veuillez choisir une ville dans la liste.';
        }
        if (mb_strlen($notes_client) > 1000)   $errors[] = 'Note trop longue (max 1000 caractères).';

        // ╔═══════════════════════════════════════════════════════════╗
        // ║ PHASE 5 — Validation paiement                             ║
        // ╚═══════════════════════════════════════════════════════════╝
        $payment_errors = v_payment_payload($_POST);
        if (!empty($payment_errors)) {
            $errors = array_merge($errors, $payment_errors);
        }

        if (!empty($errors)) {
            checkout_log_attempt($mysqli, $user_id, 'validation_fail',
                $payment_method, 0.0, null,
                implode(' | ', $errors), $customer_ip, $user_agent);
        }
    }

    // ╔═══════════════════════════════════════════════════════════════╗
    // ║ PHASE 6 — Re-fetch produits depuis la BD (anti-tampering)     ║
    // ╚═══════════════════════════════════════════════════════════════╝
    if (!$order_success && empty($errors)) {
        $items_db = checkout_recompute_items($mysqli, $items);

        // S'assurer qu'on a bien retrouvé tous les produits demandés
        $requested_ids = array_unique(array_map(fn($i) => (int)$i['id'], $items));
        $found_ids     = array_map(fn($i) => (int)$i['id'], $items_db);
        $missing       = array_diff($requested_ids, $found_ids);
        if (!empty($missing)) {
            $errors[] = "Un ou plusieurs produits de votre panier ne sont plus disponibles. "
                      . "Veuillez retourner au panier.";
        }
    }

    // ╔═══════════════════════════════════════════════════════════════╗
    // ║ PHASE 7 — Vérification du stock                               ║
    // ╚═══════════════════════════════════════════════════════════════╝
    if (!$order_success && empty($errors)) {
        $stock_errors = checkout_check_stock($items_db);
        if (!empty($stock_errors)) {
            $errors = array_merge($errors, $stock_errors);
            checkout_log_attempt($mysqli, $user_id, 'stock_fail',
                $payment_method, 0.0, null,
                implode(' | ', $stock_errors), $customer_ip, $user_agent);
        }
    }

    // Re-calculer les totaux côté serveur (jamais faire confiance au client)
    if (!$order_success && empty($errors)) {
        $items         = $items_db; // on prend la version BD pour la suite
        $subtotal      = (float)array_sum(array_column($items, 'total'));
        // Frais de livraison : selon la ville sélectionnée dans la table shipping_rates
        $shipping_cost = checkout_resolve_shipping_cost($shipping_rates_map, $_POST['city'] ?? '');

        // Code promo : revalider côté serveur (le client peut avoir fait n'importe quoi en DOM)
        $coupon_code     = '';
        $coupon_discount = 0.0;
        $promo_id_used   = 0;
        $raw_code        = trim($_POST['promo_code'] ?? '');
        if ($raw_code !== '') {
            $promo = promo_validate_mysqli($mysqli, $raw_code, $subtotal);
            if ($promo['valid']) {
                $coupon_code     = $promo['code'];
                $coupon_discount = (float) $promo['discount_amount'];
                $promo_id_used   = (int) $promo['promo_id'];
            } else {
                // Code invalide → on n'applique pas, on signale à l'utilisateur
                $errors[] = "Code promo refusé : " . ($promo['error'] ?? 'invalide');
            }
        }

        $total = max(0.0, $subtotal - $coupon_discount) + $shipping_cost;
    }

    // ╔═══════════════════════════════════════════════════════════════╗
    // ║ PHASE 8 — Transaction DB                                      ║
    // ╚═══════════════════════════════════════════════════════════════╝
    if (!$order_success && empty($errors)) {

        $shipping_address = $address_line . ', ' . $city . ', Haïti';
        $order_number     = checkout_generate_order_number();

        $items_json    = json_encode($items, JSON_UNESCAPED_UNICODE);
        $internal_note = 'Mode: ' . $mode . ' | Articles: ' . $items_json;
        if (!empty($notes_client)) {
            $internal_note .= ' | Note client: ' . $notes_client;
        }

        $pm_map           = checkout_payment_map();
        $db_payment_method = $pm_map[$payment_method];
        $processor         = $payment_method;
        if ($payment_method === 'Bank' && !empty($bank_name)) {
            $processor = $bank_name;
        }
        $transaction_ref = $transaction_id;
        if ($payment_method === 'Bank' && !empty($bank_name)) {
            $transaction_ref = $bank_name . ($transaction_id ? ' — ' . $transaction_id : '');
        }

        $stmt = null;

        try {
            $mysqli->begin_transaction();

            // ── 8a. INSERT dynamique dans orders ──────────────────────
            //    Filtrage des colonnes selon ce qui existe en BD (robuste
            //    aux BD plus vieilles que le code).
            $existing_cols = [];
            if ($r = $mysqli->query("SHOW COLUMNS FROM orders")) {
                while ($row = $r->fetch_assoc()) $existing_cols[$row['Field']] = true;
                $r->close();
            }
            if (empty($existing_cols)) {
                throw new Exception("Impossible de lire la structure de la table 'orders'");
            }

            $candidates = [
                'order_number'           => ['s', $order_number],
                'idempotency_key'        => ['s', $idempotency_key],
                'user_id'                => ['i', $user_id],
                'shipping_address'       => ['s', $shipping_address],
                'subtotal'               => ['d', $subtotal],
                'shipping_cost'          => ['d', $shipping_cost],
                'total_amount'           => ['d', $total],
                'payment_method'         => ['s', $db_payment_method],
                'payment_transaction_id' => ['s', $transaction_ref],
                'payment_processor'      => ['s', $processor],
                'status'                 => ['s', 'pending'],
                'customer_name'          => ['s', $customer_name],
                'customer_email'         => ['s', $customer_email],
                'customer_phone'         => ['s', $customer_phone],
                'notes'                  => ['s', $notes_client],
                'internal_notes'         => ['s', $internal_note],
                'customer_ip'            => ['s', $customer_ip],
                'user_agent'             => ['s', $user_agent],
                // Code promo (insérés seulement si les colonnes existent — orders les a)
                'coupon_code'            => ['s', $coupon_code     ?? ''],
                'coupon_discount'        => ['d', $coupon_discount ?? 0.0],
                'discount_amount'        => ['d', $coupon_discount ?? 0.0],
            ];

            $cols_to_insert = [];
            $types          = '';
            $values         = [];
            foreach ($candidates as $col => [$type, $val]) {
                if (isset($existing_cols[$col])) {
                    $cols_to_insert[] = $col;
                    $types           .= $type;
                    $values[]         = $val;
                }
            }

            $col_list     = '`' . implode('`, `', $cols_to_insert) . '`';
            $placeholders = rtrim(str_repeat('?, ', count($cols_to_insert)), ', ');
            $sql_insert   = "INSERT INTO orders ($col_list) VALUES ($placeholders)";

            $stmt = $mysqli->prepare($sql_insert);
            if (!$stmt) throw new Exception('Échec prepare INSERT orders : ' . $mysqli->error);
            $stmt->bind_param($types, ...$values);
            $stmt->execute();
            $order_id_new = $mysqli->insert_id;

            // ── 8b. INSERT order_items (try/catch isolé) ──────────────
            if ($order_id_new > 0 && !empty($items)) {
                try {
                    $si = $mysqli->prepare(
                        "INSERT INTO order_items
                            (order_id, product_id, product_name, product_image, color, quantity, unit_price, total_price)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
                    );
                    if ($si) {
                        foreach ($items as $item) {
                            $pid   = (int)$item['id'];
                            $pname = (string)$item['name'];
                            $pimg  = (string)$item['image'];
                            $pcol  = $item['color_name'] !== null ? (string)$item['color_name'] : null;
                            $qty   = (int)$item['qty'];
                            $up    = (float)$item['price'];
                            $tp    = (float)$item['total'];
                            $si->bind_param('iisssidd', $order_id_new, $pid, $pname, $pimg, $pcol, $qty, $up, $tp);
                            $si->execute();
                        }
                        $si->close();
                    }
                } catch (\Throwable $eItems) {
                    error_log('order_items insert (full) skipped: ' . $eItems->getMessage());
                    // Fallback minimal
                    try {
                        $si2 = $mysqli->prepare(
                            "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)"
                        );
                        if ($si2) {
                            foreach ($items as $item) {
                                $pid = (int)$item['id'];
                                $qty = (int)$item['qty'];
                                $up  = (float)$item['price'];
                                $si2->bind_param('iiid', $order_id_new, $pid, $qty, $up);
                                $si2->execute();
                            }
                            $si2->close();
                        }
                    } catch (\Throwable $e2) {
                        error_log('order_items insert (fallback) skipped: ' . $e2->getMessage());
                    }
                }
            }

            // ── 8c. INSERT order_status_history (try/catch isolé) ─────
            if ($order_id_new > 0) {
                try {
                    $hist_note = 'Commande créée — mode ' . $mode . ' · paiement ' . $db_payment_method;
                    $sh = $mysqli->prepare(
                        "INSERT INTO order_status_history
                            (order_id, old_status, new_status, changed_by_type, changed_by_id, changed_by_name, note, ip_address)
                         VALUES (?, NULL, 'pending', 'customer', ?, ?, ?, ?)"
                    );
                    if ($sh) {
                        $sh->bind_param('iisss', $order_id_new, $user_id, $customer_name, $hist_note, $customer_ip);
                        $sh->execute();
                        $sh->close();
                    }
                } catch (\Throwable $eh) {
                    error_log('order_status_history insert skipped: ' . $eh->getMessage());
                }
            }

            // ── 8d. Décrément du stock (atomique, dans la transaction) ─
            try {
                checkout_decrement_stock($mysqli, $items);
            } catch (\Throwable $eStock) {
                error_log('stock decrement skipped: ' . $eStock->getMessage());
            }

            $mysqli->commit();
            $insert_ok     = true;
            $order_success = true;

            // Incrémenter le compteur d'utilisation du code promo (si appliqué)
            if (!empty($promo_id_used) && $promo_id_used > 0) {
                promo_increment_usage_mysqli($mysqli, $promo_id_used);
            }

            // Vider le panier/buy_now
            if ($mode === 'buy_now') unset($_SESSION['buy_now']);
            else $_SESSION['cart'] = [];

            // Régénérer la clé d'idempotence pour la prochaine commande
            $_SESSION['_checkout_idem'] = bin2hex(random_bytes(16));

            checkout_log_attempt($mysqli, $user_id, 'success',
                $payment_method, $total, $order_number,
                null, $customer_ip, $user_agent);

        } catch (Throwable $e) {
            try { $mysqli->rollback(); } catch (\Throwable $_) {}
            $err_msg = $e->getMessage()
                     . ' [code=' . $e->getCode() . ']'
                     . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')';
            error_log('Checkout INSERT error: ' . $err_msg);
            checkout_log_attempt($mysqli, $user_id, 'sql_error',
                $payment_method, $total, null,
                $err_msg, $customer_ip, $user_agent);

            if (env('APP_ENV', 'production') === 'development') {
                $errors[] = 'Erreur SQL : ' . $err_msg;
            } else {
                $errors[] = "Désolé, une erreur s'est produite lors de la validation de votre commande. "
                          . "Veuillez réessayer dans quelques instants ou nous contacter au +509 4466-7553.";
            }
        }
        if ($stmt instanceof mysqli_stmt) { $stmt->close(); }
    }

    // ╔═══════════════════════════════════════════════════════════════╗
    // ║ PHASE 9 — Emails (hors transaction, ne bloquent jamais)       ║
    // ╚═══════════════════════════════════════════════════════════════╝
    if ($insert_ok && $order_id_new > 0) {
        try {
            require_once __DIR__ . '/config/order_emails.php';
            $orderRow = [
                'id'                     => $order_id_new,
                'order_number'           => $order_number,
                'customer_name'          => $customer_name,
                'customer_email'         => $customer_email,
                'customer_phone'         => $customer_phone,
                'shipping_address'       => $shipping_address,
                'payment_method'         => $db_payment_method,
                'payment_processor'      => $processor,
                'payment_transaction_id' => $transaction_ref,
                'subtotal'               => $subtotal,
                'shipping_cost'          => $shipping_cost,
                'total_amount'           => $total,
            ];
            @sendOrderConfirmationToCustomer($orderRow, $items);
            @sendOrderAlertToAdmin($orderRow, $items);
        } catch (\Throwable $e) {
            error_log('Checkout email error: ' . $e->getMessage());
        }
    }

    // ╔═══════════════════════════════════════════════════════════════╗
    // ║ PHASE 10 — Passerelle MonCash (redirection)                   ║
    // ╚═══════════════════════════════════════════════════════════════╝
    // Si le client a choisi MonCash, on crée le paiement via l'API et on
    // le redirige vers la passerelle. La commande est déjà enregistrée
    // (pending) ; l'ID de transaction sera rempli au retour (moncash-return.php).
    if ($insert_ok && $order_id_new > 0 && $payment_method === 'MonCash') {
        require_once __DIR__ . '/config/moncash.php';
        try {
            $pay = moncash_create_payment((float)$total, (string)$order_number);
            if ($pay['ok'] && !empty($pay['payment_token'])) {
                // Mémoriser la commande en attente de paiement pour la page retour
                $_SESSION['moncash_pending'] = [
                    'order_id'     => $order_id_new,
                    'order_number' => $order_number,
                ];
                header('Location: ' . moncash_gateway_url($pay['payment_token']));
                exit();
            }
            // Échec de création du paiement : la commande reste enregistrée (pending)
            error_log('MonCash create_payment KO pour ' . $order_number . ' : ' . ($pay['raw'] ?? ''));
            $moncash_error = "Connexion à MonCash impossible pour le moment. Votre commande "
                           . "#$order_number est bien enregistrée. Notre équipe vous contactera "
                           . "pour finaliser le paiement, ou réessayez plus tard.";
        } catch (\Throwable $e) {
            error_log('MonCash exception: ' . $e->getMessage());
            $moncash_error = "Erreur lors de la connexion à MonCash. Votre commande "
                           . "#$order_number est enregistrée — nous vous contacterons.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta http-equiv="x-ua-compatible" content="ie=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>Commander — AtlanTech</title>
  <link rel="shortcut icon" href="assets/img/favicon.png" type="image/x-icon">
  <!-- Preconnect Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <!-- CSS bundle (9 fichiers → 1 requête) -->
  <link rel="stylesheet" href="assets/css/bundle.min.css" />
  <link rel="stylesheet" href="assets/css/mobile.css?v=<?php echo filemtime(__DIR__.'/assets/css/mobile.css'); ?>" />
  <!-- Google Fonts non-bloquant -->
  <link rel="preload" href="https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'" />
  <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Dosis:wght@300;400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" /></noscript>
  <style>
    /* ── Compte dropdown ─────────────────────────── */
    .account-menu { position: relative; }
    .account-trigger { background: none; border: none; cursor: pointer; display: flex; align-items: center; gap: .5rem; color: #fff; font-size: .85rem; }
    .account-trigger img { width: 22px; height: 22px; }
    .account-trigger .labels { display: flex; flex-direction: column; line-height: 1.1; text-align: left; }
    .account-trigger .hello { font-size: .75rem; }
    .account-trigger .acct { font-weight: 600; }
    .account-dropdown { position: absolute; top: 100%; right: 0; background: #fff; border: 1px solid #ddd; border-radius: 4px; box-shadow: 0 5px 20px rgba(0,0,0,0.15); width: 500px; padding: 1rem; display: none; z-index: 1000; }
    .account-menu.open .account-dropdown { display: block; }
    .dropdown-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 5px solid #eee; padding-bottom: .5rem; margin-bottom: .75rem; font-size: 1rem; }
    .dropdown-header .blue { color: #ff9100; text-decoration: none; }
    .dropdown-header .blue:hover { text-decoration: underline; }
    .menu-columns { display: flex; gap: 1rem; }
    .menu-group { width: 50%; }
    .menu-group h4 { font-size: .85rem; font-weight: 700; margin-bottom: .25rem; }
    .menu-group ul { list-style: none; padding: 0; margin: 0; }
    .menu-group li { margin: .25rem 0; }
    .menu-group a { color: #111; text-decoration: none; font-size: .8rem; }
    .slide-bar__header { display: flex; align-items: center; justify-content: space-between; padding: 0 0 18px; margin-bottom: 18px; border-bottom: 1px solid #f0f0f0; }
    .slide-bar__logo img { max-height: 36px; }
    .mobile-logo { display: none; }
    .mobile-logo img { max-height: 32px; }
    .mobile-icons { display: none; gap: 10px; align-items: center; }
    .mobile-icon-btn { position: relative; display: flex; align-items: center; }
    .mobile-icon-btn img { width: 22px; height: 22px; filter: brightness(0) invert(1); }

    /* ── Checkout layout ─────────────────────────── */
    .checkout-section { padding: 50px 0 80px; background: #f7f7f7; }
    .checkout-wrap { display: grid; grid-template-columns: 1fr 400px; gap: 30px; align-items: start; }
    @media (max-width: 991px) { .checkout-wrap { grid-template-columns: 1fr; } }

    .checkout-card { background: #fff; border-radius: 8px; padding: 30px; box-shadow: 0 2px 15px rgba(0,0,0,.06); }
    .checkout-card h3 { font-size: 1.15rem; font-weight: 700; color: #222; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #f0f0f0; }

    /* Form */
    .form-group-checkout { margin-bottom: 18px; }
    .form-group-checkout label { display: block; font-size: .85rem; font-weight: 600; color: #555; margin-bottom: 6px; }
    .form-group-checkout input,
    .form-group-checkout select,
    .form-group-checkout textarea { width: 100%; padding: 10px 14px; border: 1px solid #ddd; border-radius: 6px; font-size: .95rem; color: #333; transition: border-color .2s; }
    .form-group-checkout input:focus,
    .form-group-checkout select:focus,
    .form-group-checkout textarea:focus { border-color: #ff9100; outline: none; box-shadow: 0 0 0 3px rgba(255,145,0,.12); }
    .form-row-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 600px) { .form-row-2 { grid-template-columns: 1fr; } }

    /* Payment methods */
    .payment-options { display: flex; flex-direction: column; gap: 10px; }
    .payment-option { display: flex; align-items: center; gap: 12px; padding: 12px 16px; border: 2px solid #e8e8e8; border-radius: 8px; cursor: pointer; transition: all .2s; }
    .payment-option:has(input:checked) { border-color: #ff9100; background: #fff8f0; }
    .payment-option input[type="radio"] { accent-color: #ff9100; width: 18px; height: 18px; }
    .payment-option .pm-label { font-weight: 600; font-size: .95rem; }
    .payment-option .pm-desc { font-size: .8rem; color: #888; }
    .payment-option .pm-icon { font-size: 1.4rem; width: 32px; text-align: center; }

    /* Transaction ID / Bank select / USDT */
    .transaction-field { display: none; margin-top: 14px; }
    .transaction-field.visible { display: block; }
    #bank-select-field select { width:100%; padding:10px 14px; border:1px solid #ddd; border-radius:6px; font-size:.95rem; }
    #bank-select-field select:focus { border-color:#ff9100; outline:none; }
    .payment-option .pm-icon { display:flex; align-items:center; justify-content:center; width:36px; flex-shrink:0; }

    /* Order summary */
    .order-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; border-bottom: 1px solid #f0f0f0; }
    .order-item:last-child { border-bottom: none; }
    .order-item img { width: 56px; height: 56px; object-fit: contain; border-radius: 6px; border: 1px solid #eee; }
    .order-item-info { flex: 1; }
    .order-item-name { font-size: .9rem; font-weight: 600; color: #333; margin-bottom: 6px; }
    .order-item-qty { font-size: .8rem; color: #888; }
    .order-item-price { font-weight: 700; color: #222; white-space: nowrap; }

    /* Quantity control */
    .qty-control { display: inline-flex; align-items: center; gap: 0; border: 1px solid #ddd; border-radius: 6px; overflow: hidden; background: #fff; }
    .qty-btn { width: 26px; height: 26px; border: none; background: #f7f7f7; cursor: pointer; font-size: 1rem; line-height: 1; color: #333; padding: 0; user-select: none; transition: background .15s; display: inline-flex; align-items: center; justify-content: center; }
    .qty-btn:hover:not(:disabled) { background: #ff9100; color: #fff; }
    .qty-btn:disabled { opacity: .4; cursor: not-allowed; }
    .qty-input { width: 38px; height: 26px; border: none; border-left: 1px solid #ddd; border-right: 1px solid #ddd; text-align: center; font-size: .85rem; font-weight: 600; color: #333; background: #fff; -moz-appearance: textfield; }
    .qty-input::-webkit-outer-spin-button,
    .qty-input::-webkit-inner-spin-button { -webkit-appearance: none; margin: 0; }
    .qty-stock-note { font-size: .7rem; color: #888; margin-top: 4px; }

    .summary-totals { margin-top: 16px; }
    .summary-row { display: flex; justify-content: space-between; align-items: center; padding: 8px 0; font-size: .95rem; color: #555; }
    .summary-row.total { font-size: 1.1rem; font-weight: 700; color: #111; border-top: 2px solid #f0f0f0; padding-top: 14px; margin-top: 4px; }
    .shipping-free { color: #27ae60; font-weight: 600; }

    /* Submit button */
    .btn-checkout { width: 100%; padding: 14px; background: #ff9100; color: #fff; border: none; border-radius: 8px; font-size: 1.05rem; font-weight: 700; cursor: pointer; transition: background .2s; margin-top: 20px; }
    .btn-checkout:hover { background: #e07d00; }

    /* Security badges */
    .security-badges { display: flex; gap: 16px; margin-top: 16px; flex-wrap: wrap; }
    .security-badge { display: flex; align-items: center; gap: 6px; font-size: .78rem; color: #777; }
    .security-badge i { color: #27ae60; }

    /* Errors */
    .checkout-errors { background: #fff3f3; border: 1px solid #f5c6c6; border-radius: 8px; padding: 14px 18px; margin-bottom: 24px; }
    .checkout-errors p { margin: 0 0 4px; color: #c0392b; font-size: .9rem; }
    .checkout-errors p:last-child { margin: 0; }

    /* Success */
    .order-success { text-align: center; padding: 60px 30px; }
    .order-success .success-icon { font-size: 4rem; color: #27ae60; margin-bottom: 20px; }
    .order-success h2 { font-size: 1.6rem; font-weight: 700; color: #222; margin-bottom: 10px; }
    .order-success p { color: #666; font-size: 1rem; margin-bottom: 6px; }
    .order-success .order-num { font-size: 1.1rem; font-weight: 700; color: #ff9100; background: #fff8f0; padding: 10px 20px; border-radius: 8px; display: inline-block; margin: 16px 0; }
    .order-success .btn-continue { display: inline-block; margin-top: 20px; padding: 12px 32px; background: #ff9100; color: #fff; border-radius: 8px; font-weight: 700; text-decoration: none; }
    .order-success .btn-continue:hover { background: #e07d00; color: #fff; }

    /* Breadcrumb */
    .breadcrumb-area { background: #f0f0f0; padding: 18px 0; }
    .breadcrumb-area .breadcrumb { background: none; margin: 0; padding: 0; }
    .breadcrumb-item a { color: #ff9100; }
    .breadcrumb-item.active { color: #555; }

    /* Mobile */
    @media (max-width: 767px) {
      .mobile-logo { display: block; }
      .mobile-icons { display: flex; }
      .account-menu { display: none; }
      .checkout-section { padding: 30px 0 50px; }
      .checkout-card { padding: 20px; }
    }
  </style>
</head>
<body>
<?php include __DIR__ . '/includes/header_mobile_v2.php'; ?>
<div class="body_wrap">

  <!-- preloder -->
  <div class="preloder_part" id="site-preloader">
    <div class="spinner"><div class="dot1"></div><div class="dot2"></div></div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function(){
      setTimeout(function(){
        var p = document.getElementById('site-preloader');
        if (p) { p.style.transition='opacity .5s'; p.style.opacity='0'; setTimeout(function(){ p.style.display='none'; }, 500); }
      }, 500);
    });
  </script>

  <!-- back to top -->
  <div class="progress-wrap">
    <svg class="progress-circle svg-content" width="100%" height="100%" viewBox="-1 -1 102 102">
      <path d="M50,1 a49,49 0 0,1 0,98 a49,49 0 0,1 0,-98"/>
    </svg>
  </div>

  <!-- ══════════════════════ HEADER ══════════════════════ -->
  <header class="header header__style-one">
    <div class="header__top-info-wrap d-none d-lg-block">
      <div class="container">
        <div class="header__top-info ul_li_between mt-none-10">
          <ul class="ul_li mt-10">
            <li><i class="far fa-map-marker-alt"></i> Nos Magasins</li>
            <li><i class="far fa-truck"></i> Suivre ma Commande</li>
            <li><i class="fas fa-phone"></i> Appelez-nous : +509 4466-7553</li>
            <li><i class="fas fa-heart"></i> ATLANTECH — Votre spécialiste High-Tech en Haïti</li>
          </ul>
          <div class="header__top-right ul_li mt-10">
            <div class="date">
              <i class="fal fa-calendar-alt"></i>
              <?php
                date_default_timezone_set('America/Port-au-Prince');
                $fr_jours = ['Dimanche','Lundi','Mardi','Mercredi','Jeudi','Vendredi','Samedi'];
                $fr_mois  = ['','Janvier','Février','Mars','Avril','Mai','Juin','Juillet','Août','Septembre','Octobre','Novembre','Décembre'];
                $now_dt   = new DateTime('now', new DateTimeZone('America/Port-au-Prince'));
                echo $fr_jours[(int)$now_dt->format('w')] . ' ' . $now_dt->format('d') . ' ' . $fr_mois[(int)$now_dt->format('n')] . ' ' . $now_dt->format('Y');
              ?>
            </div>
            <div class="header__social ml-25">
              <a href="#!"><i class="fab fa-facebook-f"></i></a>
              <a href="#!"><i class="fab fa-whatsapp"></i></a>
              <a href="#!"><i class="fab fa-instagram"></i></a>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="container">
      <div class="header__middle ul_li_between justify-content-xs-center">
        <div class="header__logo">
          <a href="index.php"><img src="assets/img/logo/atlantech-logo.svg" alt="AtlanTech"></a>
        </div>
        <form class="header__search-box" action="shop.php" method="get">
          <div class="select-box">
            <select name="cat">
              <option value="">Toutes catégories</option>
              <?php foreach ($rootCategories as $cat): ?>
              <option value="<?= htmlspecialchars($cat['slug']) ?>"><?= htmlspecialchars($cat['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <input type="text" name="q" placeholder="Rechercher un produit...">
          <button type="submit"><i class="far fa-search"></i></button>
        </form>
        <div class="account-menu">
          <button class="account-trigger" aria-haspopup="true" aria-expanded="false">
            <img loading="lazy" src="assets/img/icon/user.svg" alt="Compte">
            <span class="labels">
              <span class="hello">Bonjour, <?= htmlspecialchars($user_first_name ?: 'identifiez-vous') ?></span>
              <span class="acct">Compte &amp; Listes <i class="fas fa-caret-down"></i></span>
            </span>
          </button>
          <div class="account-dropdown" role="menu">
            <div class="dropdown-header">
              <a href="#">Mon Profil</a>
              <a href="logout.php" class="blue">Se déconnecter</a>
            </div>
            <div class="menu-columns">
              <div class="menu-group">
                <h4>Mon Compte</h4>
                <ul>
                  <li><a href="backoffice-client/dashboard.php">Tableau de bord</a></li>
                  <li><a href="backoffice-client/dashboard.php">Mes commandes</a></li>
                  <li><a href="wishlist.php">Mes favoris</a></li>
                  <li><a href="logout.php">Se déconnecter</a></li>
                </ul>
              </div>
              <div class="menu-group">
                <h4>Aide</h4>
                <ul>
                  <li><a href="contact.php">Service client</a></li>
                  <li><a href="cart.php">Mon panier</a></li>
                  <li><a href="shop.php">Boutique</a></li>
                </ul>
              </div>
            </div>
          </div>
        </div>
        <div class="icon wishlist-icon"><a href="wishlist.php"><img loading="lazy" src="assets/img/icon/heart.svg" alt=""> <span class="count"><?= (int)$wishlist_count ?></span></a></div>
        <div class="cart_btn icon"><a href="cart.php"><img loading="lazy" src="assets/img/icon/shopping_bag.svg" alt=""> <span class="count"><?= (int)$cart_count ?></span></a></div>
      </div>
    </div>

    <div class="header__cat-wrap" data-uk-sticky="top: 250; animation: uk-animation-slide-top;">
      <div class="container">
        <div class="header__wrap ul_li_between">
          <div class="header__cat ul_li">
            <div class="hamburger_menu">
              <a href="javascript:void(0);" class="active"><div class="icon bar"><span><i class="fal fa-bars"></i></span></div></a>
            </div>
            <div class="mobile-logo"><a href="index.php"><img src="assets/img/logo/atlantech-logo.svg" alt="AtlanTech"></a></div>
          </div>
          <div class="mobile-header-right d-flex align-items-center">
            <div class="mobile-icons">
              <a href="wishlist.php" class="mobile-icon-btn"><img loading="lazy" src="assets/img/icon/heart.svg" alt="Favoris"></a>
              <a href="cart.php" class="cart_btn mobile-icon-btn"><img loading="lazy" src="assets/img/icon/shopping_bag.svg" alt="Panier"></a>
            </div>
            <div class="login-sign-btn">
              <a class="thm-btn" href="backoffice-client/dashboard.php">
                <span class="btn-wrap"><span>Mon Compte</span><span>Mon Compte</span></span>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </header>
  <script>
  document.addEventListener('DOMContentLoaded', () => {
    const menu = document.querySelector('.account-menu');
    if (menu) {
      menu.querySelector('.account-trigger').addEventListener('click', e => { e.stopPropagation(); menu.classList.toggle('open'); });
      document.addEventListener('click', e => { if (!menu.contains(e.target)) menu.classList.remove('open'); });
    }
  });
  </script>

  <!-- slide-bar -->
  <aside class="slide-bar">
    <div class="slide-bar__header">
      <a href="index.php" class="slide-bar__logo"><img src="assets/img/logo/atlantech-logo.svg" alt="AtlanTech"></a>
      <div class="close-mobile-menu"><a href="javascript:void(0);"><i class="fal fa-times"></i></a></div>
    </div>
    <div class="slide-bar__search mb-30">
      <form action="shop.php" method="get">
        <input type="text" name="q" placeholder="Rechercher...">
        <button type="submit"><i class="fal fa-search"></i></button>
      </form>
    </div>
    <nav class="slide-bar__menu">
      <ul>
        <li><a href="index.php"><i class="far fa-home mr-2"></i> Accueil</a></li>
        <li><a href="shop.php"><i class="far fa-shopping-bag mr-2"></i> Boutique</a></li>
        <li><a href="cart.php"><i class="far fa-shopping-cart mr-2"></i> Mon Panier</a></li>
        <li><a href="backoffice-client/dashboard.php"><i class="far fa-user mr-2"></i> Mon Compte</a></li>
        <li><a href="wishlist.php"><i class="far fa-heart mr-2"></i> Mes Favoris</a></li>
        <li><a href="contact.php"><i class="far fa-envelope mr-2"></i> Contact</a></li>
        <li><a href="logout.php"><i class="far fa-sign-out mr-2"></i> Déconnexion</a></li>
      </ul>
    </nav>
    <div class="slide-bar__contact mt-30">
      <p><i class="fas fa-phone mr-2"></i> +509 4466-7553</p>
      <p><i class="far fa-envelope mr-2"></i> atlantech.service@gmail.com</p>
    </div>
  </aside>
  <div class="body-overlay"></div>

  <!-- ══════════════════════ BREADCRUMB ══════════════════════ -->
  <div class="breadcrumb-area">
    <div class="container">
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
          <li class="breadcrumb-item"><a href="index.php">Accueil</a></li>
          <li class="breadcrumb-item"><a href="cart.php">Panier</a></li>
          <li class="breadcrumb-item active">Commander</li>
        </ol>
      </nav>
    </div>
  </div>

  <!-- ══════════════════════ MAIN ══════════════════════ -->
  <main>
    <section class="checkout-section">
      <div class="container">

        <?php if ($order_success): ?>
        <!-- ── Commande réussie ──────────────────────────── -->
        <div class="checkout-card order-success">
          <div class="success-icon"><i class="fas fa-check-circle"></i></div>
          <h2>Commande confirmée !</h2>
          <?php if (!empty($moncash_error)): ?>
          <div style="background:#fff3f3; border:1px solid #f5c6c6; border-radius:8px; padding:14px 18px; margin:16px auto; max-width:520px;">
            <p style="margin:0; color:#c0392b; font-size:.92rem;"><i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($moncash_error) ?></p>
          </div>
          <?php else: ?>
          <p>Merci pour votre commande. Notre équipe la traitera dans les plus brefs délais.</p>
          <p>Vous recevrez une confirmation dès que votre commande sera expédiée.</p>
          <?php endif; ?>
          <div class="order-num"><?= htmlspecialchars($order_number) ?></div>
          <p style="color:#888; font-size:.9rem;">Conservez ce numéro pour suivre votre commande.</p>
          <a href="shop.php" class="btn-continue">Continuer mes achats</a>
          <br><br>
          <a href="backoffice-client/dashboard.php" style="color:#ff9100; font-size:.9rem;">Voir mes commandes →</a>
        </div>

        <?php elseif ($buy_now_expired): ?>
        <!-- ── Sélection "achat direct" expirée ──────────── -->
        <div class="checkout-card" style="text-align:center; padding:60px 30px;">
          <div class="success-icon" style="color:#e67e22;"><i class="fas fa-clock"></i></div>
          <h2>Votre sélection a expiré</h2>
          <p>Cette page d'achat direct n'est plus valide — la session a expiré ou une nouvelle connexion a eu lieu entre-temps.</p>
          <p style="color:#888; font-size:.9rem;">Pour votre sécurité, nous ne basculons jamais automatiquement vers un autre panier. Veuillez retourner à la fiche du produit et réessayer.</p>
          <a href="shop.php" class="btn-continue">Retour à la boutique</a>
        </div>

        <?php else: ?>
        <!-- ── Formulaire de commande ────────────────────── -->

        <?php if (!empty($errors)): ?>
        <div class="checkout-errors mb-4">
          <?php foreach ($errors as $err): ?>
          <p><i class="fas fa-exclamation-circle mr-2"></i><?= htmlspecialchars($err) ?></p>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <form method="POST" action="checkout.php<?= $mode === 'buy_now' ? '?mode=buy_now' : '' ?>" id="checkout-form" novalidate>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf_token) ?>">
        <input type="hidden" name="idempotency_key" value="<?= htmlspecialchars($client_idempotency_key) ?>">
        <div class="checkout-wrap">

          <!-- ── Colonne gauche : infos livraison + paiement ─ -->
          <div>

            <!-- Informations personnelles -->
            <div class="checkout-card mb-4">
              <h3><i class="far fa-user mr-2" style="color:#ff9100;"></i> Informations personnelles</h3>

              <div class="form-row-2">
                <div class="form-group-checkout">
                  <label for="customer_name">Nom complet *</label>
                  <input type="text" id="customer_name" name="customer_name"
                    value="<?= htmlspecialchars($_POST['customer_name'] ?? $user_name) ?>"
                    placeholder="Jean Dupont" required>
                </div>
                <div class="form-group-checkout">
                  <label for="customer_phone">Téléphone *</label>
                  <input type="tel" id="customer_phone" name="customer_phone"
                    value="<?= htmlspecialchars($_POST['customer_phone'] ?? $user_phone) ?>"
                    placeholder="+509 XXXX-XXXX" required>
                </div>
              </div>

              <div class="form-group-checkout">
                <label for="customer_email">Adresse email *</label>
                <input type="email" id="customer_email" name="customer_email"
                  value="<?= htmlspecialchars($_POST['customer_email'] ?? $user_email) ?>"
                  placeholder="email@exemple.com" required>
              </div>
            </div>

            <!-- Adresse de livraison -->
            <div class="checkout-card mb-4">
              <h3><i class="far fa-map-marker-alt mr-2" style="color:#ff9100;"></i> Adresse de livraison</h3>

              <div class="form-group-checkout">
                <label for="address_line">Adresse (rue, numéro, quartier) *</label>
                <input type="text" id="address_line" name="address_line"
                  value="<?= htmlspecialchars($_POST['address_line'] ?? '') ?>"
                  placeholder="Rue Saint-Michel, #12, Cayes-Jacmel" required>
              </div>

              <div class="form-row-2">
                <div class="form-group-checkout">
                  <label for="city">Ville * <small style="color:#888;font-weight:400">(le tarif de livraison s'applique)</small></label>
                  <select id="city" name="city" required>
                    <option value="" data-price="0">-- Choisir la ville --</option>
                    <?php
                    $sel_city = $_POST['city'] ?? '';

                    // Regrouper par département pour un <optgroup> propre
                    $by_dept = [];
                    foreach ($shipping_cities as $sc) {
                        $dept = $sc['department'] ?: 'Autres';
                        $by_dept[$dept][] = $sc;
                    }
                    ksort($by_dept);

                    if (empty($shipping_cities)) {
                        // Fallback si la table shipping_rates est vide ou absente
                        echo '<option value="" disabled>⚠ Aucune ville configurée. Contactez le support.</option>';
                    } else {
                        foreach ($by_dept as $dept => $list) {
                            echo '<optgroup label="' . htmlspecialchars($dept) . '">';
                            foreach ($list as $row) {
                                $c       = $row['city'];
                                $price   = (float) $row['price_htg'];
                                $sel     = ($sel_city === $c) ? ' selected' : '';
                                $label   = htmlspecialchars($c) . ' — ' . number_format($price, 0, ',', ' ') . ' HTG';
                                echo '<option value="' . htmlspecialchars($c) . '" data-price="' . $price . '"' . $sel . '>' . $label . '</option>';
                            }
                            echo '</optgroup>';
                        }
                    }
                    ?>
                  </select>
                </div>
                <div class="form-group-checkout">
                  <label>Pays</label>
                  <input type="text" value="Haïti" readonly style="background:#f8f8f8; color:#999;">
                </div>
              </div>

              <div class="form-group-checkout">
                <label for="notes_client">Instructions spéciales (optionnel)</label>
                <textarea id="notes_client" name="notes_client" rows="2"
                  placeholder="Appartement, code d'entrée, horaires préférés..."><?= htmlspecialchars($_POST['notes_client'] ?? '') ?></textarea>
              </div>
            </div>

            <!-- Mode de paiement -->
            <div class="checkout-card">
              <h3><i class="far fa-credit-card mr-2" style="color:#ff9100;"></i> Mode de paiement</h3>

              <div class="payment-options">

                <!-- MonCash -->
                <label class="payment-option">
                  <input type="radio" name="payment_method" value="MonCash"
                    <?= (($_POST['payment_method'] ?? '') === 'MonCash') ? 'checked' : '' ?>
                    onchange="toggleTransaction(this.value)">
                  <span class="pm-icon"><img loading="lazy" src="assets/img/payment/moncash.png" onerror="this.style.display='none';this.nextSibling.style.display='inline'" alt="" style="width:32px;height:32px;object-fit:contain;"><i class="fas fa-mobile-alt" style="color:#e91e63;display:none;"></i></span>
                  <span>
                    <span class="pm-label">MonCash</span><br>
                    <span class="pm-desc">Envoyez au : <strong>+509 3888-4000</strong> · Digicel MonCash</span>
                  </span>
                </label>

                <!-- NatCash -->
                <label class="payment-option">
                  <input type="radio" name="payment_method" value="NatCash"
                    <?= (($_POST['payment_method'] ?? '') === 'NatCash') ? 'checked' : '' ?>
                    onchange="toggleTransaction(this.value)">
                  <span class="pm-icon"><img loading="lazy" src="assets/img/payment/natcash.png" onerror="this.style.display='none';this.nextSibling.style.display='inline'" alt="" style="width:32px;height:32px;object-fit:contain;"><i class="fas fa-mobile-alt" style="color:#f57c00;display:none;"></i></span>
                  <span>
                    <span class="pm-label">NatCash</span><br>
                    <span class="pm-desc">Envoyez au : <strong>+509 4466-7553</strong> · Natcom NatCash</span>
                  </span>
                </label>

                <!-- Virement bancaire -->
                <label class="payment-option">
                  <input type="radio" name="payment_method" value="Bank"
                    <?= (($_POST['payment_method'] ?? '') === 'Bank') ? 'checked' : '' ?>
                    onchange="toggleTransaction(this.value)">
                  <span class="pm-icon" style="color:#1565c0;font-size:1.4rem;width:32px;text-align:center;"><i class="fas fa-university"></i></span>
                  <span>
                    <span class="pm-label">Virement bancaire</span><br>
                    <span class="pm-desc">BUH · Unibank · Sogebank · BNC · Capital Bank</span>
                  </span>
                </label>

                <!-- USDT -->
                <label class="payment-option">
                  <input type="radio" name="payment_method" value="USDT"
                    <?= (($_POST['payment_method'] ?? '') === 'USDT') ? 'checked' : '' ?>
                    onchange="toggleTransaction(this.value)">
                  <span class="pm-icon" style="color:#26a17b;font-size:1.4rem;width:32px;text-align:center;"><i class="fab fa-bitcoin"></i></span>
                  <span>
                    <span class="pm-label">USDT (Crypto)</span><br>
                    <span class="pm-desc">Tether TRC-20 · Réseau TRON — envoyez le reçu</span>
                  </span>
                </label>

                <!-- Cash -->
                <label class="payment-option">
                  <input type="radio" name="payment_method" value="Cash"
                    <?= (($_POST['payment_method'] ?? 'Cash') === 'Cash') ? 'checked' : '' ?>
                    onchange="toggleTransaction(this.value)">
                  <span class="pm-icon" style="color:#27ae60;font-size:1.4rem;width:32px;text-align:center;"><i class="fas fa-hand-holding-usd"></i></span>
                  <span>
                    <span class="pm-label">Paiement à la livraison</span><br>
                    <span class="pm-desc">Payez en espèces (HTG ou USD) à la réception</span>
                  </span>
                </label>

              </div>

              <!-- Sélection de banque (visible uniquement si Bank) -->
              <div class="transaction-field" id="bank-select-field">
                <label style="font-size:.85rem; font-weight:600; color:#555; display:block; margin-bottom:6px; margin-top:14px;">
                  Choisissez votre banque *
                </label>
                <select name="bank_name" id="bank_name">
                  <option value="">-- Sélectionner la banque --</option>
                  <?php
                  $banks = ['BUH','Unibank','Sogebank','BNC','Capital Bank','BPH','BICH','Scotiabank'];
                  $sel_bank = $_POST['bank_name'] ?? '';
                  foreach ($banks as $b) {
                    echo '<option value="' . htmlspecialchars($b) . '"' . ($sel_bank === $b ? ' selected' : '') . '>' . htmlspecialchars($b) . '</option>';
                  }
                  ?>
                </select>
              </div>

              <!-- Info passerelle MonCash (redirection automatique) -->
              <div id="moncash-gateway-info" style="display:none; background:#fdecef; border:1px solid #f5c2cf; border-radius:8px; padding:12px 16px; margin-top:14px;">
                <p style="margin:0 0 4px; font-size:.88rem; font-weight:700; color:#c2185b;"><i class="fas fa-mobile-alt mr-1"></i> Paiement sécurisé via MonCash</p>
                <p style="margin:0; font-size:.8rem; color:#880e4f;">
                  En cliquant sur « Confirmer ma commande », vous serez redirigé vers la page sécurisée
                  MonCash pour payer. À la fin du paiement, vous reviendrez ici automatiquement et votre
                  numéro de transaction sera enregistré tout seul — rien à recopier.
                </p>
              </div>

              <!-- Champ ID de transaction (NatCash / Bank / USDT) -->
              <div class="transaction-field" id="transaction-field">
                <label for="transaction_id" id="transaction-label" style="font-size:.85rem; font-weight:600; color:#555; display:block; margin-bottom:6px; margin-top:14px;">
                  Numéro de transaction / Référence *
                </label>
                <input type="text" id="transaction_id" name="transaction_id"
                  value="<?= htmlspecialchars($_POST['transaction_id'] ?? '') ?>"
                  placeholder="Entrez votre numéro de transaction">
              </div>

              <!-- Info USDT wallet -->
              <div class="transaction-field" id="usdt-info" style="background:#f0faf6; border:1px solid #b2dfdb; border-radius:8px; padding:12px 16px; margin-top:14px;">
                <p style="margin:0 0 4px; font-size:.85rem; font-weight:700; color:#00796b;"><i class="fab fa-bitcoin mr-1"></i> Adresse USDT (TRC-20)</p>
                <code id="usdt-address" style="font-size:.9rem; word-break:break-all; color:#004d40;">TAdresse_USDT_de_AtlanTech_ici</code>
                <button type="button" onclick="copyUSDT()" style="background:none;border:none;cursor:pointer;color:#00796b;margin-left:8px;" title="Copier"><i class="far fa-copy"></i></button>
                <p style="margin:6px 0 0; font-size:.78rem; color:#888;">Réseau TRON uniquement. Envoyez le hash de la transaction dans le champ ci-dessus.</p>
              </div>
            </div>
          </div>

          <!-- ── Colonne droite : récapitulatif commande ───── -->
          <div>
            <div class="checkout-card" style="position: sticky; top: 100px;">
              <h3><i class="far fa-shopping-bag mr-2" style="color:#ff9100;"></i> Récapitulatif</h3>

              <!-- Articles -->
              <div class="order-items-list">
                <?php foreach ($items as $item): ?>
                <?php
                  $fb_n  = (((int)$item['id'] - 1) % 177) + 1;
                  $fb    = sprintf('assets/img/product/img_%02d.png', $fb_n);
                  $img   = !empty($item['image']) ? 'uploads/products/' . htmlspecialchars($item['image']) : $fb;
                  $pid   = (int)$item['id'];
                  $price = (float)$item['price'];
                  $qty   = (int)$item['qty'];
                  $info  = $stock_map[$pid] ?? ['stock' => 9999, 'min_qty' => 1, 'max_qty' => 9999];
                  $max_q = max(1, min($info['stock'] > 0 ? $info['stock'] : 9999, $info['max_qty'] ?: 9999));
                  $min_q = max(1, $info['min_qty']);
                ?>
                <div class="order-item" data-product-id="<?= $pid ?>" data-price="<?= $price ?>" data-min="<?= $min_q ?>" data-max="<?= $max_q ?>">
                  <img loading="lazy" src="<?= $img ?>" onerror="this.src='<?= $fb ?>'" alt="<?= htmlspecialchars($item['name']) ?>">
                  <div class="order-item-info">
                    <div class="order-item-name"><?= htmlspecialchars($item['name']) ?></div>
                    <?php if (!empty($item['color_name'])): ?>
                    <div style="font-size:.78rem; color:#666; margin-bottom:4px;">
                      Couleur : <strong style="color:#ff9100;"><?= htmlspecialchars($item['color_name']) ?></strong>
                    </div>
                    <?php endif; ?>
                    <div class="qty-control">
                      <button type="button" class="qty-btn qty-minus" aria-label="Diminuer">−</button>
                      <input type="number"
                             class="qty-input"
                             name="qty_override[<?= $pid ?>]"
                             value="<?= $qty ?>"
                             min="<?= $min_q ?>"
                             max="<?= $max_q ?>"
                             readonly>
                      <button type="button" class="qty-btn qty-plus" aria-label="Augmenter">+</button>
                    </div>
                    <?php if ($max_q < 9999): ?>
                    <div class="qty-stock-note"><?= $max_q ?> en stock</div>
                    <?php endif; ?>
                  </div>
                  <div class="order-item-price" data-line-total><?= number_format($price * $qty, 2) ?> HTG</div>
                </div>
                <?php endforeach; ?>
              </div>

              <!-- Code promo -->
              <div class="promo-block" style="margin-top:18px; padding:14px; background:#faf9ff; border:1px dashed #d8d4ff; border-radius:10px;">
                <label for="promo_code" style="font-weight:600; font-size:.85rem; color:#4c1d95; display:block; margin-bottom:6px">
                  <i class="fas fa-tag"></i> Code promo
                </label>
                <div style="display:flex; gap:8px;">
                  <input type="text"
                         id="promo_code"
                         name="promo_code"
                         value="<?= htmlspecialchars($_POST['promo_code'] ?? '') ?>"
                         placeholder="Entrez votre code"
                         maxlength="50"
                         style="flex:1; padding:9px 12px; border:1px solid #d8d4ff; border-radius:6px; text-transform:uppercase; font-family:monospace; font-size:.95rem;">
                  <button type="button" id="btn-apply-promo"
                          style="padding:9px 16px; background:#6d28d9; color:#fff; border:none; border-radius:6px; font-weight:600; cursor:pointer; white-space:nowrap;">
                    Appliquer
                  </button>
                </div>
                <div id="promo-feedback" style="margin-top:8px; font-size:.85rem; display:none;"></div>
              </div>

              <!-- Totaux -->
              <div class="summary-totals">
                <div class="summary-row">
                  <span>Sous-total</span>
                  <span id="sum-subtotal"><?= number_format($subtotal, 2) ?> HTG</span>
                </div>
                <div class="summary-row" id="sum-discount-row" style="<?= empty($_POST['promo_code']) ? 'display:none;' : '' ?> color:#10b981;">
                  <span>Réduction <small id="sum-discount-code" style="opacity:.8"></small></span>
                  <span id="sum-discount">– 0,00 HTG</span>
                </div>
                <div class="summary-row">
                  <span>Livraison</span>
                  <span id="sum-shipping">
                    <?php if ($selected_city_initial === ''): ?>
                      <em style="color:#888;font-weight:400">Choisir une ville</em>
                    <?php else: ?>
                      <?= number_format($shipping_cost, 2) ?> HTG
                    <?php endif; ?>
                  </span>
                </div>
                <div id="sum-shipping-hint" style="font-size:.78rem; color:#888; text-align:right; margin-top:-6px;<?= $selected_city_initial === '' ? '' : 'display:none;' ?>">
                  Le tarif dépend de la ville
                </div>
                <div class="summary-row total">
                  <span>Total</span>
                  <span id="sum-total"
                        data-subtotal="<?= $subtotal ?>"
                        data-shipping="<?= $shipping_cost ?>"
                        data-discount="0"><?= number_format($total, 2) ?> HTG</span>
                </div>
              </div>

              <!-- Bouton commander / payer -->
              <button type="submit" class="btn-checkout" id="btn-confirm-order">
                <span class="btn-label" id="btn-confirm-label"><i class="fas fa-lock mr-2"></i>Confirmer ma commande</span>
                <span class="btn-spinner" style="display:none;"><i class="fas fa-spinner fa-spin mr-2"></i>Traitement en cours...</span>
              </button>

              <!-- Badges sécurité -->
              <div class="security-badges">
                <div class="security-badge"><i class="fas fa-shield-alt"></i> Paiement sécurisé</div>
                <div class="security-badge"><i class="fas fa-undo"></i> Retours sous 7 jours</div>
                <div class="security-badge"><i class="fas fa-headset"></i> Support 24/7</div>
              </div>
            </div>
          </div>

        </div><!-- /.checkout-wrap -->
        </form>
        <?php endif; ?>

      </div><!-- /.container -->
    </section>
  </main>

  <!-- ══════════════════════ FOOTER ══════════════════════ -->
  <footer class="footer" data-background="assets/img/bg/footer_bg.jpg">
    <div class="container">
      <div class="footer__main pt-90 pb-90">
        <div class="row mt-none-40">
          <div class="footer__widget col-lg-3 col-md-6 mt-40">
            <div class="footer__logo mb-20">
              <a href="index.php"><img src="assets/img/logo/atlantech-logo.svg" alt="AtlanTech"></a>
            </div>
            <p>AtlanTech — Votre partenaire technologique aux Cayes, Haïti. Produits certifiés, service professionnel et livraison rapide.</p>
            <ul class="footer__info mt-30">
              <li><i class="far fa-map-marker-alt"></i> Les Cayes, Sud, Haïti</li>
              <li><i class="fas fa-phone"></i> (+509) 44 66 75 53</li>
              <li><i class="far fa-envelope"></i> atlantech.service@gmail.com</li>
            </ul>
          </div>
          <div class="footer__widget col-lg-3 col-md-6 mt-40">
            <h2 class="title">Catégories</h2>
            <ul class="quick-links">
              <?php foreach (array_slice($rootCategories, 0, 6) as $cat): ?>
              <li><a href="shop.php?category=<?= (int)$cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></a></li>
              <?php endforeach; ?>
            </ul>
          </div>
          <div class="footer__widget col-lg-3 col-md-6 mt-40">
            <h2 class="title">Liens rapides</h2>
            <ul class="quick-links">
              <li><a href="account.php">Mon compte</a></li>
              <li><a href="cart.php">Mon panier</a></li>
              <li><a href="wishlist.php">Mes favoris</a></li>
              <li><a href="shop.php">Boutique</a></li>
              <li><a href="contact.php">Contact</a></li>
            </ul>
          </div>
          <div class="footer__widget col-lg-3 col-md-6 mt-40">
            <h2 class="title">Service client</h2>
            <ul class="category">
              <li><a href="contact.php">Centre d'aide</a></li>
              <li><a href="#">Livraison &amp; Expédition</a></li>
              <li><a href="#">Retours &amp; Remboursements</a></li>
              <li><a href="about.php">À propos</a></li>
            </ul>
          </div>
        </div>
      </div>
      <div class="footer__bottom ul_li_center">
        <div class="footer__copyright mt-15">&copy; <?= date('Y') ?> <a href="index.php">AtlanTech</a>. Tous droits réservés.</div>
        <div class="footer__social mt-15">
          <a href="https://facebook.com/atlantech.service" target="_blank"><i class="fab fa-facebook-f"></i></a>
          <a href="https://wa.me/50944667553" target="_blank"><i class="fab fa-whatsapp"></i></a>
          <a href="https://instagram.com/atlantech.service" target="_blank"><i class="fab fa-instagram"></i></a>
          <a href="mailto:atlantech.service@gmail.com"><i class="far fa-envelope"></i></a>
        </div>
        <div class="payment_method mt-15">
          <img loading="lazy" src="assets/img/bg/payment_method.png" alt="Méthodes de paiement">
        </div>
      </div>
    </div>
  </footer>

  <!-- cookies -->
  <div class="cookies-area">
    <p>Ce site utilise des cookies pour améliorer votre expérience. <a href="#">Politique de confidentialité</a>.</p>
    <div><button class="cookie-btn">Accepter</button></div>
  </div>

</div><!-- /.body_wrap -->

<!-- JS -->
    <!-- JS bundle (15 fichiers → 1 requête) -->
    <script src="assets/js/bundle.min.js"></script>
<script>
// ── Afficher/masquer les champs selon le mode de paiement ────────────
function toggleTransaction(method) {
  var txField    = document.getElementById('transaction-field');
  var txInput    = document.getElementById('transaction_id');
  var txLabel    = document.getElementById('transaction-label');
  var bankField  = document.getElementById('bank-select-field');
  var bankSelect = document.getElementById('bank_name');
  var usdtInfo   = document.getElementById('usdt-info');

  txField.classList.remove('visible');
  bankField.classList.remove('visible');
  usdtInfo.classList.remove('visible');
  txInput.removeAttribute('required');
  bankSelect.removeAttribute('required');
  var _mcInfo = document.getElementById('moncash-gateway-info');
  if (_mcInfo) _mcInfo.style.display = 'none';

  // Réinitialiser le libellé du bouton par défaut
  var _btnLabel = document.getElementById('btn-confirm-label');
  if (_btnLabel) _btnLabel.innerHTML = '<i class="fas fa-lock mr-2"></i>Confirmer ma commande';

  if (method === 'MonCash') {
    // Paiement via passerelle MonCash : pas de saisie manuelle.
    // Le client sera redirigé vers MonCash et l'ID sera récupéré automatiquement.
    var mcInfo = document.getElementById('moncash-gateway-info');
    if (mcInfo) mcInfo.style.display = 'block';
    // Le bouton devient un bouton "Payer" qui mène à la passerelle MonCash
    if (_btnLabel) _btnLabel.innerHTML = '<i class="fas fa-mobile-alt mr-2"></i>Payer avec MonCash →';
  } else if (method === 'NatCash') {
    txField.classList.add('visible');
    txInput.setAttribute('required', 'required');
    txInput.placeholder = 'Numéro de transaction NatCash';
    txLabel.textContent = 'Numéro de transaction NatCash *';
  } else if (method === 'Bank') {
    bankField.classList.add('visible');
    bankSelect.setAttribute('required', 'required');
    txField.classList.add('visible');
    txInput.setAttribute('required', 'required');
    txInput.placeholder = 'Numéro de référence du virement';
    txLabel.textContent = 'Référence du virement bancaire *';
  } else if (method === 'USDT') {
    usdtInfo.classList.add('visible');
    txField.classList.add('visible');
    txInput.setAttribute('required', 'required');
    txInput.placeholder = 'Hash de la transaction USDT (TxID)';
    txLabel.textContent = 'Hash de la transaction USDT *';
  }
  // Cash : aucun champ supplémentaire
}

function copyUSDT() {
  var addr = document.getElementById('usdt-address').textContent;
  navigator.clipboard.writeText(addr).then(function() {
    alert('Adresse USDT copiée !');
  });
}

// ────────────────────────────────────────────────────────────────────
// Gestion des quantités dans le récapitulatif (boutons +/-)
// ────────────────────────────────────────────────────────────────────
function formatHTG(n) {
  return n.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') + ' HTG';
}

function recomputeOrderTotals() {
  var subtotal = 0;
  document.querySelectorAll('.order-item').forEach(function(item) {
    var price = parseFloat(item.dataset.price || '0');
    var qty   = parseInt(item.querySelector('.qty-input').value, 10) || 1;
    var line  = price * qty;
    var lineEl = item.querySelector('[data-line-total]');
    if (lineEl) lineEl.textContent = formatHTG(line);
    subtotal += line;
  });

  var shipping = subtotal >= 5000 ? 0 : 500;
  var total    = subtotal + shipping;

  document.querySelectorAll('.summary-row').forEach(function(row) {
    var label = row.querySelector('span:first-child');
    if (!label) return;
    var lbl = label.textContent.trim();
    var valSpan = row.querySelector('span:last-child');
    if (!valSpan) return;
    if (lbl === 'Sous-total') {
      valSpan.textContent = formatHTG(subtotal);
    } else if (lbl === 'Livraison') {
      if (shipping === 0) {
        valSpan.innerHTML = '<span class="shipping-free"><i class="fas fa-check-circle mr-1"></i>Gratuite</span>';
      } else {
        valSpan.textContent = formatHTG(shipping);
      }
    } else if (lbl === 'Total') {
      valSpan.textContent = formatHTG(total);
    }
  });
}

function bindQtyControls() {
  document.querySelectorAll('.order-item').forEach(function(item) {
    var minus = item.querySelector('.qty-minus');
    var plus  = item.querySelector('.qty-plus');
    var input = item.querySelector('.qty-input');
    if (!minus || !plus || !input) return;

    var min = parseInt(item.dataset.min || '1', 10);
    var max = parseInt(item.dataset.max || '99', 10);

    function refreshButtons() {
      var v = parseInt(input.value, 10) || 1;
      minus.disabled = v <= min;
      plus.disabled  = v >= max;
    }

    minus.addEventListener('click', function() {
      var v = parseInt(input.value, 10) || 1;
      if (v > min) {
        input.value = v - 1;
        recomputeOrderTotals();
        refreshButtons();
      }
    });

    plus.addEventListener('click', function() {
      var v = parseInt(input.value, 10) || 1;
      if (v < max) {
        input.value = v + 1;
        recomputeOrderTotals();
        refreshButtons();
      } else {
        plus.style.background = '#fcd34d';
        setTimeout(function() { plus.style.background = ''; }, 250);
      }
    });

    refreshButtons();
  });
}

// ── Recalcul du total quand on change de ville (tarif depuis shipping_rates) ──
function bindCityShippingUpdate() {
  var citySelect = document.getElementById('city');
  var spanShip   = document.getElementById('sum-shipping');
  var spanTotal  = document.getElementById('sum-total');
  var hint       = document.getElementById('sum-shipping-hint');
  if (!citySelect || !spanShip || !spanTotal) return;

  function fmtHTG(n) {
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' HTG';
  }

  function update() {
    var opt = citySelect.options[citySelect.selectedIndex];
    var price = opt && opt.value ? parseFloat(opt.dataset.price || '0') : 0;
    var subtotal = parseFloat(spanTotal.dataset.subtotal || '0');
    var discount = parseFloat(spanTotal.dataset.discount || '0');

    if (!opt || !opt.value) {
      spanShip.innerHTML = '<em style="color:#888;font-weight:400">Choisir une ville</em>';
      if (hint) hint.style.display = '';
    } else {
      spanShip.textContent = fmtHTG(price);
      if (hint) hint.style.display = 'none';
    }
    spanTotal.textContent = fmtHTG(Math.max(0, subtotal - discount) + price);
    spanTotal.dataset.shipping = price;
  }

  citySelect.addEventListener('change', update);
  update(); // calculer au chargement (si une ville était déjà sélectionnée via POST)
}

// ── Application d'un code promo (AJAX) ────────────────────────────────
function bindPromoCode() {
  var input    = document.getElementById('promo_code');
  var btn      = document.getElementById('btn-apply-promo');
  var feedback = document.getElementById('promo-feedback');
  var rowDisc  = document.getElementById('sum-discount-row');
  var spanDisc = document.getElementById('sum-discount');
  var codeLbl  = document.getElementById('sum-discount-code');
  var spanTot  = document.getElementById('sum-total');
  var spanShip = document.getElementById('sum-shipping');
  if (!input || !btn || !spanTot) return;

  function fmtHTG(n) {
    return n.toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' HTG';
  }

  function setFeedback(msg, type) {
    if (!feedback) return;
    feedback.style.display = msg ? 'block' : 'none';
    feedback.style.color   = type === 'ok' ? '#10b981' : '#ef4444';
    feedback.innerHTML     = msg;
  }

  function recomputeTotal(discount) {
    var subtotal = parseFloat(spanTot.dataset.subtotal || '0');
    var shipping = parseFloat(spanTot.dataset.shipping || '0');
    spanTot.dataset.discount = discount;
    var newTotal = Math.max(0, subtotal - discount) + shipping;
    spanTot.textContent = fmtHTG(newTotal);
  }

  function applyPromo() {
    var code = input.value.trim().toUpperCase();
    input.value = code; // normaliser visuellement
    if (!code) {
      setFeedback('', null);
      rowDisc.style.display = 'none';
      recomputeTotal(0);
      return;
    }
    var subtotal = parseFloat(spanTot.dataset.subtotal || '0');
    btn.disabled = true;
    btn.textContent = '...';

    fetch('checkout.php?action=validate_promo&code=' + encodeURIComponent(code) + '&subtotal=' + subtotal)
      .then(r => r.json())
      .then(data => {
        btn.disabled = false;
        btn.textContent = 'Appliquer';
        if (data.valid) {
          var pct = parseFloat(data.discount_percent).toFixed(0);
          var amt = parseFloat(data.discount_amount);
          setFeedback('✅ Code <strong>' + code + '</strong> appliqué : –' + pct + '% (–' + fmtHTG(amt) + ')', 'ok');
          if (codeLbl) codeLbl.textContent = '(' + code + ')';
          spanDisc.textContent = '– ' + fmtHTG(amt);
          rowDisc.style.display = '';
          recomputeTotal(amt);
        } else {
          setFeedback('❌ ' + (data.error || 'Code invalide'), 'err');
          rowDisc.style.display = 'none';
          recomputeTotal(0);
        }
      })
      .catch(() => {
        btn.disabled = false;
        btn.textContent = 'Appliquer';
        setFeedback('❌ Erreur réseau. Réessayez.', 'err');
        rowDisc.style.display = 'none';
        recomputeTotal(0);
      });
  }

  btn.addEventListener('click', applyPromo);
  input.addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
      e.preventDefault();
      applyPromo();
    }
  });

  // Si un code était déjà rempli (POST échoué), l'auto-appliquer au chargement
  if (input.value.trim()) applyPromo();
}

// Initialiser à l'affichage de la page
document.addEventListener('DOMContentLoaded', function() {
  var checked = document.querySelector('input[name="payment_method"]:checked');
  if (checked) toggleTransaction(checked.value);

  bindQtyControls();
  bindCityShippingUpdate();
  bindPromoCode();

  var form = document.getElementById('checkout-form');
  if (!form) return;

  form.addEventListener('submit', function(e) {
    var pm = document.querySelector('input[name="payment_method"]:checked');
    if (!pm) {
      e.preventDefault();
      alert('Veuillez choisir un mode de paiement.');
      return;
    }

    // Référence requise pour paiements saisis manuellement. MonCash exclu (passerelle).
    var needsRef = ['NatCash','Bank','USDT'].indexOf(pm.value) !== -1;
    if (needsRef) {
      var tx = (document.getElementById('transaction_id').value || '').trim();
      if (tx.length < 4) {
        e.preventDefault();
        alert('Veuillez entrer la référence de votre transaction (au moins 4 caractères).');
        document.getElementById('transaction_id').focus();
        return;
      }
    }
    if (pm.value === 'Bank') {
      var bk = (document.getElementById('bank_name').value || '').trim();
      if (!bk) {
        e.preventDefault();
        alert('Veuillez choisir votre banque.');
        document.getElementById('bank_name').focus();
        return;
      }
    }

    // Anti-double-clic + spinner
    var btn = document.getElementById('btn-confirm-order');
    if (btn && !btn.disabled) {
      btn.disabled = true;
      btn.style.opacity = '.7';
      btn.style.cursor = 'wait';
      var label = btn.querySelector('.btn-label');
      var spin  = btn.querySelector('.btn-spinner');
      if (label) label.style.display = 'none';
      if (spin)  spin.style.display  = 'inline';
    } else if (btn && btn.disabled) {
      e.preventDefault();
      return;
    }
  });
});
</script>
</body>
</html>
