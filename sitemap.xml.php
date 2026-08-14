<?php
/**
 * Sitemap XML Dynamique — AtlanTech Shop
 * Génère le sitemap depuis les produits et catégories en base de données.
 * Accessible via : /sitemap.xml (règle RewriteRule dans .htaccess ou accès direct)
 *
 * @security: aucune donnée utilisateur, lecture seule en DB
 */

// ── Pas d'affichage d'erreurs en production ──────────────────────────
ini_set('display_errors', '0');
error_reporting(0);

require_once __DIR__ . '/config/config.php';

// URL de base du site
$base_url = rtrim(env('SITE_URL', 'http://localhost/atlantech-shop'), '/');

header('Content-Type: application/xml; charset=UTF-8');
header('X-Robots-Tag: noindex');

// ── Pages statiques ───────────────────────────────────────────────────
$static_pages = [
    ['url' => '/',          'changefreq' => 'daily',   'priority' => '1.0'],
    ['url' => '/shop.php',  'changefreq' => 'daily',   'priority' => '0.9'],
    ['url' => '/about.php', 'changefreq' => 'monthly', 'priority' => '0.5'],
    ['url' => '/contact.php','changefreq' => 'monthly','priority' => '0.5'],
    ['url' => '/promotions.php','changefreq' => 'weekly','priority' => '0.7'],
];

// ── Produits actifs ───────────────────────────────────────────────────
$products = [];
try {
    $stmt = $mysqli->prepare(
        "SELECT id, updated_at, created_at FROM products WHERE is_active = 1 ORDER BY updated_at DESC LIMIT 5000"
    );
    $stmt->execute();
    $products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (\Throwable $e) {
    error_log('sitemap.xml.php products: ' . $e->getMessage());
}

// ── Catégories actives ────────────────────────────────────────────────
$categories = [];
try {
    $stmt = $mysqli->query("SELECT id FROM categories WHERE is_active = 1");
    $categories = $stmt->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (\Throwable $e) {
    error_log('sitemap.xml.php categories: ' . $e->getMessage());
}

// ── Génération XML ────────────────────────────────────────────────────
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

// Pages statiques
foreach ($static_pages as $page) {
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($base_url . $page['url'], ENT_XML1) . "</loc>\n";
    echo "    <changefreq>{$page['changefreq']}</changefreq>\n";
    echo "    <priority>{$page['priority']}</priority>\n";
    echo "  </url>\n";
}

// Produits
foreach ($products as $p) {
    $lastmod = date('Y-m-d', strtotime($p['updated_at'] ?: $p['created_at']));
    $url     = $base_url . '/product.php?id=' . (int)$p['id'];
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($url, ENT_XML1) . "</loc>\n";
    echo "    <lastmod>{$lastmod}</lastmod>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.8</priority>\n";
    echo "  </url>\n";
}

// Catégories
foreach ($categories as $c) {
    $url = $base_url . '/shop.php?category=' . (int)$c['id'];
    echo "  <url>\n";
    echo "    <loc>" . htmlspecialchars($url, ENT_XML1) . "</loc>\n";
    echo "    <changefreq>weekly</changefreq>\n";
    echo "    <priority>0.7</priority>\n";
    echo "  </url>\n";
}

echo '</urlset>' . "\n";
