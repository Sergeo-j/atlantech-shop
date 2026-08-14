<?php
/**
 * AtlanTech — Compteurs wishlist et cart pour le header
 *
 * Fournit deux variables prêtes à l'emploi pour les badges du header :
 *   - $wishlist_count : nombre d'articles dans la wishlist du user connecté
 *   - $cart_count     : nombre total d'articles dans le panier
 *                        (somme des quantités, pas nombre de lignes)
 *
 * Logique :
 *   - Si l'utilisateur est connecté, on prend la vérité de la BD (tables
 *     wishlist + cart) — comme ça les compteurs restent corrects même
 *     entre deux appareils ou après une déconnexion/reconnexion.
 *   - Si pas connecté, on retombe sur $_SESSION['cart'] (achat invité)
 *     et wishlist = 0 (la wishlist nécessite un compte).
 *
 * Usage : require_once __DIR__ . '/includes/header_counters.php';
 *         puis utiliser $wishlist_count et $cart_count dans le HTML.
 *
 * Pré-requis : $mysqli doit déjà être disponible (via config/config.php).
 * Le fichier est tolérant : si une table manque ou si la connexion BD
 * est cassée, il renvoie 0 sans planter la page.
 */

if (!isset($wishlist_count)) $wishlist_count = 0;
if (!isset($cart_count))     $cart_count     = 0;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$_atl_uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

// ── Wishlist ────────────────────────────────────────────────────────
// La wishlist est stockée en $_SESSION['wishlist'] (tableau d'IDs produits)
// par wishlist.php — on compte directement depuis la session.
$wishlist_count = isset($_SESSION['wishlist']) && is_array($_SESSION['wishlist'])
    ? count($_SESSION['wishlist'])
    : 0;

// ── Cart ────────────────────────────────────────────────────────────
// Pour le cart, on prend la somme des quantités plutôt que le nombre
// de lignes (UX plus parlante : "5 articles" plutôt que "2 produits").
if ($_atl_uid > 0 && isset($mysqli) && $mysqli instanceof mysqli) {
    // Utilisateur connecté → priorité à la BD
    try {
        $stmt = $mysqli->prepare("SELECT COALESCE(SUM(quantity), 0) AS n FROM cart WHERE user_id = ?");
        if ($stmt) {
            $stmt->bind_param('i', $_atl_uid);
            $stmt->execute();
            $r = $stmt->get_result()->fetch_assoc();
            $cart_count = (int)($r['n'] ?? 0);
            $stmt->close();
        }
    } catch (\Throwable $e) {
        error_log('header_counters cart (DB): ' . $e->getMessage());
        $cart_count = 0;
    }

    // Si la BD ne renvoie rien mais qu'on a une session active,
    // fallback sur la session (cas où le panier est uniquement en session)
    if ($cart_count === 0 && !empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $line) {
            $cart_count += max(1, (int)($line['qty'] ?? 1));
        }
    }
} else {
    // Visiteur non connecté → uniquement la session
    if (!empty($_SESSION['cart']) && is_array($_SESSION['cart'])) {
        foreach ($_SESSION['cart'] as $line) {
            $cart_count += max(1, (int)($line['qty'] ?? 1));
        }
    }
}

unset($_atl_uid);
