<?php
/**
 * Bannière promo — AtlanTech
 *
 * À inclure juste après <body> dans les pages publiques (index, shop, etc.).
 * Affiche le code promo actif le plus avantageux (% le plus élevé).
 * Cachée si aucun code actif ou si l'utilisateur l'a déjà fermée (cookie).
 *
 * Dépendances : $mysqli (depuis config/config.php déjà chargé)
 */

if (!isset($mysqli) || !($mysqli instanceof mysqli)) {
    return; // sécurité : ne rien afficher si pas de connexion BD
}

// Si le visiteur a fermé la bannière récemment, on ne la réaffiche pas pendant 24h
if (isset($_COOKIE['promo_banner_closed']) && $_COOKIE['promo_banner_closed'] === '1') {
    return;
}

// Chercher le meilleur code actif courant
$promo = null;
try {
    $today = date('Y-m-d');
    $st = $mysqli->prepare("
        SELECT code, description, discount_percent, valid_until
        FROM promo_codes
        WHERE is_active = 1
          AND (valid_from  IS NULL OR valid_from  <= ?)
          AND (valid_until IS NULL OR valid_until >= ?)
        ORDER BY discount_percent DESC, valid_until ASC
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
    error_log('promo_banner: ' . $e->getMessage());
}

if (!$promo) return; // aucun code disponible → on n'affiche rien

$code     = htmlspecialchars($promo['code']);
$percent  = (int) round((float)$promo['discount_percent']);
$desc     = htmlspecialchars($promo['description'] ?? '');
$validity = '';
if (!empty($promo['valid_until'])) {
    $validity = " · jusqu'au " . date('d/m/Y', strtotime($promo['valid_until']));
}
?>
<div id="atl-promo-banner" style="
    position:relative;
    background: linear-gradient(135deg, #6d28d9, #f59e0b);
    color: #fff;
    text-align: center;
    padding: 12px 50px 12px 20px;
    font-family: 'Inter', 'Segoe UI', sans-serif;
    font-size: 0.95rem;
    font-weight: 600;
    z-index: 1000;
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
">
    <span style="display:inline-flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:center;">
        <span style="font-size:1.1em">🎉</span>
        <?= $desc ?: 'Offre spéciale' ?> :
        <strong style="background:rgba(0,0,0,0.25); padding:3px 12px; border-radius:14px; font-family:monospace; letter-spacing:1px;"><?= $code ?></strong>
        <span style="background:#fff; color:#6d28d9; padding:3px 10px; border-radius:14px; font-weight:800;">–<?= $percent ?>%</span>
        <button type="button" id="atl-promo-copy"
            onclick="
                navigator.clipboard.writeText('<?= addslashes($code) ?>').then(function(){
                    var b = document.getElementById('atl-promo-copy');
                    var orig = b.innerHTML;
                    b.innerHTML = '✓ Copié';
                    setTimeout(function(){ b.innerHTML = orig; }, 1500);
                });
            "
            style="background:rgba(255,255,255,0.2); color:#fff; border:1px solid rgba(255,255,255,0.4); padding:4px 12px; border-radius:14px; cursor:pointer; font-weight:600; font-size:0.85rem;">
            📋 Copier
        </button>
        <span style="opacity:0.85; font-weight:400; font-size:0.85rem;"><?= $validity ?></span>
    </span>
    <button type="button" id="atl-promo-close" aria-label="Fermer"
        onclick="
            document.cookie = 'promo_banner_closed=1; max-age=86400; path=/';
            document.getElementById('atl-promo-banner').style.display='none';
        "
        style="position:absolute; top:50%; right:14px; transform:translateY(-50%); background:transparent; border:none; color:#fff; font-size:1.4rem; cursor:pointer; line-height:1; padding:0 8px; opacity:0.85;">
        ×
    </button>
</div>
