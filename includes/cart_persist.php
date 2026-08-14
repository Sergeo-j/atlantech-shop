<?php
/**
 * Persistance du panier en base de données — AtlanTech
 *
 * - À chaque fin de requête (cart.php / checkout.php), le panier session
 *   d'un utilisateur connecté est sauvegardé dans la table `cart`.
 * - À la connexion (account.php), le panier sauvegardé est rechargé et
 *   fusionné avec le panier session courant (la session prime).
 */

if (!function_exists('cart_db_save')) {

    function cart_db_save(mysqli $mysqli, int $uid): void
    {
        if ($uid <= 0) return;
        $cart = $_SESSION['cart'] ?? [];

        $stmt = $mysqli->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $stmt->close();

        if (empty($cart)) return;

        $stmt = $mysqli->prepare("INSERT INTO cart (user_id, product_id, quantity) VALUES (?, ?, ?)");
        foreach ($cart as $pid => $it) {
            $p = (int)$pid;
            $q = max(1, (int)($it['qty'] ?? 1));
            if ($p > 0) {
                $stmt->bind_param('iii', $uid, $p, $q);
                $stmt->execute();
            }
        }
        $stmt->close();
    }

    function cart_db_load(mysqli $mysqli, int $uid): void
    {
        if ($uid <= 0) return;
        $stmt = $mysqli->prepare("
            SELECT c.product_id, c.quantity, p.name, p.price, p.image, p.stock
            FROM cart c
            JOIN products p ON p.id = c.product_id AND p.is_active = 1
            WHERE c.user_id = ?");
        $stmt->bind_param('i', $uid);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }

        foreach ($rows as $r) {
            $pid = (int)$r['product_id'];
            if (isset($_SESSION['cart'][$pid])) continue; // le panier session courant prime
            $stock = max(0, (int)$r['stock']);
            if ($stock === 0) continue; // produit épuisé entre-temps
            $_SESSION['cart'][$pid] = [
                'name'       => $r['name'],
                'price'      => (float)$r['price'],
                'image'      => $r['image'],
                'stock'      => $stock,
                'qty'        => min(max(1, (int)$r['quantity']), $stock),
                'color_id'   => null,
                'color_name' => null,
            ];
        }
    }

    function cart_db_autosave(): void
    {
        if (empty($_SESSION['user_id'])) return;
        global $mysqli;
        if (!($mysqli instanceof mysqli)) return;
        try {
            cart_db_save($mysqli, (int)$_SESSION['user_id']);
        } catch (Throwable $e) {
            error_log('cart_db_autosave: ' . $e->getMessage());
        }
    }
}

// Sauvegarde automatique en fin de requête sur les pages qui incluent ce fichier
register_shutdown_function('cart_db_autosave');
