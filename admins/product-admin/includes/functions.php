<?php
/**
 * Functions Product Admin
 * Atlantech Shop
 */

require_once __DIR__ . '/config.php';

/**
 * Nettoyer les inputs
 */
function clean_input($data) {
    return htmlspecialchars(strip_tags(trim($data)));
}

/**
 * Formater la date
 */
function format_date($date) {
    $months = [
        1 => 'Jan', 2 => 'Fév', 3 => 'Mar', 4 => 'Avr',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juil', 8 => 'Août',
        9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Déc'
    ];
    
    $timestamp = strtotime($date);
    $day = date('d', $timestamp);
    $month = $months[(int)date('m', $timestamp)];
    $year = date('Y', $timestamp);
    
    return "$day $month $year";
}

/**
 * Formater le prix
 */
function format_price($price) {
    return number_format($price, 2, ',', ' ') . ' HTG';
}

/**
 * Taux de conversion USD → HTG (table atl_settings, clé 'usd_to_htg').
 * Utilisé pour permettre à l'admin de saisir un prix en dollars, converti
 * automatiquement en gourdes avant l'enregistrement en base.
 */
function atl_pa_usd_rate(): float {
    global $pdo;
    static $rate = null;
    if ($rate !== null) return $rate;
    try {
        $st = $pdo->query("SELECT setting_value FROM atl_settings WHERE setting_key = 'usd_to_htg' LIMIT 1");
        $row = $st ? $st->fetch() : null;
        if ($row && (float)$row['setting_value'] > 0) {
            $rate = (float)$row['setting_value'];
            return $rate;
        }
    } catch (\Throwable $e) {
        error_log('atl_pa_usd_rate: ' . $e->getMessage());
    }
    $rate = 130.0;
    return $rate;
}

/**
 * Convertit un montant saisi (HTG ou USD) en gourdes, selon la devise choisie.
 */
function atl_pa_to_htg(float $amount, string $currency): float {
    if ($currency === 'USD') {
        return round($amount * atl_pa_usd_rate(), 2);
    }
    return round($amount, 2);
}

/**
 * Logger une action admin
 */
