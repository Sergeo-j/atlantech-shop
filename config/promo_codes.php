<?php
/**
 * Helpers Codes Promo — AtlanTech
 *
 * Logique métier partagée entre :
 *   - Le checkout (validation + application)
 *   - Les interfaces admin (DG, marketing) qui gèrent la table
 *
 * Toutes les fonctions sont SAFE : si la table n'existe pas (migration non
 * appliquée), elles retournent un résultat neutre sans crasher.
 */

/**
 * Valide un code promo : existe, actif, dans la période, et calcule la réduction.
 *
 * @param  PDO    $pdo
 * @param  string $code      Le code saisi (sera normalisé : trim + upper)
 * @param  float  $subtotal  Sous-total panier (avant livraison), pour calcul du montant
 * @return array  Résultat structuré :
 *    [
 *      'valid'           => bool,
 *      'error'           => string|null,
 *      'code'            => string (normalisé) ou '',
 *      'description'     => string|'',
 *      'discount_percent'=> float,
 *      'discount_amount' => float (HTG arrondi à 2 décimales),
 *      'promo_id'        => int|null,
 *    ]
 */
function promo_validate(PDO $pdo, string $code, float $subtotal): array
{
    $code   = strtoupper(trim($code));
    $result = [
        'valid'            => false,
        'error'            => null,
        'code'             => $code,
        'description'      => '',
        'discount_percent' => 0.0,
        'discount_amount'  => 0.0,
        'promo_id'         => null,
    ];

    if ($code === '') {
        return $result; // pas d'erreur, juste pas de code (silencieux)
    }

    try {
        $st = $pdo->prepare("
            SELECT id, code, description, discount_percent, valid_from, valid_until, is_active
            FROM promo_codes
            WHERE code = ? COLLATE utf8mb4_general_ci
            LIMIT 1
        ");
        $st->execute([$code]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log('promo_validate: ' . $e->getMessage());
        $result['error'] = "Le système de codes promo est temporairement indisponible.";
        return $result;
    }

    if (!$row) {
        $result['error'] = "Ce code n'existe pas.";
        return $result;
    }
    if (!$row['is_active']) {
        $result['error'] = "Ce code n'est plus actif.";
        return $result;
    }

    $today = date('Y-m-d');
    if ($row['valid_from']  && $today < $row['valid_from'])  {
        $result['error'] = "Ce code n'est pas encore valide (à partir du " . date('d/m/Y', strtotime($row['valid_from'])) . ").";
        return $result;
    }
    if ($row['valid_until'] && $today > $row['valid_until']) {
        $result['error'] = "Ce code a expiré le " . date('d/m/Y', strtotime($row['valid_until'])) . ".";
        return $result;
    }

    $percent = (float) $row['discount_percent'];
    $amount  = round($subtotal * $percent / 100, 2);

    $result['valid']            = true;
    $result['description']      = $row['description'] ?? '';
    $result['discount_percent'] = $percent;
    $result['discount_amount']  = $amount;
    $result['promo_id']         = (int) $row['id'];
    return $result;
}

/**
 * Incrémente le compteur d'utilisations d'un code (à appeler après commande validée).
 * SAFE : silencieuse si la table n'existe pas ou si le code n'existe plus.
 */
function promo_increment_usage(PDO $pdo, int $promo_id): void
{
    if ($promo_id <= 0) return;
    try {
        $pdo->prepare("UPDATE promo_codes SET usage_count = usage_count + 1 WHERE id = ?")
            ->execute([$promo_id]);
    } catch (PDOException $e) {
        error_log('promo_increment_usage: ' . $e->getMessage());
    }
}

/**
 * Variante MySQLi (pour le checkout qui utilise mysqli, pas PDO).
 */
function promo_validate_mysqli(mysqli $mysqli, string $code, float $subtotal): array
{
    $code   = strtoupper(trim($code));
    $result = [
        'valid'            => false,
        'error'            => null,
        'code'             => $code,
        'description'      => '',
        'discount_percent' => 0.0,
        'discount_amount'  => 0.0,
        'promo_id'         => null,
    ];
    if ($code === '') return $result;

    try {
        $st = $mysqli->prepare("
            SELECT id, code, description, discount_percent, valid_from, valid_until, is_active
            FROM promo_codes
            WHERE code = ? COLLATE utf8mb4_general_ci
            LIMIT 1
        ");
        if (!$st) {
            $result['error'] = "Système indisponible.";
            return $result;
        }
        $st->bind_param('s', $code);
        $st->execute();
        $res = $st->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $st->close();
    } catch (\Throwable $e) {
        error_log('promo_validate_mysqli: ' . $e->getMessage());
        $result['error'] = "Système indisponible.";
        return $result;
    }

    if (!$row) {
        $result['error'] = "Ce code n'existe pas.";
        return $result;
    }
    if (!$row['is_active']) {
        $result['error'] = "Ce code n'est plus actif.";
        return $result;
    }

    $today = date('Y-m-d');
    if ($row['valid_from']  && $today < $row['valid_from'])  {
        $result['error'] = "Ce code n'est pas encore valide (à partir du " . date('d/m/Y', strtotime($row['valid_from'])) . ").";
        return $result;
    }
    if ($row['valid_until'] && $today > $row['valid_until']) {
        $result['error'] = "Ce code a expiré le " . date('d/m/Y', strtotime($row['valid_until'])) . ".";
        return $result;
    }

    $percent = (float) $row['discount_percent'];
    $amount  = round($subtotal * $percent / 100, 2);
    $result['valid']            = true;
    $result['description']      = $row['description'] ?? '';
    $result['discount_percent'] = $percent;
    $result['discount_amount']  = $amount;
    $result['promo_id']         = (int) $row['id'];
    return $result;
}

/**
 * Variante MySQLi du incrément usage_count.
 */
function promo_increment_usage_mysqli(mysqli $mysqli, int $promo_id): void
{
    if ($promo_id <= 0) return;
    try {
        $st = $mysqli->prepare("UPDATE promo_codes SET usage_count = usage_count + 1 WHERE id = ?");
        if ($st) {
            $st->bind_param('i', $promo_id);
            $st->execute();
            $st->close();
        }
    } catch (\Throwable $e) {
        error_log('promo_increment_usage_mysqli: ' . $e->getMessage());
    }
}
