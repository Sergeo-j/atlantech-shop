<?php
/**
 * Fiche Produit (Lecture seule)
 * Product Admin - Atlantech Shop
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$page_title  = 'Fiche Produit';
$current_page = 'products';

// Vérifier l'ID
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($product_id <= 0) {
    header('Location: products-list.php?error=invalid_id');
    exit();
}

// Charger le produit
$product = get_product_by_id($product_id);
if (!$product) {
    header('Location: products-list.php?error=not_found');
    exit();
}

// Galerie d'images
$gallery = get_product_gallery($product_id);

// Décoder les attributs JSON
$product_attrs = [];
if (!empty($product['attributes'])) {
    $decoded = json_decode(is_string($product['attributes']) ? $product['attributes'] : json_encode($product['attributes']), true);
    if (is_array($decoded)) $product_attrs = $decoded;
}

$ATTR_LABELS = [
    'genre'=>'Genre','tailles'=>'Tailles','matiere'=>'Matière',
    'sous_type'=>'Sous-catégorie','modele'=>'Modèle','reference'=>'Référence fabricant',
    'couleur'=>'Couleur(s)','ecran'=>'Taille écran','resolution'=>'Résolution',
    'ram'=>'RAM','stockage'=>'Stockage','processeur'=>'Processeur',
    'batterie'=>'Batterie','camera_avant'=>'Caméra avant','camera_arriere'=>'Caméra arrière',
    'sim'=>'SIM','reseau'=>'Réseau','garantie'=>'Garantie','pays_origine'=>"Pays d'origine",
    'connectivite'=>'Connectivité','os'=>'OS','gpu'=>'GPU','affichage'=>'Affichage',
    'ports'=>'Ports','clavier'=>'Clavier','touchpad'=>'Touchpad','autonomie'=>'Autonomie',
    'poids'=>'Poids','dimensions'=>'Dimensions','type_son'=>'Type','puissance'=>'Puissance',
    'impedance'=>'Impédance','frequence'=>'Réponse fréq.','bluetooth'=>'Bluetooth',
    'jack'=>'Jack 3.5mm','anc'=>'Réduction bruit','duree_batterie'=>'Autonomie',
    'type_tv'=>'Type TV','taille_ecran'=>'Taille écran','smart'=>'Smart TV',
    'hdr'=>'HDR','refresh_rate'=>'Taux rafraîch.','hdmi'=>'HDMI',
    'megapixels'=>'Mégapixels','zoom'=>'Zoom optique','stabilisation'=>'Stabilisation',
    'video'=>'Vidéo max','objectif'=>'Objectif','monture'=>'Monture',
    'type_console'=>'Plateforme','stockage_interne'=>'Stockage interne',
    'manettes'=>'Manettes incluses','jeux_inclus'=>'Jeux inclus','online'=>'Online',
    'type_frigo'=>'Type','volume'=>'Volume total','volume_congelateur'=>'Vol. congélateur',
    'degivrage'=>'Dégivrage','classe_energie'=>'Classe énergie','couleur_app'=>'Couleur',
    'largeur'=>'Largeur','hauteur'=>'Hauteur','profondeur'=>'Profondeur',
    'puissance_machine'=>'Puissance','type_moteur'=>'Type moteur','capacite'=>'Capacité',
    'programmes'=>'Programmes','essorage'=>'Essorage','energie'=>'Énergie',
    'surface'=>'Surface cuisson','type_livre'=>'Genre','auteur'=>'Auteur',
    'editeur'=>'Éditeur','isbn'=>'ISBN','langue'=>'Langue','pages'=>'Pages','edition'=>'Édition',
];

$ATTR_TYPE_LABELS = [
    'smartphone'=>'Smartphone','laptop'=>'Ordinateur portable','audio'=>'Audio',
    'tv'=>'Télévision','camera'=>'Appareil photo','gaming'=>'Gaming / Console',
    'electromenager'=>'Électroménager','vetement'=>'Vêtement / Mode',
    'livre'=>'Livre / Média','accessoire'=>'Accessoire','tablette'=>'Tablette','autre'=>'Autre',
];

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ---- Layout général ---- */
.view-grid {
    display: grid;
    grid-template-columns: 320px 1fr;
    gap: 24px;
    align-items: start;
}

/* ---- Colonne gauche : galerie ---- */
.gallery-main-wrap {
    position: sticky;
    top: 20px;
}

