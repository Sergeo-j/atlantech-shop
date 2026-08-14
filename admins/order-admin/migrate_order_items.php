<?php
/**
 * Migration : ajout des colonnes product_name, product_image, unit_price, total_price
 * à la table order_items.
 * Exécuter UNE SEULE FOIS puis supprimer ce fichier.
 */
require_once __DIR__ . '/includes/config.php';

$migrations = [
    "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS product_name  VARCHAR(255) NOT NULL DEFAULT '' AFTER product_id",
    "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS product_image VARCHAR(255)          DEFAULT NULL AFTER product_name",
    "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS unit_price    DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER product_image",
    "ALTER TABLE order_items ADD COLUMN IF NOT EXISTS total_price   DECIMAL(10,2) NOT NULL DEFAULT 0.00 AFTER unit_price",
    // remplir unit_price depuis price (colonne existante) si besoin
    "UPDATE order_items SET unit_price = price WHERE unit_price = 0 AND price > 0",
    "UPDATE order_items SET total_price = price * quantity WHERE total_price = 0",
    // remplir product_name depuis products
    "UPDATE order_items oi JOIN products p ON oi.product_id = p.id SET oi.product_name = p.name, oi.product_image = p.image WHERE oi.product_name = ''",
];

echo "<pre style='font-family:monospace;background:#1e1e2e;color:#e2e8f0;padding:20px;border-radius:8px;'>";
echo "=== Migration order_items ===\n\n";

foreach ($migrations as $sql) {
    try {
        $pdo->exec($sql);
        echo "✅ OK : " . substr($sql, 0, 80) . "...\n";
    } catch (PDOException $e) {
        // IF NOT EXISTS peut ne pas être supporté sur vieilles versions MySQL
        if (str_contains($e->getMessage(), 'Duplicate column')) {
            echo "⏭  Déjà existant : " . substr($sql, 0, 60) . "\n";
        } else {
            echo "❌ ERREUR : " . $e->getMessage() . "\n";
            echo "   SQL : $sql\n";
        }
    }
}

echo "\n✅ Migration terminée.\n";
echo "⚠️  Supprimez ce fichier après utilisation.\n";
echo "</pre>";
?>