function log_admin_action(string $action, string $description = '') {
    global $pdo;
    
    if (!isset($_SESSION['admin_id'])) {
        return false;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_activity_logs 
            (admin_id, action, module, description, ip_address, created_at) 
            VALUES (?, ?, 'products', ?, ?, NOW())
        ");

        return $stmt->execute([
            $_SESSION['admin_id'],
            $action,
            $description,
            $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
        ]);

    } catch (PDOException $e) {
        error_log("Erreur log_admin_action : " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir tous les produits avec pagination, recherche, filtres statut et stock
 */
function get_all_products($page = 1, $per_page = 20, $search = '', $category = 0, $status = '', $stock_filter = '') {
    global $pdo;

    $offset = ($page - 1) * $per_page;

    $where  = [];
    $params = [];

    if (!empty($search)) {
        $where[]     = "(p.name LIKE ? OR p.sku LIKE ? OR p.description LIKE ?)";
        $search_term = "%$search%";
        $params[]    = $search_term;
        $params[]    = $search_term;
        $params[]    = $search_term;
    }

    if ($category > 0) {
        $where[]  = "p.category_id = ?";
        $params[] = $category;
    }

    if ($status !== '') {
        if ($status === 'active') {
            $where[] = "p.is_active = 1";
        } elseif ($status === 'inactive') {
            $where[] = "p.is_active = 0";
        }
    }

    // Filtre stock
    if ($stock_filter !== '') {
        switch ($stock_filter) {
            case 'in_stock':
                $where[] = "p.stock > p.stock_threshold";
                break;
            case 'low_stock':
                $where[] = "p.stock > 0 AND p.stock <= p.stock_threshold";
                break;
            case 'out_of_stock':
                $where[] = "p.stock = 0";
                break;
        }
    }

    $where_clause = !empty($where) ? "WHERE " . implode(" AND ", $where) : "";
    
    try {
        // Compter le total
        $count_sql = "SELECT COUNT(*) as total FROM products p $where_clause";
        $stmt = $pdo->prepare($count_sql);
        $stmt->execute($params);
        $total = $stmt->fetch()['total'];
        
        // Récupérer les produits
        $params[] = $offset;
        $params[] = $per_page;
        
        $sql = "SELECT 
                    p.*,
                    c.name as category_name
                FROM products p
                LEFT JOIN categories c ON p.category_id = c.id
                $where_clause
                ORDER BY p.created_at DESC
                LIMIT ?, ?";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll();
        
        return [
            'products' => $products,
            'total' => $total,
            'pages' => ceil($total / $per_page),
            'current_page' => $page
        ];
    } catch (PDOException $e) {
        error_log("Erreur get_all_products : " . $e->getMessage());
        return ['products' => [], 'total' => 0, 'pages' => 0, 'current_page' => 1];
    }
}

/**
 * Obtenir un produit par ID
 */
function get_product_by_id($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("
            SELECT p.*, c.name as category_name, b.name as brand_name
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            LEFT JOIN brands b ON p.brand_id = b.id
            WHERE p.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
        return $stmt->fetch();
    } catch (PDOException $e) {
        error_log("Erreur get_product_by_id : " . $e->getMessage());
        return null;
    }
}

/**
 * Créer un produit
 */
function create_product($data) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            INSERT INTO products (
                name, sku, description, short_description, price, old_price,
                stock, stock_threshold, category_id, brand_id,
                `condition`, product_type, attributes, image, is_active, is_featured, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");

        $ok = $stmt->execute([
            $data['name'],
            $data['sku'],
            $data['description'] ?? null,
            $data['short_description'] ?? null,
            $data['price'],
            $data['old_price'] ?? null,
            $data['stock'],
            $data['stock_threshold'] ?? 5,
            $data['category_id'] ?? null,
            $data['brand_id'] ?? null,
            $data['condition'] ?? 'new',
            $data['product_type'] ?? null,
            isset($data['attributes']) ? (is_string($data['attributes']) ? $data['attributes'] : json_encode($data['attributes'], JSON_UNESCAPED_UNICODE)) : null,
            $data['image'] ?? null,
            $data['is_active'] ?? 1,
            $data['is_featured'] ?? 0
        ]);
        if ($ok) {
            $new_id = (int)$pdo->lastInsertId();
            // Synchroniser product_images avec l'image principale
            if (!empty($data['image'])) {
                $pdo->prepare("DELETE FROM product_images WHERE product_id = ? AND is_primary = 1")->execute([$new_id]);
                $pdo->prepare("INSERT INTO product_images (product_id, image, is_primary) VALUES (?, ?, 1)")->execute([$new_id, $data['image']]);
            }
            return $new_id; // retourne le nouvel ID (truthy)
        }
        return false;
    } catch (PDOException $e) {
        error_log("Erreur create_product : " . $e->getMessage());
        return false;
    }
}

/**
 * Mettre à jour un produit
 */
function update_product($id, $data) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            UPDATE products
            SET name = ?,
                sku = ?,
                description = ?,
                short_description = ?,
                price = ?,
                old_price = ?,
                stock = ?,
                stock_threshold = ?,
                category_id = ?,
                brand_id = ?,
                `condition` = ?,
                product_type = ?,
                attributes = ?,
                image = ?,
                is_active = ?,
                is_featured = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $ok = $stmt->execute([
            $data['name'],
            $data['sku'],
            $data['description'] ?? null,
            $data['short_description'] ?? null,
            $data['price'],
            $data['old_price'] ?? null,
            $data['stock'],
            $data['stock_threshold'] ?? 5,
            $data['category_id'] ?? null,
            $data['brand_id'] ?? null,
            $data['condition'] ?? 'new',
            $data['product_type'] ?? null,
            isset($data['attributes']) ? (is_string($data['attributes']) ? $data['attributes'] : json_encode($data['attributes'], JSON_UNESCAPED_UNICODE)) : null,
            $data['image'] ?? null,
            $data['is_active'] ?? 1,
            $data['is_featured'] ?? 0,
            $id
        ]);
        // Synchroniser product_images avec la nouvelle image principale
        if ($ok && !empty($data['image'])) {
            $pdo->prepare("DELETE FROM product_images WHERE product_id = ? AND is_primary = 1")->execute([$id]);
            $pdo->prepare("INSERT INTO product_images (product_id, image, is_primary) VALUES (?, ?, 1)")->execute([$id, $data['image']]);
        }
        return $ok;
    } catch (PDOException $e) {
        error_log("Erreur update_product : " . $e->getMessage());
        return false;
    }
}

/**
 * Supprimer un produit
 */