.gallery-main-img-box {
    width: 100%;
    aspect-ratio: 1;
    background: var(--bg-secondary);
    border: 1px solid var(--border-color);
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.gallery-main-img-box img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: opacity 0.2s;
}

.gallery-main-img-box .no-image-icon {
    font-size: 64px;
    color: var(--text-secondary);
    opacity: 0.3;
}

.gallery-thumbs {
    display: flex;
    gap: 10px;
    margin-top: 12px;
    flex-wrap: wrap;
}

.gallery-thumb {
    width: 60px;
    height: 60px;
    border-radius: 8px;
    overflow: hidden;
    border: 2px solid transparent;
    cursor: pointer;
    transition: border-color 0.2s, opacity 0.2s;
    background: var(--bg-secondary);
}

.gallery-thumb img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.gallery-thumb:hover,
.gallery-thumb.active {
    border-color: var(--neon-cyan);
}

/* ---- Colonne droite : infos ---- */
.product-title-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 8px;
    flex-wrap: wrap;
}

.product-main-title {
    font-size: 22px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.3;
}

.badge-row {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.badge-active {
    background: rgba(0, 255, 136, 0.1);
    color: var(--neon-green);
    border: 1px solid var(--neon-green);
}

.badge-inactive {
    background: rgba(255, 0, 0, 0.1);
    color: #ff5555;
    border: 1px solid #ff5555;
}

.badge-featured {
    background: rgba(255, 215, 0, 0.1);
    color: var(--neon-gold);
    border: 1px solid var(--neon-gold);
}

.badge-cat {
    background: rgba(138, 43, 226, 0.15);
    color: #b77fe8;
    border: 1px solid rgba(138, 43, 226, 0.4);
}

.badge-brand {
    background: rgba(0, 217, 255, 0.1);
    color: var(--neon-cyan);
    border: 1px solid rgba(0, 217, 255, 0.3);
}

/* Prix */
.price-box {
    display: flex;
    align-items: baseline;
    gap: 14px;
    margin-bottom: 20px;
    padding: 16px 20px;
    background: rgba(0, 217, 255, 0.05);
    border: 1px solid rgba(0, 217, 255, 0.15);
    border-radius: 10px;
}

.price-current {
    font-size: 28px;
    font-weight: 700;
    color: var(--neon-cyan);
}

.price-old {
    font-size: 16px;
    color: var(--text-secondary);
    text-decoration: line-through;
}

.price-discount {
    font-size: 13px;
    padding: 2px 8px;
    border-radius: 8px;
    background: rgba(0, 255, 136, 0.15);
    color: var(--neon-green);
    font-weight: 600;
}

/* Stock */
.stock-row {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-bottom: 20px;
}

.stock-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.stock-ok {
    background: rgba(0, 255, 136, 0.1);
    color: var(--neon-green);
    border: 1px solid var(--neon-green);
}

.stock-low {
    background: rgba(255, 215, 0, 0.1);
    color: var(--neon-gold);
    border: 1px solid var(--neon-gold);
}

.stock-out {
    background: rgba(255, 0, 0, 0.1);
    color: #ff5555;
    border: 1px solid #ff5555;
}

.stock-number {
    font-size: 15px;
    color: var(--text-secondary);
}

/* Section infos */
.info-section {
    margin-bottom: 20px;
    padding-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
}

.info-section:last-child {
    border-bottom: none;
    margin-bottom: 0;
    padding-bottom: 0;
}

.info-section-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin-bottom: 10px;
    display: flex;
    align-items: center;
    gap: 7px;
}

.info-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 12px;
}

.info-item {
    background: rgba(255,255,255,0.03);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    padding: 10px 14px;
}

.info-item-label {
    font-size: 11px;
    color: var(--text-secondary);
    margin-bottom: 3px;
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

.info-item-value {
    font-size: 14px;
    font-weight: 500;
    color: var(--text-primary);
}

.description-text {
    color: var(--text-secondary);
    line-height: 1.7;
    font-size: 14px;
    white-space: pre-wrap;
}

/* SKU badge */
.sku-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
    padding: 4px 10px;
    border-radius: 6px;
    background: rgba(0, 217, 255, 0.1);
    color: var(--neon-cyan);
    border: 1px solid rgba(0, 217, 255, 0.25);
    font-family: monospace;
    font-weight: 600;
    letter-spacing: 0.05em;
    margin-bottom: 12px;
    display: inline-block;
}

