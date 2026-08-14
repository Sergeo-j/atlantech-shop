<?php
/**
 * AtlanTech — Helpers de sécurité et de validation pour le checkout
 *
 * Centralise toute la logique défensive du flux paiement :
 *   - CSRF (génération / vérification)
 *   - Validation des champs (email, téléphone HT)
 *   - Validation par méthode de paiement
 *   - Re-calcul du panier depuis la BD (anti-tampering du prix)
 *   - Vérification du stock avec verrou
 *   - Idempotence (anti-double-soumission)
 *   - Génération de numéro de commande
 *   - Journal des tentatives (checkout_attempts)
 *
 * À inclure UNE FOIS depuis checkout.php après config/config.php.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ════════════════════════════════════════════════════════════════════
// 1. CSRF
// ════════════════════════════════════════════════════════════════════

/**
 * Renvoie le token CSRF de la session (le génère si absent).
 */
function checkout_csrf_token(): string {
    if (empty($_SESSION['_csrf_checkout'])) {
        $_SESSION['_csrf_checkout'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf_checkout'];
}

/**
 * Vérifie un token CSRF posté. Utilise hash_equals (timing-safe).
 */
function checkout_csrf_verify(?string $posted): bool {
    if (empty($posted) || empty($_SESSION['_csrf_checkout'])) return false;
    return hash_equals($_SESSION['_csrf_checkout'], $posted);
}


// ════════════════════════════════════════════════════════════════════
// 2. Validation des champs personnels
// ════════════════════════════════════════════════════════════════════

/**
 * Email — wrapper sur filter_var, avec longueur max.
 */
function v_email(string $email): bool {
    if (strlen($email) > 150) return false;
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Téléphone haïtien — accepte :
 *   - +509 XXXX-XXXX
 *   - +509XXXXXXXX
 *   - 509XXXXXXXX
 *   - XXXXXXXX (8 chiffres locaux)
 *   - XXXX-XXXX
 * Retourne le numéro normalisé "+509XXXXXXXX" ou null si invalide.
 */
function v_phone_ht(string $phone): ?string {
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === null) return null;
    // Cas "509XXXXXXXX" (11 chiffres)
    if (strlen($digits) === 11 && substr($digits, 0, 3) === '509') {
        return '+509' . substr($digits, 3);
    }
    // Cas "XXXXXXXX" (8 chiffres locaux)
    if (strlen($digits) === 8) {
        return '+509' . $digits;
    }
    return null;
}

/**
 * Longueur de texte simple — utile pour adresse / ville / note.
 */
function v_text(string $value, int $min, int $max): bool {
    $len = mb_strlen($value);
    return $len >= $min && $len <= $max;
}


// ════════════════════════════════════════════════════════════════════
// 3. Validation par méthode de paiement
// ════════════════════════════════════════════════════════════════════

/**
 * Mapping label utilisateur → enum DB.
 */
function checkout_payment_map(): array {
    return [
        'MonCash' => 'MonCash',
        'NatCash' => 'MonCash',   // mobile money → enum MonCash
        'Bank'    => 'Bank',
        'USDT'    => 'Zelle',     // crypto/inter → enum Zelle
        'Cash'    => 'Cash',
    ];
}

/**
 * Liste des banques acceptées (whitelist stricte).
 */
function checkout_banks_whitelist(): array {
    return ['BUH','Unibank','Sogebank','BNC','Capital Bank','BPH','BICH','Scotiabank'];
}

/**
 * Liste des villes acceptées (whitelist).
 */
function checkout_cities_whitelist(): array {
    return ['Les Cayes','Port-au-Prince','Cap-Haïtien','Gonaïves','Saint-Marc',
            'Jacmel','Jérémie','Hinche','Fort-Liberté','Miragoâne','Léogâne'];
}

/**
 * Valide la cohérence du paiement et renvoie les erreurs.
 *
 * @return string[] erreurs (vide si OK)
 */
function v_payment_payload(array $post): array {
    $errors = [];
    $method = (string)($post['payment_method'] ?? '');
    $tx     = trim((string)($post['transaction_id'] ?? ''));
    $bank   = trim((string)($post['bank_name'] ?? ''));

    $map = checkout_payment_map();
    if (!array_key_exists($method, $map)) {
        $errors[] = 'Veuillez choisir un mode de paiement valide.';
        return $errors; // inutile d'aller plus loin
    }

    // Référence de transaction requise pour les paiements non-Cash SAISIS MANUELLEMENT.
    // MonCash est exclu : c'est la passerelle qui fournit l'ID automatiquement après paiement.
    if (in_array($method, ['NatCash','Bank','USDT'], true)) {
        if (mb_strlen($tx) < 4 || mb_strlen($tx) > 100) {
            $errors[] = 'Veuillez entrer une référence de transaction valide (4-100 caractères).';
        }
    }

    if ($method === 'Bank') {
        if (!in_array($bank, checkout_banks_whitelist(), true)) {
            $errors[] = 'Veuillez choisir une banque dans la liste.';
        }
    }

    // USDT : on n'impose pas un format strict (les hashes TRON font 64 caractères
    // hex en théorie, mais les utilisateurs collent parfois avec préfixe 0x ou
    // sans). On laisse souple, l'admin vérifie manuellement.

    return $errors;
}


// ════════════════════════════════════════════════════════════════════
// 4. Re-calcul du panier depuis la BD (anti-tampering)
// ════════════════════════════════════════════════════════════════════

/**
 * Re-charge les articles passés (id + qty) depuis la BD et renvoie un
 * tableau enrichi avec les prix LIVE, le stock dispo, et un flag
 * `is_active`. Tout prix passé en session est ignoré : on prend la vérité
 * de la BD au moment du checkout.
 *
 * @param array $items_in   [['id'=>int,'qty'=>int], ...]
 * @return array            [['id','name','image','price','qty','total','stock','min_qty','max_qty','active'], ...]
 */
function checkout_recompute_items(mysqli $mysqli, array $items_in): array {
    $items_in = array_values($items_in);
    $ids = array_filter(array_map(fn($i) => (int)($i['id'] ?? 0), $items_in));
    if (empty($ids)) return [];

    // Index id → qty demandée + couleur choisie (préservée depuis la session)
    $qty_map   = [];
    $color_map = [];
    foreach ($items_in as $i) {
        $id = (int)($i['id'] ?? 0);
        if ($id > 0) {
            $qty_map[$id]   = max(1, (int)($i['qty'] ?? 1));
            $color_map[$id] = [
                'color_id'   => $i['color_id']   ?? null,
                'color_name' => $i['color_name'] ?? null,
            ];
        }
    }

    $in = implode(',', array_map('intval', array_keys($qty_map)));
    $sql = "SELECT id, name, image, price, stock, min_order_qty, max_order_qty, is_active
            FROM products
            WHERE id IN ($in)";
    $res = $mysqli->query($sql);
    if (!$res) return [];

    $out = [];
    while ($row = $res->fetch_assoc()) {
        $pid = (int)$row['id'];
        $qty = $qty_map[$pid] ?? 1;
        $base_price = (float)$row['price'];
        $color_id   = $color_map[$pid]['color_id'] ?? null;
        // Prix recalculé serveur : prix de la couleur si défini, sinon prix de base.
        // Le client ne peut JAMAIS imposer un prix — on le dérive toujours de la BD.
        $price = function_exists('effective_color_price')
            ? effective_color_price($mysqli, $pid, $color_id ? (int)$color_id : null, $base_price)
            : $base_price;
        $out[] = [
            'id'         => $pid,
            'name'       => $row['name'],
            'image'      => $row['image'],
            'price'      => $price,
            'qty'        => $qty,
            'total'      => $price * $qty,
            'stock'      => (int)$row['stock'],
            'min_qty'    => (int)$row['min_order_qty'],
            'max_qty'    => $row['max_order_qty'] !== null ? (int)$row['max_order_qty'] : null,
            'active'     => (int)$row['is_active'] === 1,
            'color_id'   => $color_id,
            'color_name' => $color_map[$pid]['color_name'] ?? null,
        ];
    }
    $res->close();
    return $out;
}

/**
 * Vérifie disponibilité + bornes min/max pour chaque article.
 *
 * @return string[] erreurs (vide si OK)
 */
function checkout_check_stock(array $items): array {
    $errors = [];
    foreach ($items as $it) {
        $name = $it['name'] ?? ('#' . $it['id']);
        if (!$it['active']) {
            $errors[] = "« $name » n'est plus disponible à la vente.";
            continue;
        }
        if ($it['qty'] < $it['min_qty']) {
            $errors[] = "« $name » : quantité minimum {$it['min_qty']}.";
        }
        if ($it['max_qty'] !== null && $it['qty'] > $it['max_qty']) {
            $errors[] = "« $name » : quantité maximum {$it['max_qty']}.";
        }
        if ($it['stock'] !== null && $it['qty'] > $it['stock']) {
            $errors[] = "Stock insuffisant pour « $name » (disponible : {$it['stock']}, demandé : {$it['qty']}).";
        }
    }
    return $errors;
}

/**
 * Pose un verrou sur les lignes products concernées et décrémente le stock
 * dans la même transaction. À appeler APRÈS begin_transaction.
 */
function checkout_decrement_stock(mysqli $mysqli, array $items): void {
    if (empty($items)) return;
    $stmt = $mysqli->prepare(
        "UPDATE products SET stock = GREATEST(stock - ?, 0), sold_count = sold_count + ?
         WHERE id = ? AND stock >= ?"
    );
    if (!$stmt) return;
    foreach ($items as $it) {
        $qty = (int)$it['qty'];
        $pid = (int)$it['id'];
        $stmt->bind_param('iiii', $qty, $qty, $pid, $qty);
        $stmt->execute();
    }
    $stmt->close();
}


// ════════════════════════════════════════════════════════════════════
// 5. Idempotence (anti-double-soumission)
// ════════════════════════════════════════════════════════════════════

/**
 * Renvoie une clé d'idempotence stable pour la session/forme courante.
 * Si pas fournie par le client, on la régénère ici. Le frontend en pose
 * une dans un hidden field au premier affichage du form, ce qui garantit
 * qu'un double-clic sur "Confirmer" génère la MÊME clé.
 */
function checkout_idempotency_key(?string $client_key): string {
    $key = trim((string)$client_key);
    if (preg_match('/^[a-zA-Z0-9_-]{16,64}$/', $key)) {
        return $key;
    }
    return bin2hex(random_bytes(16));
}

/**
 * Cherche une commande déjà créée avec cette clé. Si trouvée, on renvoie
 * la commande (id + order_number) pour afficher l'écran de succès sans
 * dupliquer. Ignore l'erreur si la colonne idempotency_key n'existe pas.
 */
function checkout_find_by_idempotency(mysqli $mysqli, int $user_id, string $key): ?array {
    try {
        $stmt = $mysqli->prepare(
            "SELECT id, order_number FROM orders
             WHERE user_id = ? AND idempotency_key = ?
             ORDER BY id DESC LIMIT 1"
        );
        if (!$stmt) return null;
        $stmt->bind_param('is', $user_id, $key);
        $stmt->execute();
        $r = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        return $r ?: null;
    } catch (\Throwable $e) {
        // Colonne absente → idempotence désactivée, on continue.
        return null;
    }
}


// ════════════════════════════════════════════════════════════════════
// 6. Numéro de commande
// ════════════════════════════════════════════════════════════════════

function checkout_generate_order_number(): string {
    return 'AT-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 5));
}


// ════════════════════════════════════════════════════════════════════
// 7. Journal des tentatives (audit)
// ════════════════════════════════════════════════════════════════════

/**
 * Enregistre chaque tentative de checkout (succès ou échec) pour audit
 * et détection d'abus. Ne lève jamais d'exception : si la table n'existe
 * pas (migration 004 non encore exécutée), on retombe silencieusement
 * sur error_log.
 */
function checkout_log_attempt(
    mysqli $mysqli,
    int $user_id,
    string $status,        // 'success' | 'validation_fail' | 'sql_error' | 'duplicate'
    string $payment_method,
    float $total,
    ?string $order_number,
    ?string $error_message,
    string $ip,
    string $user_agent
): void {
    try {
        $stmt = $mysqli->prepare(
            "INSERT INTO checkout_attempts
                (user_id, status, payment_method, total_amount, order_number,
                 error_message, ip_address, user_agent, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())"
        );
        if (!$stmt) return;
        $stmt->bind_param(
            'issdssss',
            $user_id, $status, $payment_method, $total,
            $order_number, $error_message, $ip, $user_agent
        );
        $stmt->execute();
        $stmt->close();
    } catch (\Throwable $e) {
        error_log(sprintf(
            'checkout_attempt[%s] uid=%d pm=%s total=%.2f order=%s err=%s',
            $status, $user_id, $payment_method, $total, $order_number ?? '-', $error_message ?? '-'
        ));
    }
}


// ════════════════════════════════════════════════════════════════════
// 8. Rate limiting basique (session-based)
// ════════════════════════════════════════════════════════════════════

/**
 * Limite simple : pas plus de N tentatives toutes les T secondes pour
 * une même session. Renvoie true si la limite est dépassée.
 */
function checkout_rate_limited(int $max = 6, int $window = 60): bool {
    $now  = time();
    $hist = $_SESSION['_checkout_attempts'] ?? [];
    $hist = array_values(array_filter($hist, fn($t) => ($now - $t) < $window));
    $hist[] = $now;
    $_SESSION['_checkout_attempts'] = $hist;
    return count($hist) > $max;
}