function delete_product($id) {
    global $pdo;
    
    try {
        $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
        return $stmt->execute([$id]);
    } catch (PDOException $e) {
        error_log("Erreur delete_product : " . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir toutes les catégories
 */
function get_all_categories() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT * FROM categories 
            WHERE is_active = 1 
            ORDER BY name ASC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur get_all_categories : " . $e->getMessage());
        return [];
    }
}

/**
 * Obtenir toutes les marques
 */
function get_all_brands() {
    global $pdo;
    
    try {
        $stmt = $pdo->query("
            SELECT * FROM brands 
            WHERE is_active = 1 
            ORDER BY name ASC
        ");
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Erreur get_all_brands : " . $e->getMessage());
        return [];
    }
}

/**
 * Upload une image produit
 *
 * SÉCURITÉ : le MIME est vérifié via finfo_file() sur le fichier réel (tmp_name),
 * jamais depuis $_FILES['type'] qui est contrôlé par le client.
 * L'extension est forcée d'après le vrai MIME — jamais depuis le nom du fichier.
 */
function upload_product_image($file) {
    $upload_dir = __DIR__ . '/../../../uploads/products/';

    // Créer le dossier si n'existe pas
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Vérifier la taille (max 5MB) — avant tout traitement
    if ($file['size'] > 5 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Fichier trop volumineux (max 5MB)'];
    }

    // Vérifier que le fichier a bien été uploadé (pas de path traversal)
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return ['success' => false, 'error' => 'Fichier invalide'];
    }

    // ── VRAI MIME depuis le contenu du fichier (finfo) ──────────────────────
    // Ne jamais utiliser $file['type'] — c'est la valeur envoyée par le client,
    // facilement falsifiée. finfo_file() lit le magic bytes réel du fichier.
    $allowed_types = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    $finfo     = new finfo(FILEINFO_MIME_TYPE);
    $real_mime = $finfo->file($file['tmp_name']);

    if (!array_key_exists($real_mime, $allowed_types)) {
        return ['success' => false, 'error' => 'Type de fichier non autorisé (jpeg, png, gif, webp uniquement)'];
    }

    // Extension forcée depuis le vrai MIME — jamais depuis le nom du fichier client
    $extension = $allowed_types[$real_mime];
    $filename  = 'product_' . bin2hex(random_bytes(8)) . '.' . $extension;
    $filepath  = $upload_dir . $filename;

    // Déplacer le fichier
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        return ['success' => true, 'filename' => $filename];
    }

    return ['success' => false, 'error' => "Erreur lors de l'upload"];
}

/**
 * Récupérer toutes les images de la galerie d'un produit
 * Retourne un tableau de lignes product_images (is_primary=1 en premier)
 */
function get_product_gallery($product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT * FROM product_images
             WHERE product_id = ?
             ORDER BY is_primary DESC, id ASC"
        );
        $stmt->execute([$product_id]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log('get_product_gallery: ' . $e->getMessage());
        return [];
    }
}

/**
 * Ajouter une image à la galerie d'un produit
 * Si is_primary=0 et qu'il n'existe aucune image principale, cette image devient principale automatiquement
 */
function add_gallery_image($product_id, $filename, $is_primary = 0) {
    global $pdo;
    try {
        // Auto-promouvoir si aucune image principale n'existe encore
        if (!$is_primary) {
            $chk = $pdo->prepare(
                "SELECT id FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1"
            );
            $chk->execute([$product_id]);
            if (!$chk->fetch()) {
                $is_primary = 1;
            }
        } else {
            // Retirer l'ancienne image principale
            $pdo->prepare(
                "DELETE FROM product_images WHERE product_id = ? AND is_primary = 1"
            )->execute([$product_id]);
        }
        $stmt = $pdo->prepare(
            "INSERT INTO product_images (product_id, image, is_primary) VALUES (?, ?, ?)"
        );
        $stmt->execute([$product_id, $filename, $is_primary ? 1 : 0]);
        return true;
    } catch (PDOException $e) {
        error_log('add_gallery_image: ' . $e->getMessage());
        return false;
    }
}

/**
 * Supprimer une image de la galerie (par ID)
 * Si c'était l'image principale, promeut automatiquement la suivante
 */
function delete_gallery_image_by_id($image_id, $product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT image, is_primary FROM product_images WHERE id = ? AND product_id = ?"
        );
        $stmt->execute([$image_id, $product_id]);
        $row = $stmt->fetch();
        if (!$row) return false;

        // Supprimer la ligne DB
        $pdo->prepare(
            "DELETE FROM product_images WHERE id = ? AND product_id = ?"
        )->execute([$image_id, $product_id]);

        // Supprimer le fichier physique
        $file_path = __DIR__ . '/../../../uploads/products/' . $row['image'];
        if (file_exists($file_path)) {
            @unlink($file_path);
        }

        // Si c'était l'image principale, mettre à jour products.image et promouvoir la suivante
        if ($row['is_primary']) {
            $pdo->prepare("UPDATE products SET image = NULL WHERE id = ?")->execute([$product_id]);
            $next = $pdo->prepare(
                "SELECT id, image FROM product_images WHERE product_id = ? ORDER BY id ASC LIMIT 1"
            );
            $next->execute([$product_id]);
            $next_img = $next->fetch();
            if ($next_img) {
                $pdo->prepare(
                    "UPDATE product_images SET is_primary = 1 WHERE id = ?"
                )->execute([$next_img['id']]);
                $pdo->prepare(
                    "UPDATE products SET image = ? WHERE id = ?"
                )->execute([$next_img['image'], $product_id]);
            }
        }
        return true;
    } catch (PDOException $e) {
        error_log('delete_gallery_image_by_id: ' . $e->getMessage());
        return false;
    }
}