/* Actions */
.view-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
    padding-top: 20px;
    border-top: 1px solid var(--border-color);
    margin-top: 20px;
}

/* Meta badge (header) */
.product-meta-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    padding: 4px 10px;
    border-radius: 20px;
    background: rgba(0, 229, 255, 0.12);
    color: var(--neon-cyan);
    border: 1px solid rgba(0, 229, 255, 0.25);
    margin-right: 8px;
    margin-bottom: 8px;
}

.page-header-meta {
    display: flex;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
    margin-bottom: 8px;
}

/* dates */
.dates-row {
    display: flex;
    gap: 20px;
    flex-wrap: wrap;
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 6px;
}

.dates-row span {
    display: flex;
    align-items: center;
    gap: 5px;
}

@media (max-width: 900px) {
    .view-grid {
        grid-template-columns: 1fr;
    }
    .gallery-main-wrap {
        position: static;
    }
    .info-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- En-tête -->
<div class="page-header">
    <div>
        <div class="page-header-meta">
            <h1><i class="fas fa-eye"></i> Fiche Produit</h1>
        </div>
        <div>
            <span class="product-meta-badge">
                <i class="fas fa-hashtag"></i> ID : <?php echo $product_id; ?>
            </span>
            <span class="product-meta-badge">
                <i class="fas fa-barcode"></i> <?php echo htmlspecialchars($product['sku'] ?? '—'); ?>
            </span>
            <?php if (!empty($product['category_name'])): ?>
            <span class="product-meta-badge">
                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name']); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <a href="products-list.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Retour
        </a>
        <a href="product-edit.php?id=<?php echo $product_id; ?>" class="btn btn-primary">
            <i class="fas fa-edit"></i> Modifier
        </a>
    </div>
</div>

<div class="card">
    <div class="view-grid">

        <!-- ============================================ -->
        <!-- COLONNE GAUCHE : Galerie photos              -->
        <!-- ============================================ -->
        <div class="gallery-main-wrap">

            <?php
            $primary_img = null;
            foreach ($gallery as $gimg) {
                if ($gimg['is_primary']) { $primary_img = $gimg; break; }
            }
            if (!$primary_img && !empty($gallery)) {
                $primary_img = $gallery[0];
            }
            $main_src = $primary_img
                ? '../../../uploads/products/' . htmlspecialchars($primary_img['image'])
                : null;
            ?>

            <!-- Image principale -->
            <div class="gallery-main-img-box">
                <?php if ($main_src): ?>
                    <img id="main-img"
                         src="<?php echo $main_src; ?>"
                         alt="<?php echo htmlspecialchars($product['name']); ?>"
                         onerror="this.style.display='none';document.getElementById('no-img-icon').style.display='block';">
                    <i class="fas fa-image no-image-icon" id="no-img-icon" style="display:none;"></i>
                <?php else: ?>
                    <i class="fas fa-image no-image-icon"></i>
                <?php endif; ?>
            </div>

            <!-- Miniatures -->
            <?php if (count($gallery) > 1): ?>
            <div class="gallery-thumbs" id="gallery-thumbs">
                <?php foreach ($gallery as $ti => $timg): ?>
                <div class="gallery-thumb <?php echo $timg['is_primary'] ? 'active' : ''; ?>"
                     data-src="../../../uploads/products/<?php echo htmlspecialchars($timg['image']); ?>"
                     onclick="switchMain(this)">
                    <img src="../../../uploads/products/<?php echo htmlspecialchars($timg['image']); ?>"
                         alt=""
                         onerror="this.parentElement.style.display='none';">
                </div>
                <?php endforeach; ?>
            </div>
            <?php elseif (empty($gallery)): ?>
            <p style="text-align:center;color:var(--text-secondary);margin-top:12px;font-size:13px;">
                Aucune photo pour ce produit
            </p>
            <?php endif; ?>

            <!-- Dates -->
            <div class="dates-row" style="margin-top:16px;">
                <?php if (!empty($product['created_at'])): ?>
                <span><i class="fas fa-calendar-plus"></i> Créé le <?php echo format_date($product['created_at']); ?></span>
                <?php endif; ?>
                <?php if (!empty($product['updated_at'])): ?>
                <span><i class="fas fa-calendar-check"></i> Modifié le <?php echo format_date($product['updated_at']); ?></span>
                <?php endif; ?>
            </div>
        </div>

        <!-- ============================================ -->
        <!-- COLONNE DROITE : Informations produit       -->
        <!-- ============================================ -->
        <div>

            <!-- Titre & badges statut -->
            <div class="product-title-row">
                <h2 class="product-main-title"><?php echo htmlspecialchars($product['name']); ?></h2>
            </div>

            <!-- SKU -->
            <div class="sku-badge">
                <i class="fas fa-barcode"></i> <?php echo htmlspecialchars($product['sku'] ?? '—'); ?>
            </div>

            <!-- Badges -->
            <div class="badge-row">
                <?php if ($product['is_active']): ?>
                    <span class="badge badge-active"><i class="fas fa-check-circle"></i> Actif</span>
                <?php else: ?>
                    <span class="badge badge-inactive"><i class="fas fa-times-circle"></i> Inactif</span>
                <?php endif; ?>

                <?php if ($product['is_featured']): ?>
                    <span class="badge badge-featured"><i class="fas fa-star"></i> Featured</span>
                <?php endif; ?>

                <?php if (!empty($product['category_name'])): ?>
                    <span class="badge badge-cat"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name']); ?></span>
                <?php endif; ?>

                <?php if (!empty($product['brand_name'])): ?>
                    <span class="badge badge-brand"><i class="fas fa-certificate"></i> <?php echo htmlspecialchars($product['brand_name']); ?></span>
                <?php endif; ?>
            </div>

            <!-- Prix -->
            <div class="price-box">
                <span class="price-current"><?php echo format_price($product['price']); ?></span>
                <?php if (!empty($product['old_price']) && $product['old_price'] > 0): ?>
                    <span class="price-old"><?php echo format_price($product['old_price']); ?></span>
                    <?php
                    $discount = round((1 - $product['price'] / $product['old_price']) * 100);
                    if ($discount > 0):
                    ?>
                    <span class="price-discount">-<?php echo $discount; ?>%</span>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <!-- Stock -->
            <div class="stock-row">
                <?php
                $stock = (int)$product['stock'];
                $threshold = (int)($product['stock_threshold'] ?? 5);
                if ($stock === 0):
                ?>
                    <span class="stock-badge stock-out"><i class="fas fa-times-circle"></i> Rupture de stock</span>
                <?php elseif ($stock <= $threshold): ?>
                    <span class="stock-badge stock-low"><i class="fas fa-exclamation-triangle"></i> Stock faible</span>
                <?php else: ?>
                    <span class="stock-badge stock-ok"><i class="fas fa-check-circle"></i> En stock</span>
                <?php endif; ?>
                <span class="stock-number"><?php echo $stock; ?> unité<?php echo $stock > 1 ? 's' : ''; ?> disponible<?php echo $stock > 1 ? 's' : ''; ?></span>
                <?php if ($threshold > 0): ?>
                    <span style="font-size:12px;color:var(--text-secondary);">(alerte à <?php echo $threshold; ?>)</span>
                <?php endif; ?>
            </div>

            <!-- Description -->
            <?php if (!empty($product['description'])): ?>
            <div class="info-section">
                <div class="info-section-title">
                    <i class="fas fa-align-left"></i> Description
                </div>
                <p class="description-text"><?php echo htmlspecialchars($product['description']); ?></p>
            </div>
            <?php endif; ?>

            <!-- Détails techniques -->
            <div class="info-section">
                <div class="info-section-title">
                    <i class="fas fa-info-circle"></i> Détails
                </div>
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-item-label">ID Produit</div>
                        <div class="info-item-value">#<?php echo $product_id; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">SKU</div>
                        <div class="info-item-value" style="font-family:monospace;"><?php echo htmlspecialchars($product['sku'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">Catégorie</div>
                        <div class="info-item-value"><?php echo htmlspecialchars($product['category_name'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">Marque</div>
                        <div class="info-item-value"><?php echo htmlspecialchars($product['brand_name'] ?? '—'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">Prix actuel</div>
                        <div class="info-item-value" style="color:var(--neon-cyan);"><?php echo format_price($product['price']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">Ancien prix</div>
                        <div class="info-item-value"><?php echo !empty($product['old_price']) ? format_price($product['old_price']) : '—'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">Stock actuel</div>
                        <div class="info-item-value"><?php echo $stock; ?> unité<?php echo $stock > 1 ? 's' : ''; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">Seuil d'alerte</div>
                        <div class="info-item-value"><?php echo $threshold; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">Statut</div>
                        <div class="info-item-value"><?php echo $product['is_active'] ? '<span style="color:var(--neon-green);">Actif</span>' : '<span style="color:#ff5555;">Inactif</span>'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">Featured</div>
                        <div class="info-item-value"><?php echo $product['is_featured'] ? '<span style="color:var(--neon-gold);">Oui</span>' : 'Non'; ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">Nb de photos</div>
                        <div class="info-item-value"><?php echo count($gallery); ?> / 5</div>
                    </div>
                    <div class="info-item">
                        <div class="info-item-label">Image principale</div>
                        <div class="info-item-value" style="font-size:12px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo htmlspecialchars($product['image'] ?? ''); ?>">
                            <?php echo !empty($product['image']) ? htmlspecialchars($product['image']) : '<span style="color:var(--text-secondary);">Aucune</span>'; ?>
                        </div>
                    </div>
                    <?php if (!empty($product['product_type'])): ?>
                    <div class="info-item">
                        <div class="info-item-label">Type de produit</div>
                        <div class="info-item-value" style="color:var(--neon-cyan);">
                            <i class="fas fa-tag"></i>
                            <?php echo htmlspecialchars($ATTR_TYPE_LABELS[$product['product_type']] ?? ucfirst($product['product_type'])); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Caractéristiques dynamiques -->
            <?php if (!empty($product_attrs)): ?>
            <div class="info-section">
                <div class="info-section-title">
                    <i class="fas fa-list-ul"></i> Caractéristiques
                </div>
                <table style="width:100%;border-collapse:collapse;">
                    <?php foreach ($product_attrs as $key => $val):
                        $label  = $ATTR_LABELS[$key] ?? ucfirst(str_replace('_', ' ', $key));
                        $is_arr = is_array($val);
                    ?>
                    <tr style="border-bottom:1px solid var(--border-color);">
                        <th style="width:40%;padding:8px 12px;text-align:left;font-size:12px;color:var(--text-secondary);font-weight:600;background:rgba(255,255,255,.03);">
                            <?php echo htmlspecialchars($label); ?>
                        </th>
                        <td style="padding:8px 12px;font-size:13px;color:var(--text-primary);">
                            <?php if ($is_arr): ?>
                                <?php foreach ($val as $chip): ?>
                                    <span style="display:inline-block;background:rgba(0,188,212,.15);color:var(--neon-cyan);border-radius:999px;padding:2px 10px;font-size:11px;margin:2px 3px 2px 0;">
                                        <?php echo htmlspecialchars($chip); ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php echo htmlspecialchars((string)$val); ?>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <?php endif; ?>

            <!-- Actions -->
            <div class="view-actions">
                <a href="products-list.php" class="btn btn-secondary">
                    <i class="fas fa-list"></i> Liste des produits
                </a>
                <a href="product-edit.php?id=<?php echo $product_id; ?>" class="btn btn-primary">
                    <i class="fas fa-edit"></i> Modifier ce produit
                </a>
                <button type="button"
                        class="btn btn-danger"
                        onclick="confirmDelete(<?php echo $product_id; ?>, '<?php echo addslashes(htmlspecialchars($product['name'])); ?>')">
                    <i class="fas fa-trash-alt"></i> Supprimer
                </button>
            </div>

        </div><!-- fin colonne droite -->
    </div><!-- fin view-grid -->
</div><!-- fin card -->

<script>
/* ---- Changer l'image principale au clic sur une miniature ---- */
function switchMain(thumb) {
    const mainImg = document.getElementById('main-img');
    if (mainImg) {
        mainImg.style.opacity = '0.4';
        mainImg.src = thumb.dataset.src;
        mainImg.style.display = 'block';
        const noIcon = document.getElementById('no-img-icon');
        if (noIcon) noIcon.style.display = 'none';
        mainImg.onload  = function() { mainImg.style.opacity = '1'; };
        mainImg.onerror = function() {
            mainImg.style.display = 'none';
            const icon = document.getElementById('no-img-icon');
            if (icon) icon.style.display = 'block';
        };
    }
    document.querySelectorAll('.gallery-thumb').forEach(t => t.classList.remove('active'));
    thumb.classList.add('active');
}

/* ---- Confirmer la suppression ---- */
function confirmDelete(id, name) {
    if (confirm('⚠️ ATTENTION ⚠️\n\nSupprimer le produit :\n"' + name + '" ?\n\nCette action est irréversible !')) {
        window.location.href = 'product-delete.php?id=' + id;
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