/**
 * Obtenir le nom de fichier de l'image principale d'un produit
 */
function get_primary_image_filename($product_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare(
            "SELECT image FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1"
        );
        $stmt->execute([$product_id]);
        $row = $stmt->fetch();
        return $row ? $row['image'] : null;
    } catch (PDOException $e) {
        error_log('get_primary_image_filename: ' . $e->getMessage());
        return null;
    }
}

// ════════════════════════════════════════════════════════════════════
//  COULEURS DES PRODUITS
// ════════════════════════════════════════════════════════════════════

/**
 * Récupère toutes les couleurs disponibles depuis la table `colors`.
 *
 * @return array Liste de [id, name, hex_code]
 */
function get_all_colors(): array {
    global $pdo;
    try {
        return $pdo->query("SELECT id, name, hex_code FROM colors ORDER BY name ASC")->fetchAll();
    } catch (\Throwable $e) {
        error_log('get_all_colors: ' . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les IDs de couleurs liées à un produit (via product_colors).
 *
 * @return int[]  Tableau d'IDs (vide si aucune)
 */
function get_product_color_ids(int $product_id): array {
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT color_id FROM product_colors WHERE product_id = ?");
        $st->execute([$product_id]);
        return array_map('intval', $st->fetchAll(PDO::FETCH_COLUMN));
    } catch (\Throwable $e) {
        error_log('get_product_color_ids: ' . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les couleurs (id + name + hex + price) d'un produit.
 * `price` est le prix absolu de la couleur (NULL = prix de base du produit).
 *
 * @return array  Liste de [id, name, hex_code, price]
 */
function get_product_colors(int $product_id): array {
    global $pdo;
    try {
        $st = $pdo->prepare("
            SELECT c.id, c.name, c.hex_code, pc.price
            FROM product_colors pc
            JOIN colors c ON c.id = pc.color_id
            WHERE pc.product_id = ?
            ORDER BY c.name ASC
        ");
        $st->execute([$product_id]);
        return $st->fetchAll();
    } catch (\Throwable $e) {
        error_log('get_product_colors: ' . $e->getMessage());
        return [];
    }
}

/**
 * Récupère les prix par couleur d'un produit, indexés par color_id.
 *
 * @return array  [color_id => price|null]
 */
function get_product_color_prices(int $product_id): array {
    global $pdo;
    try {
        $st = $pdo->prepare("SELECT color_id, price FROM product_colors WHERE product_id = ?");
        $st->execute([$product_id]);
        $out = [];
        foreach ($st->fetchAll() as $row) {
            $out[(int)$row['color_id']] = ($row['price'] !== null) ? (float)$row['price'] : null;
        }
        return $out;
    } catch (\Throwable $e) {
        error_log('get_product_color_prices: ' . $e->getMessage());
        return [];
    }
}

/**
 * Remplace ENTIÈREMENT le set de couleurs (+ prix) d'un produit.
 * Atomique : delete + insert dans une transaction.
 *
 * @param int   $product_id
 * @param int[] $color_ids   Liste d'IDs de couleurs
 * @param array $color_prices [color_id => prix|''|null]  (prix absolu ; vide/0/null = prix de base)
 * @return bool
 */
function set_product_colors(int $product_id, array $color_ids, array $color_prices = []): bool {
    global $pdo;
    // Nettoyer + dédupliquer
    $clean = array_unique(array_filter(array_map('intval', $color_ids), fn($v) => $v > 0));

    try {
        $pdo->beginTransaction();
        $pdo->prepare("DELETE FROM product_colors WHERE product_id = ?")->execute([$product_id]);
        if (!empty($clean)) {
            $ins = $pdo->prepare("INSERT IGNORE INTO product_colors (product_id, color_id, price) VALUES (?, ?, ?)");
            foreach ($clean as $cid) {
                // Prix : si fourni et > 0, on le garde ; sinon NULL (= prix de base)
                $raw = $color_prices[$cid] ?? null;
                $price = ($raw !== null && $raw !== '' && (float)$raw > 0) ? (float)$raw : null;
                $ins->execute([$product_id, $cid, $price]);
            }
        }
        $pdo->commit();
        return true;
    } catch (\Throwable $e) {
        try { $pdo->rollBack(); } catch (\Throwable $_) {}
        error_log('set_product_colors: ' . $e->getMessage());
        return false;
    }
}
