<?php
/**
 * Modifier un Produit
 * Product Admin - Atlantech Shop
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$atl_usd_rate = atl_pa_usd_rate();

$page_title   = 'Modifier un Produit';
$current_page = 'products';

/* =====================================================================
   SCHÉMA DES ATTRIBUTS PAR TYPE (identique à product-add.php)
   ===================================================================== */
$ATTR_SCHEMA = [
  'vetements'   => ['label'=>'Vêtements','icon'=>'fa-tshirt','color'=>'#e91e8c','fields'=>[
    ['n'=>'genre','l'=>'Genre','t'=>'select','o'=>['Homme','Femme','Enfant','Unisexe']],
    ['n'=>'tailles','l'=>'Taille(s)','t'=>'chips','o'=>['XS','S','M','L','XL','XXL','XXXL','Unique']],
    ['n'=>'matiere','l'=>'Matière','t'=>'text','ph'=>'Ex: coton, polyester...'],
    ['n'=>'motif','l'=>'Motif','t'=>'select','o'=>['Uni','Rayé','Floral','Carreaux','Imprimé','Brodé','Autre']],
    ['n'=>'style','l'=>'Style','t'=>'select','o'=>['Casual','Chic','Sport','Streetwear','Formel','Bohème']],
    ['n'=>'coupe','l'=>'Coupe','t'=>'select','o'=>['Slim','Regular','Oversize','Fitted','Loose']],
    ['n'=>'saison','l'=>'Saison','t'=>'chips','o'=>['Printemps','Été','Automne','Hiver','Toutes saisons']],
    ['n'=>'longueur','l'=>'Longueur','t'=>'text','ph'=>'Ex: court, mi-long, long...'],
    ['n'=>'type_col','l'=>'Type de col','t'=>'text','ph'=>'Ex: col rond, col V...'],
    ['n'=>'type_manche','l'=>'Type de manche','t'=>'text','ph'=>'Ex: courtes, longues...'],
    ['n'=>'fermeture','l'=>'Fermeture','t'=>'select','o'=>['Aucune','Zip','Bouton','Lacet','Velcro','Élastique']],
    ['n'=>'occasion','l'=>'Occasion','t'=>'chips','o'=>['Quotidien','Travail','Soirée','Sport','Plage','Cérémonie']],
    ['n'=>'pays_fab','l'=>'Pays de fabrication','t'=>'text','ph'=>'Ex: Chine, France...'],
    ['n'=>'poids','l'=>'Poids (g)','t'=>'number','ph'=>'0'],
  ]],
  'chaussures'  => ['label'=>'Chaussures','icon'=>'fa-shoe-prints','color'=>'#ff6b35','fields'=>[
    ['n'=>'genre','l'=>'Genre','t'=>'select','o'=>['Homme','Femme','Enfant','Unisexe']],
    ['n'=>'type_chaussure','l'=>'Type','t'=>'select','o'=>['Basket','Sandale','Habillée','Botte','Bottine','Escarpin','Mocassin','Tong','Autre']],
    ['n'=>'pointures','l'=>'Pointure(s)','t'=>'chips','o'=>['35','36','37','38','39','40','41','42','43','44','45','46','47','48']],
    ['n'=>'matiere_ext','l'=>'Matière extérieure','t'=>'text','ph'=>'Ex: cuir, synthétique...'],
    ['n'=>'matiere_int','l'=>'Matière intérieure','t'=>'text','ph'=>'Ex: cuir, textile...'],
    ['n'=>'semelle','l'=>'Semelle','t'=>'text','ph'=>'Ex: caoutchouc, EVA...'],
    ['n'=>'hauteur_talon','l'=>'Hauteur talon (cm)','t'=>'number','ph'=>'0'],
    ['n'=>'fermeture','l'=>'Fermeture','t'=>'select','o'=>['Lacets','Velcro','Zip','Élastique','Slip-on','Boucle']],
    ['n'=>'style','l'=>'Style','t'=>'select','o'=>['Sport','Casual','Chic','Formel','Outdoor','Urbain']],
    ['n'=>'saison','l'=>'Saison','t'=>'select','o'=>['Printemps/Été','Automne/Hiver','Toutes saisons']],
    ['n'=>'usage','l'=>'Usage','t'=>'chips','o'=>['Sport','Ville','Travail','Soirée','Plage','Randonnée']],
    ['n'=>'poids','l'=>'Poids (g)','t'=>'number','ph'=>'0'],
  ]],
  'sacs'        => ['label'=>'Sacs & Accessoires','icon'=>'fa-shopping-bag','color'=>'#9c27b0','fields'=>[
    ['n'=>'type_sac','l'=>'Type de sac','t'=>'select','o'=>['Sac à main','Sac à dos','Portefeuille','Pochette','Bandoulière','Tote bag','Voyage','Ceinture']],
    ['n'=>'genre','l'=>'Genre','t'=>'select','o'=>['Femme','Homme','Unisexe']],
    ['n'=>'matiere','l'=>'Matière','t'=>'text','ph'=>'Ex: cuir, tissu, paille...'],
    ['n'=>'dimensions','l'=>'Dimensions (L×H×P cm)','t'=>'text','ph'=>'Ex: 35×25×12'],
    ['n'=>'nb_compart','l'=>'Nb compartiments','t'=>'number','ph'=>'1'],
    ['n'=>'fermeture','l'=>'Fermeture','t'=>'select','o'=>['Zip','Magnétique','Bouton pression','Rabat','Ouvert','Cadenas']],
    ['n'=>'style','l'=>'Style','t'=>'select','o'=>['Casual','Chic','Sport','Luxe','Vintage','Minimaliste']],
    ['n'=>'occasion','l'=>'Occasion','t'=>'chips','o'=>['Quotidien','Travail','Soirée','Voyage','Sport','Plage']],
    ['n'=>'capacite','l'=>'Capacité (L)','t'=>'text','ph'=>'Ex: 10L, 25L...'],
    ['n'=>'poids','l'=>'Poids (g)','t'=>'number','ph'=>'0'],
  ]],
  'bijoux'      => ['label'=>'Bijoux','icon'=>'fa-gem','color'=>'#ffc107','fields'=>[
    ['n'=>'type_bijou','l'=>'Type','t'=>'select','o'=>['Collier','Bracelet','Bague','Boucles d\'oreilles','Pendentif','Montre','Broche','Parure']],
    ['n'=>'metal','l'=>'Métal','t'=>'select','o'=>['Acier inoxydable','Argent 925','Or 18K','Or 14K','Plaqué or','Cuivre','Titane','Autre']],
    ['n'=>'pierre','l'=>'Pierre','t'=>'text','ph'=>'Ex: diamant, zircon, aucune...'],
    ['n'=>'taille','l'=>'Taille / Circonférence','t'=>'text','ph'=>'Ex: 45cm, T52...'],
    ['n'=>'style','l'=>'Style','t'=>'select','o'=>['Classique','Moderne','Bohème','Minimaliste','Vintage','Luxe']],
    ['n'=>'hypoallergenique','l'=>'Hypoallergénique','t'=>'select','o'=>['Oui','Non','Non spécifié']],
    ['n'=>'resistant_eau','l'=>'Résistant à l\'eau','t'=>'select','o'=>['Oui','Non','Non spécifié']],
    ['n'=>'genre','l'=>'Genre','t'=>'select','o'=>['Femme','Homme','Unisexe']],
    ['n'=>'occasion','l'=>'Occasion','t'=>'chips','o'=>['Quotidien','Soirée','Mariage','Cadeau','Cérémonie']],
  ]],
  'beaute'      => ['label'=>'Beauté & Cosmétiques','icon'=>'fa-spa','color'=>'#e91e63','fields'=>[
    ['n'=>'type_beaute','l'=>'Type','t'=>'select','o'=>['Fond de teint','Rouge à lèvres','Mascara','Eye-liner','Fard à paupières','Parfum','Crème visage','Sérum','Hydratant','Nettoyant','Shampoing','Après-shampoing','Soin corps','Autre']],
    ['n'=>'volume','l'=>'Volume / Quantité','t'=>'text','ph'=>'Ex: 50ml, 100g...'],
    ['n'=>'type_peau','l'=>'Type de peau','t'=>'chips','o'=>['Normale','Sèche','Grasse','Mixte','Sensible','Toutes peaux']],
    ['n'=>'fonction','l'=>'Fonction(s)','t'=>'text','ph'=>'Ex: hydratant, anti-âge...'],
    ['n'=>'ingredients','l'=>'Ingrédients clés','t'=>'textarea','ph'=>'Liste des ingrédients...'],
    ['n'=>'parfum_prod','l'=>'Parfum / Senteur','t'=>'text','ph'=>'Ex: rose, vanille, sans parfum...'],
    ['n'=>'dlc','l'=>'DLC (après ouverture)','t'=>'text','ph'=>'Ex: 12M, 24M...'],
    ['n'=>'certification','l'=>'Certification','t'=>'text','ph'=>'Ex: Vegan, Bio, Halal...'],
    ['n'=>'usage_beaute','l'=>'Usage','t'=>'select','o'=>['Visage','Corps','Cheveux','Yeux','Lèvres','Mixte']],
  ]],
  'electronique'=> ['label'=>'Électronique','icon'=>'fa-microchip','color'=>'#00bcd4','fields'=>[
    ['n'=>'sous_type','l'=>'Sous-catégorie','t'=>'select','o'=>['Smartphone','Tablette','Télévision','Enceinte','Écouteurs / Casque','Appareil photo','Caméra','Console de jeu','Montre connectée','Drone','Autre']],
    ['n'=>'modele','l'=>'Modèle','t'=>'text','ph'=>'Ex: Galaxy S24 Ultra...'],
    ['n'=>'reference','l'=>'Référence fabricant','t'=>'text','ph'=>'Ex: SM-S928B...'],
    ['n'=>'couleur','l'=>'Couleur(s)','t'=>'text','ph'=>'Ex: Noir, Blanc...'],
    ['n'=>'ecran','l'=>'Taille écran (pouces)','t'=>'text','ph'=>'Ex: 6.7"'],
    ['n'=>'resolution','l'=>'Résolution écran','t'=>'text','ph'=>'Ex: 2556×1179 px...'],
    ['n'=>'ram','l'=>'RAM','t'=>'text','ph'=>'Ex: 8 Go, 12 Go...'],
    ['n'=>'stockage','l'=>'Stockage','t'=>'text','ph'=>'Ex: 128 Go, 256 Go...'],
    ['n'=>'processeur','l'=>'Processeur','t'=>'text','ph'=>'Ex: Snapdragon 8 Gen 3...'],
    ['n'=>'batterie','l'=>'Batterie (mAh)','t'=>'text','ph'=>'Ex: 5000 mAh'],
    ['n'=>'camera_avant','l'=>'Caméra avant','t'=>'text','ph'=>'Ex: 12 MP'],
    ['n'=>'camera_arriere','l'=>'Caméra arrière','t'=>'text','ph'=>'Ex: 200 MP + 10 MP...'],
    ['n'=>'sim','l'=>'SIM','t'=>'select','o'=>['Nano SIM','eSIM','Dual SIM','eSIM + SIM','Non applicable']],
    ['n'=>'reseau','l'=>'Réseau','t'=>'chips','o'=>['2G','3G','4G','5G','WiFi 6','Bluetooth 5','Non applicable']],
    ['n'=>'garantie','l'=>'Garantie','t'=>'text','ph'=>'Ex: 1 an, 2 ans...'],
    ['n'=>'pays_origine','l'=>'Pays d\'origine','t'=>'text','ph'=>'Ex: Chine, Corée du Sud...'],
  ]],
  'informatique'=> ['label'=>'Informatique','icon'=>'fa-laptop','color'=>'#3f51b5','fields'=>[
    ['n'=>'sous_type','l'=>'Sous-catégorie','t'=>'select','o'=>['Ordinateur portable','Ordinateur de bureau','Souris','Clavier','Écran / Moniteur','Imprimante','Scanner','Disque dur / SSD externe','Carte mémoire','Hub USB','Câble','Autre accessoire']],
    ['n'=>'modele','l'=>'Modèle','t'=>'text','ph'=>'Ex: MacBook Pro 14...'],
    ['n'=>'processeur','l'=>'Processeur (CPU)','t'=>'text','ph'=>'Ex: Intel Core i7-13700H...'],
    ['n'=>'gen_cpu','l'=>'Génération CPU','t'=>'text','ph'=>'Ex: 13ème génération...'],
    ['n'=>'ram','l'=>'RAM','t'=>'select','o'=>['4 Go','8 Go','16 Go','32 Go','64 Go','128 Go','Autre']],
    ['n'=>'type_ram','l'=>'Type de RAM','t'=>'select','o'=>['DDR4','DDR5','LPDDR4','LPDDR5','Non applicable']],
    ['n'=>'stockage','l'=>'Stockage','t'=>'select','o'=>['128 Go','256 Go','512 Go','1 To','2 To','4 To','Autre']],
    ['n'=>'type_stockage','l'=>'Type de stockage','t'=>'select','o'=>['SSD NVMe','SSD SATA','HDD','eMMC','Hybride (SSD+HDD)','Non applicable']],
    ['n'=>'ecran','l'=>'Taille écran (pouces)','t'=>'text','ph'=>'Ex: 14", 15.6"...'],
    ['n'=>'resolution','l'=>'Résolution écran','t'=>'text','ph'=>'Ex: 2560×1600 (2K)...'],
    ['n'=>'gpu','l'=>'Carte graphique (GPU)','t'=>'text','ph'=>'Ex: NVIDIA RTX 4060...'],
    ['n'=>'batterie','l'=>'Batterie','t'=>'text','ph'=>'Ex: 100 Wh, 10h autonomie...'],
    ['n'=>'clavier','l'=>'Langue du clavier','t'=>'select','o'=>['AZERTY (FR)','QWERTY (EN/US)','QWERTZ (DE)','Rétroéclairé','Autre']],
    ['n'=>'os','l'=>'Système d\'exploitation','t'=>'select','o'=>['Windows 11','Windows 10','macOS','Linux','Chrome OS','Sans OS','Autre']],
    ['n'=>'ports','l'=>'Ports disponibles','t'=>'text','ph'=>'Ex: USB-A×2, USB-C×2, HDMI...'],
    ['n'=>'wifi_bt','l'=>'WiFi / Bluetooth','t'=>'text','ph'=>'Ex: WiFi 6E, Bluetooth 5.3...'],
    ['n'=>'garantie','l'=>'Garantie','t'=>'text','ph'=>'Ex: 1 an constructeur...'],
    ['n'=>'connexion','l'=>'Connexion (accessoires)','t'=>'chips','o'=>['USB-A','USB-C','Bluetooth','WiFi','2.4 GHz sans fil','Jack 3.5mm','Autre']],
    ['n'=>'compatibilite','l'=>'Compatibilité','t'=>'text','ph'=>'Ex: Windows/Mac/Linux...'],
  ]],
  'maison'      => ['label'=>'Maison & Décoration','icon'=>'fa-couch','color'=>'#795548','fields'=>[
    ['n'=>'type_prod','l'=>'Type de produit','t'=>'text','ph'=>'Ex: canapé, lampe, rideau...'],
    ['n'=>'piece','l'=>'Pièce','t'=>'chips','o'=>['Salon','Chambre','Cuisine','Salle de bain','Bureau','Entrée','Jardin','Toute la maison']],
    ['n'=>'matiere','l'=>'Matière','t'=>'text','ph'=>'Ex: bois, métal, verre...'],
    ['n'=>'couleur','l'=>'Couleur(s)','t'=>'text','ph'=>'Ex: blanc, noir, beige...'],
    ['n'=>'dimensions','l'=>'Dimensions (cm)','t'=>'text','ph'=>'Ex: L120×H80×P45'],
    ['n'=>'poids','l'=>'Poids (kg)','t'=>'text','ph'=>'Ex: 5.5 kg'],
    ['n'=>'style','l'=>'Style','t'=>'select','o'=>['Moderne','Classique','Scandinave','Industriel','Bohème','Minimaliste','Baroque','Rustique']],
    ['n'=>'installation','l'=>'Installation','t'=>'select','o'=>['Non, prêt à l\'emploi','Oui, simple (vis)','Oui, professionnel recommandé']],
    ['n'=>'entretien','l'=>'Entretien','t'=>'text','ph'=>'Ex: lavable, essuyage humide...'],
    ['n'=>'usage','l'=>'Usage','t'=>'select','o'=>['Intérieur','Extérieur','Intérieur / Extérieur']],
  ]],
  'cuisine'     => ['label'=>'Cuisine','icon'=>'fa-utensils','color'=>'#ff5722','fields'=>[
    ['n'=>'type_prod','l'=>'Type','t'=>'select','o'=>['Ustensile','Électroménager','Vaisselle','Accessoire','Rangement','Autre']],
    ['n'=>'matiere','l'=>'Matière','t'=>'text','ph'=>'Ex: inox, céramique, silicone...'],
    ['n'=>'capacite','l'=>'Capacité (L)','t'=>'text','ph'=>'Ex: 5L, 2.5L...'],
    ['n'=>'dimensions','l'=>'Dimensions (cm)','t'=>'text','ph'=>'Ex: 30×20×15'],
    ['n'=>'puissance','l'=>'Puissance (W)','t'=>'number','ph'=>'0'],
    ['n'=>'voltage','l'=>'Voltage','t'=>'select','o'=>['110V','220V','110-220V (universel)','Non applicable']],
    ['n'=>'compatibilite','l'=>'Compatibilité','t'=>'text','ph'=>'Ex: induction, gaz, tous feux...'],
    ['n'=>'lave_vaisselle','l'=>'Lavable au lave-vaisselle','t'=>'select','o'=>['Oui','Non','Certaines pièces uniquement']],
    ['n'=>'garantie','l'=>'Garantie','t'=>'text','ph'=>'Ex: 2 ans...'],
  ]],
  'sport'       => ['label'=>'Sport & Fitness','icon'=>'fa-dumbbell','color'=>'#4caf50','fields'=>[
    ['n'=>'type_sport','l'=>'Sport concerné','t'=>'text','ph'=>'Ex: football, yoga, musculation...'],
    ['n'=>'niveau','l'=>'Niveau','t'=>'select','o'=>['Débutant','Intermédiaire','Avancé','Professionnel','Tous niveaux']],
    ['n'=>'taille','l'=>'Taille(s)','t'=>'text','ph'=>'Ex: S/M/L ou 183×61 cm...'],
    ['n'=>'couleur','l'=>'Couleur(s)','t'=>'text','ph'=>'Ex: noir, rouge, multicolore...'],
    ['n'=>'matiere','l'=>'Matière','t'=>'text','ph'=>'Ex: néoprène, coton, caoutchouc...'],
    ['n'=>'poids','l'=>'Poids (kg)','t'=>'text','ph'=>'Ex: 2 kg, 5 kg...'],
    ['n'=>'dimensions','l'=>'Dimensions (cm)','t'=>'text','ph'=>'Ex: 183×61 cm...'],
    ['n'=>'resistance','l'=>'Résistance / Charge max','t'=>'text','ph'=>'Ex: 120 kg, niveau 3/5...'],
    ['n'=>'genre','l'=>'Genre','t'=>'select','o'=>['Mixte','Homme','Femme','Enfant']],
  ]],
  'jouets'      => ['label'=>'Jouets & Enfants','icon'=>'fa-baby','color'=>'#ff9800','fields'=>[
    ['n'=>'age_min','l'=>'Âge minimum recommandé','t'=>'select','o'=>['0+ mois','3+ mois','6+ mois','12+ mois','18+ mois','2+ ans','3+ ans','4+ ans','5+ ans','6+ ans','8+ ans','10+ ans','12+ ans','Adulte']],
    ['n'=>'type_jouet','l'=>'Type de jouet','t'=>'select','o'=>['Peluche','Jeu de construction','Jeu éducatif','Poupée / Figurine','Voiture / Véhicule','Jeu de société','Puzzle','Jouet musical','Jouet d\'éveil','Déguisement','Autre']],
    ['n'=>'matiere','l'=>'Matière','t'=>'text','ph'=>'Ex: plastique ABS, bois...'],
    ['n'=>'dimensions','l'=>'Dimensions (cm)','t'=>'text','ph'=>'Ex: 30×20 cm...'],
    ['n'=>'securite','l'=>'Certifications sécurité','t'=>'text','ph'=>'Ex: CE, EN71, ASTM...'],
    ['n'=>'batterie','l'=>'Batterie requise','t'=>'select','o'=>['Non','Oui — incluse','Oui — non incluse (préciser)']],
    ['n'=>'type_batterie','l'=>'Type de batterie','t'=>'text','ph'=>'Ex: 3×AA, Rechargeable USB...'],
    ['n'=>'fonction','l'=>'Fonctions','t'=>'text','ph'=>'Ex: lumineux, musical, télécommandé...'],
    ['n'=>'genre','l'=>'Genre','t'=>'select','o'=>['Fille','Garçon','Mixte']],
  ]],
  'automobile'  => ['label'=>'Automobile','icon'=>'fa-car','color'=>'#607d8b','fields'=>[
    ['n'=>'type_piece','l'=>'Type pièce / accessoire','t'=>'text','ph'=>'Ex: filtre à huile, tapis de sol...'],
    ['n'=>'marque_compat','l'=>'Marque compatible','t'=>'text','ph'=>'Ex: Toyota, Honda, Universel...'],
    ['n'=>'modele_vehic','l'=>'Modèle de véhicule','t'=>'text','ph'=>'Ex: Corolla, Civic...'],
    ['n'=>'annee_vehic','l'=>'Année(s) compatible(s)','t'=>'text','ph'=>'Ex: 2018-2024...'],
    ['n'=>'matiere','l'=>'Matière','t'=>'text','ph'=>'Ex: plastique ABS, aluminium...'],
    ['n'=>'dimensions','l'=>'Dimensions (cm)','t'=>'text','ph'=>'Ex: 50×30 cm...'],
    ['n'=>'installation','l'=>'Installation','t'=>'select','o'=>['Plug & Play','Bricolage simple','Mécanicien recommandé','Professionnel requis']],
    ['n'=>'garantie','l'=>'Garantie','t'=>'text','ph'=>'Ex: 1 an, 2 ans...'],
  ]],
];

$error   = '';
$success = '';

// Vérifier que l'ID est présent et valide
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    header('Location: products-list.php?error=invalid_id');
    exit();
}

// Charger le produit depuis la DB
$product = get_product_by_id($product_id);

if (!$product) {
    header('Location: products-list.php?error=not_found');
    exit();
}

// ============================================================
// Traitement du formulaire (POST)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Vérification CSRF
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide. Veuillez rafraîchir la page et réessayer.';
    } else {

        // Récupérer les données du formulaire
        // Whitelist condition (doit matcher l'enum DB)
        $allowed_conditions = ['new','refurbished','used_like_new','used_good','used_acceptable'];
        $cond = (string)($_POST['condition'] ?? ($product['condition'] ?? 'new'));
        if (!in_array($cond, $allowed_conditions, true)) {
            $cond = 'new';
        }

        // --- Construire le JSON d'attributs ---
        $posted_type = clean_input($_POST['product_type'] ?? '');
        $attributes_json = null;
        if ($posted_type && isset($ATTR_SCHEMA[$posted_type])) {
            $attrs = [];
            foreach ($ATTR_SCHEMA[$posted_type]['fields'] as $f) {
                $key = 'attr_' . $f['n'];
                if ($f['t'] === 'chips') {
                    $val = isset($_POST[$key]) && is_array($_POST[$key]) ? array_values(array_filter($_POST[$key])) : [];
                } else {
                    $val = trim($_POST[$key] ?? '');
                }
                if ($val !== '' && $val !== []) {
                    $attrs[$f['n']] = $val;
                }
            }
            $attributes_json = json_encode($attrs, JSON_UNESCAPED_UNICODE);
        }

        $data = [
            'name'              => clean_input($_POST['name']            ?? ''),
            'sku'               => clean_input($_POST['sku']             ?? ''),
            'description'       => clean_input($_POST['description']     ?? ''),
            'short_description' => clean_input($_POST['short_description'] ?? ''),
            'price'             => atl_pa_to_htg(floatval($_POST['price'] ?? 0), ($_POST['price_currency'] ?? 'HTG') === 'USD' ? 'USD' : 'HTG'),
            'old_price'         => !empty($_POST['old_price']) ? atl_pa_to_htg(floatval($_POST['old_price']), ($_POST['old_price_currency'] ?? 'HTG') === 'USD' ? 'USD' : 'HTG') : null,
            'stock'             => intval($_POST['stock']                ?? 0),
            'stock_threshold'   => intval($_POST['stock_threshold']      ?? 5),
            'category_id'       => !empty($_POST['category_id']) ? intval($_POST['category_id']) : null,
            'brand_id'          => !empty($_POST['brand_id'])    ? intval($_POST['brand_id'])    : null,
            'condition'         => $cond,
            'product_type'      => $posted_type ?: null,
            'attributes'        => $attributes_json,
            'is_active'         => isset($_POST['is_active'])   ? 1 : 0,
            'is_featured'       => isset($_POST['is_featured']) ? 1 : 0,
            'image'             => $product['image'], // conserver l'image actuelle par défaut
        ];

        // Validation basique
        if (empty($data['name'])) {
            $error = 'Le nom du produit est requis.';
        } elseif (empty($data['sku'])) {
            $error = 'Le SKU est requis.';
        } elseif ($data['price'] <= 0) {
            $error = 'Le prix doit être supérieur à 0.';
        } elseif ($data['stock'] < 0) {
            $error = 'Le stock ne peut pas être négatif.';
        } else {

            // 1. Supprimer les images de galerie marquées pour suppression
            if (!empty($_POST['delete_gallery_ids'])) {
                foreach ($_POST['delete_gallery_ids'] as $del_id) {
                    delete_gallery_image_by_id((int)$del_id, $product_id);
                }
            }

            // 2. Upload des nouvelles photos de galerie
            if (isset($_FILES['new_gallery'])) {
                $gf = $_FILES['new_gallery'];
                $slot_count = is_array($gf['name']) ? count($gf['name']) : 0;
                for ($i = 0; $i < $slot_count; $i++) {
                    if (isset($gf['error'][$i]) && $gf['error'][$i] === UPLOAD_ERR_OK) {
                        $file = [
                            'name'     => $gf['name'][$i],
                            'type'     => $gf['type'][$i],
                            'tmp_name' => $gf['tmp_name'][$i],
                            'error'    => $gf['error'][$i],
                            'size'     => $gf['size'][$i],
                        ];
                        $upload_result = upload_product_image($file);
                        if ($upload_result['success']) {
                            // add_gallery_image auto-promeut si aucune image principale n'existe
                            add_gallery_image($product_id, $upload_result['filename'], 0);
                        } else {
                            $error = 'Nouvelle photo : ' . $upload_result['error'];
                            break;
                        }
                    }
                }
            }

            // 3. Récupérer l'image principale mise à jour pour products.image
            $updated_primary = get_primary_image_filename($product_id);
            $data['image'] = $updated_primary ?? null;

            // Mise à jour si pas d'erreur d'upload
            if (empty($error)) {
                if (update_product($product_id, $data)) {
                    // Mettre à jour les couleurs liées (+ prix par couleur)
                    $posted_color_ids    = $_POST['color_ids'] ?? [];
                    $posted_color_prices = $_POST['color_prices'] ?? [];
                    if (is_array($posted_color_ids)) {
                        set_product_colors($product_id, $posted_color_ids,
                            is_array($posted_color_prices) ? $posted_color_prices : []);
                    }
                    log_admin_action('product_updated', 'Produit modifié : ' . $data['name'] . ' (ID: ' . $product_id . ')');
                    header('Location: products-list.php?success=updated');
                    exit();
                } else {
                    $error = 'Erreur lors de la mise à jour du produit. Veuillez réessayer.';
                }
            }
        }

        // En cas d'erreur, rafraîchir les données affichées avec ce qui a été soumis
        $product = array_merge($product, $data);
    }
}

// Catégories et marques pour les selects
$categories       = get_all_categories();
$brands           = get_all_brands();
$colors             = get_all_colors();
$current_color_ids  = get_product_color_ids($product_id);
$current_color_prices = get_product_color_prices($product_id); // [color_id => price|null]

// Galerie d'images du produit (lue après tout traitement POST éventuel)
$gallery     = get_product_gallery($product_id);
$empty_slots = max(0, 5 - count($gallery));

include __DIR__ . '/../includes/header.php';
?>

<style>
.form-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.form-grid-3 {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.form-section {
    margin-bottom: 30px;
    padding-bottom: 30px;
    border-bottom: 1px solid var(--border-color);
}

.form-section:last-child {
    border-bottom: none;
}

.form-section-title {
    font-size: 18px;
    font-weight: 600;
    color: var(--neon-cyan);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.image-preview {
    margin-top: 15px;
    max-width: 300px;
}

.image-preview img {
    width: 100%;
    border-radius: 8px;
    border: 1px solid var(--border-color);
}

.current-image-wrap {
    display: flex;
    align-items: flex-start;
    gap: 20px;
    flex-wrap: wrap;
    margin-top: 10px;
}

.current-image-wrap img {
    width: 150px;
    height: 150px;
    object-fit: cover;
    border-radius: 8px;
    border: 2px solid var(--border-color);
}

.current-image-label {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 6px;
}

.remove-image-box {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-top: 10px;
    font-size: 13px;
    color: #ff6b6b;
    cursor: pointer;
}

.remove-image-box input[type="checkbox"] {
    width: 16px;
    height: 16px;
    cursor: pointer;
}

.checkbox-group {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 15px;
    background: rgba(255, 255, 255, 0.05);
    border: 1px solid var(--border-color);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.3s;
}

.checkbox-group:hover {
    background: rgba(255, 255, 255, 0.08);
    border-color: var(--neon-cyan);
}

.checkbox-group input[type="checkbox"] {
    width: 20px;
    height: 20px;
    cursor: pointer;
}

.checkbox-group label {
    cursor: pointer;
    margin: 0;
    flex: 1;
}

.form-actions {
    display: flex;
    gap: 15px;
    justify-content: flex-end;
    padding-top: 30px;
    border-top: 1px solid var(--border-color);
    margin-top: 30px;
}

.required-indicator {
    color: #ff0000;
    margin-left: 4px;
}

.form-help {
    font-size: 12px;
    color: var(--text-secondary);
    margin-top: 5px;
}

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

/* ---- Galerie de photos ---- */
.gallery-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 14px;
}

.gallery-slot {
    position: relative;
    border: 2px dashed var(--border-color);
    border-radius: 10px;
    overflow: hidden;
    aspect-ratio: 1;
    background: rgba(255,255,255,0.03);
    transition: border-color 0.25s;
}

.gallery-slot:hover {
    border-color: var(--neon-cyan);
}

.gallery-slot.has-image {
    border-style: solid;
}

.gallery-slot.marked-delete .gallery-preview-img {
    opacity: 0.25;
    filter: grayscale(1);
}

.gallery-slot-inner {
    width: 100%;
    height: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 8px;
    cursor: pointer;
    padding: 10px;
}

.gallery-plus-icon {
    font-size: 26px;
    color: var(--text-secondary);
}

.gallery-slot-label {
    font-size: 11px;
    color: var(--text-secondary);
    text-align: center;
    line-height: 1.3;
}

.gallery-slot .gallery-preview-img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.gallery-badge {
    position: absolute;
    top: 6px;
    left: 6px;
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 10px;
    background: var(--neon-cyan);
    color: #000;
    font-weight: 700;
    z-index: 2;
    pointer-events: none;
    white-space: nowrap;
}

.gallery-badge.secondary {
    background: rgba(255,255,255,0.18);
    color: var(--text-primary);
    font-weight: 500;
}

.gallery-remove-btn {
    position: absolute;
    top: 6px;
    right: 6px;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    background: rgba(220, 50, 50, 0.85);
    color: #fff;
    border: none;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 11px;
    z-index: 3;
    transition: background 0.2s;
}

.gallery-remove-btn:hover {
    background: #ff2222;
}

.gallery-undo-btn {
    position: absolute;
    bottom: 6px;
    right: 6px;
    font-size: 10px;
    padding: 2px 8px;
    border-radius: 8px;
    background: rgba(0, 229, 255, 0.7);
    color: #000;
    border: none;
    cursor: pointer;
    z-index: 3;
    display: none;
}

.gallery-deleted-label {
    position: absolute;
    top: 50%;
    left: 50%;
    transform: translate(-50%, -50%);
    font-size: 11px;
    color: #ff6b6b;
    font-weight: 600;
    z-index: 2;
    pointer-events: none;
    display: none;
}

@media (max-width: 900px) {
    .gallery-grid { grid-template-columns: repeat(3, 1fr); }
}

@media (max-width: 768px) {
    .form-grid-3 { grid-template-columns: 1fr; }
    .gallery-grid { grid-template-columns: repeat(2, 1fr); }
}
</style>

<div class="page-header">
    <div>
        <div class="page-header-meta">
            <h1><i class="fas fa-edit"></i> Modifier un Produit</h1>
        </div>
        <div>
            <span class="product-meta-badge">
                <i class="fas fa-hashtag"></i> ID : <?php echo $product_id; ?>
            </span>
            <span class="product-meta-badge">
                <i class="fas fa-barcode"></i> SKU : <?php echo htmlspecialchars($product['sku'] ?? '—'); ?>
            </span>
            <?php if (!empty($product['category_name'])): ?>
            <span class="product-meta-badge">
                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($product['category_name']); ?>
            </span>
            <?php endif; ?>
        </div>
    </div>
    <a href="products-list.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Retour à la liste
    </a>
</div>

<?php if ($error): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error); ?>
    </div>
<?php endif; ?>

<div class="card">
    <form method="POST" enctype="multipart/form-data" onsubmit="return validateEditForm()">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">

        <!-- ================================================ -->
        <!-- SECTION 1 : Informations de base                 -->
        <!-- ================================================ -->
        <div class="form-section">
            <h3 class="form-section-title">
                <i class="fas fa-info-circle"></i>
                Informations de Base
            </h3>

            <div class="form-group">
                <label class="form-label">
                    Nom du Produit
                    <span class="required-indicator">*</span>
                </label>
                <input
                    type="text"
                    name="name"
                    id="name"
                    class="form-input"
                    required
                    placeholder="Ex: iPhone 15 Pro Max 256GB"
                    value="<?php echo htmlspecialchars($product['name'] ?? ''); ?>"
                >
            </div>

            <div class="form-grid">
                <div class="form-group">
                    <label class="form-label">
                        SKU (Code Produit)
                        <span class="required-indicator">*</span>
                    </label>
                    <input
                        type="text"
                        name="sku"
                        id="sku"
                        class="form-input"
                        required
                        placeholder="Ex: APL-IP15PM-256-001"
                        value="<?php echo htmlspecialchars($product['sku'] ?? ''); ?>"
                    >
                    <div class="form-help">Code unique pour identifier le produit</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Catégorie
                        <span class="required-indicator">*</span>
                    </label>
                    <select name="category_id" class="form-select" required>
                        <option value="">Sélectionner une catégorie...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?php echo $cat['id']; ?>"
                                <?php echo ($product['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <div class="form-group">
                <label class="form-label">Marque</label>
                <select name="brand_id" class="form-select">
                    <option value="">Sélectionner une marque...</option>
                    <?php foreach ($brands as $brand): ?>
                        <option value="<?php echo $brand['id']; ?>"
                            <?php echo ($product['brand_id'] == $brand['id']) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($brand['name']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label class="form-label">État du produit <span class="required">*</span></label>
                <?php
                $cond_options = [
                    'new'              => '🆕 Neuf',
                    'refurbished'      => '♻️ Reconditionné',
                    'used_like_new'    => '✨ Occasion — comme neuf',
                    'used_good'        => '👍 Occasion — bon état',
                    'used_acceptable'  => '🆗 Occasion — état acceptable',
                ];
                $current_cond = $product['condition'] ?? 'new';
                ?>
                <select name="condition" class="form-select" required>
                    <?php foreach ($cond_options as $val => $label): ?>
                        <option value="<?php echo $val; ?>"
                            <?php echo ($current_cond === $val) ? 'selected' : ''; ?>>
                            <?php echo $label; ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint" style="color:#888;font-size:.78rem;">
                    Choisissez l'état réel du produit. Le badge correspondant sera affiché aux clients.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">Couleurs disponibles</label>
                <?php
                // Priorité aux couleurs postées (en cas de ré-affichage après erreur),
                // sinon celles actuellement liées au produit.
                $selected_color_ids = isset($_POST['color_ids']) && is_array($_POST['color_ids'])
                    ? array_map('intval', $_POST['color_ids'])
                    : $current_color_ids;
                ?>
                <style>
                .color-swatch-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(80px,1fr));gap:10px;margin-top:8px;}
                .color-swatch{display:flex;flex-direction:column;align-items:center;gap:6px;padding:10px 6px;border:2px solid var(--border-color);border-radius:12px;cursor:pointer;background:rgba(255,255,255,.03);transition:all .2s;user-select:none;}
                .color-swatch:hover{border-color:var(--neon-cyan);background:rgba(0,188,212,.08);}
                .color-swatch.selected{border-color:var(--neon-cyan);background:rgba(0,188,212,.12);}
                .color-swatch input{display:none;}
                .color-swatch-dot{width:36px;height:36px;border-radius:50%;border:3px solid rgba(255,255,255,.2);box-shadow:0 2px 8px rgba(0,0,0,.35);position:relative;flex-shrink:0;transition:transform .15s;}
                .color-swatch.selected .color-swatch-dot{border-color:var(--neon-cyan);box-shadow:0 0 0 3px var(--neon-cyan),0 2px 8px rgba(0,0,0,.35);transform:scale(1.1);}
                .color-swatch-name{font-size:11px;font-weight:600;color:var(--text-secondary);text-align:center;line-height:1.2;}
                .color-swatch.selected .color-swatch-name{color:var(--neon-cyan);}
                .color-swatch-check{position:absolute;top:-5px;right:-5px;width:16px;height:16px;border-radius:50%;background:var(--neon-cyan);color:#000;font-size:8px;display:none;align-items:center;justify-content:center;font-weight:900;}
                .color-swatch.selected .color-swatch-check{display:flex;}
                .color-price-row{margin-top:12px;display:flex;flex-wrap:wrap;gap:8px;}
                .color-price-item{display:flex;align-items:center;gap:8px;background:rgba(255,255,255,.05);border:1px solid var(--border-color);border-radius:8px;padding:6px 10px;}
                .color-price-dot{width:14px;height:14px;border-radius:50%;flex-shrink:0;border:2px solid rgba(255,255,255,.2);}
                .color-price-label{font-size:11px;font-weight:600;color:var(--text-secondary);white-space:nowrap;}
                .color-price-input-sm{width:150px;padding:4px 8px;border:1px solid var(--border-color);border-radius:6px;font-size:12px;background:var(--bg-secondary);color:var(--text-primary);}
                .price-currency-wrap { display:flex; gap:6px; }
                .price-currency-wrap .form-input { flex:1; }
                .currency-select { width:82px; padding:8px 6px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-secondary); color:var(--text-primary); font-size:13px; }
                .price-converted-preview { color:var(--text-secondary); font-size:12px; margin-top:4px; min-height:16px; }
                </style>

                <!-- Grille de swatches -->
                <div class="color-swatch-grid">
                    <?php foreach ($colors as $c):
                        $cid     = (int)$c['id'];
                        $checked = in_array($cid, $selected_color_ids, true);
                        if (isset($_POST['color_prices'][$cid])) {
                            $cprice = $_POST['color_prices'][$cid];
                        } else {
                            $cprice = isset($current_color_prices[$cid]) && $current_color_prices[$cid] !== null
                                ? (string)$current_color_prices[$cid] : '';
                        }
                    ?>
                    <div class="color-swatch <?php echo $checked ? 'selected' : ''; ?>"
                         onclick="toggleColorSwatch(this, <?php echo $cid; ?>)">
                        <input type="checkbox" name="color_ids[]" value="<?php echo $cid; ?>"
                               <?php echo $checked ? 'checked' : ''; ?>>
                        <div class="color-swatch-dot" style="background:<?php echo htmlspecialchars($c['hex_code']); ?>;">
                            <span class="color-swatch-check"><i class="fas fa-check"></i></span>
                        </div>
                        <span class="color-swatch-name"><?php echo htmlspecialchars($c['name']); ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Prix par couleur sélectionnée -->
                <div class="color-price-row" id="color-price-row">
                    <?php foreach ($colors as $c):
                        $cid     = (int)$c['id'];
                        $checked = in_array($cid, $selected_color_ids, true);
                        if (isset($_POST['color_prices'][$cid])) {
                            $cprice = $_POST['color_prices'][$cid];
                        } else {
                            $cprice = isset($current_color_prices[$cid]) && $current_color_prices[$cid] !== null
                                ? (string)$current_color_prices[$cid] : '';
                        }
                    ?>
                    <div class="color-price-item" id="cprice_wrap_<?php echo $cid; ?>"
                         style="<?php echo $checked ? '' : 'display:none;'; ?>">
                        <span class="color-price-dot" style="background:<?php echo htmlspecialchars($c['hex_code']); ?>;"></span>
                        <span class="color-price-label"><?php echo htmlspecialchars($c['name']); ?></span>
                        <input type="number" step="0.01" min="0"
                               name="color_prices[<?php echo $cid; ?>]"
                               class="color-price-input-sm"
                               placeholder="Prix spécifique (HTG)"
                               value="<?php echo htmlspecialchars($cprice); ?>">
                    </div>
                    <?php endforeach; ?>
                </div>
                <small class="form-hint" style="color:#888;font-size:.78rem;">
                    Cliquez sur une couleur pour la sélectionner. Laissez le prix vide pour utiliser le prix de base.
                </small>
            </div>

            <div class="form-group">
                <label class="form-label">Description courte</label>
                <textarea
                    name="short_description"
                    class="form-textarea"
                    rows="2"
                    maxlength="500"
                    placeholder="Résumé court (max 500 caractères), affiché dans les listes..."
                ><?php echo htmlspecialchars($product['short_description'] ?? ''); ?></textarea>
            </div>

            <div class="form-group">
                <label class="form-label">Description longue</label>
                <textarea
                    name="description"
                    class="form-textarea"
                    rows="5"
                    placeholder="Description détaillée du produit..."
                ><?php echo htmlspecialchars($product['description'] ?? ''); ?></textarea>
            </div>
        </div>

        <!-- ================================================ -->
        <!-- SECTION 2 : Type de produit & Attributs          -->
        <!-- ================================================ -->
        <?php
        $current_type  = $product['product_type'] ?? '';
        $current_attrs = [];
        if (!empty($product['attributes'])) {
            $dec = json_decode(is_string($product['attributes']) ? $product['attributes'] : json_encode($product['attributes']), true);
            if (is_array($dec)) $current_attrs = $dec;
        }
        ?>
        <div class="form-section">
            <h3 class="form-section-title">
                <i class="fas fa-tags"></i>
                Type de Produit &amp; Attributs
            </h3>
            <p style="color:var(--text-secondary);margin-bottom:18px;font-size:14px;">
                Sélectionnez le type pour afficher les attributs spécifiques.
            </p>
            <input type="hidden" name="product_type" id="product_type_hidden" value="<?php echo htmlspecialchars($current_type); ?>">

            <div class="type-grid">
                <?php foreach ($ATTR_SCHEMA as $tkey => $tval): ?>
                <div class="type-card <?php echo ($current_type === $tkey) ? 'active' : ''; ?>"
                     data-type="<?php echo $tkey; ?>"
                     onclick="selectProductType('<?php echo $tkey; ?>')"
                     style="--tc:<?php echo $tval['color']; ?>">
                    <i class="fas <?php echo $tval['icon']; ?>"></i>
                    <span><?php echo htmlspecialchars($tval['label']); ?></span>
                </div>
                <?php endforeach; ?>
            </div>

            <?php foreach ($ATTR_SCHEMA as $tkey => $tval): ?>
            <div class="attr-panel" id="panel_<?php echo $tkey; ?>" style="<?php echo ($current_type === $tkey) ? '' : 'display:none;'; ?>">
                <div class="attr-panel-header" style="border-color:<?php echo $tval['color']; ?>">
                    <i class="fas <?php echo $tval['icon']; ?>" style="color:<?php echo $tval['color']; ?>"></i>
                    <strong><?php echo htmlspecialchars($tval['label']); ?></strong>
                </div>
                <div class="attr-fields-grid">
                <?php foreach ($tval['fields'] as $f):
                    $fname    = 'attr_' . $f['n'];
                    $fval_raw = $current_attrs[$f['n']] ?? null;
                    if ($f['t'] === 'chips' && !is_array($fval_raw)) { $fval_raw = []; }
                ?>
                    <div class="form-group">
                        <label class="form-label" style="font-size:13px;"><?php echo htmlspecialchars($f['l']); ?></label>
                        <?php if ($f['t'] === 'select'): ?>
                            <select name="<?php echo $fname; ?>" class="form-select" style="font-size:13px;">
                                <option value="">— Sélectionner —</option>
                                <?php foreach ($f['o'] as $opt): ?>
                                    <option value="<?php echo htmlspecialchars($opt); ?>"
                                        <?php echo ($fval_raw === $opt) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($opt); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php elseif ($f['t'] === 'chips'): ?>
                            <div class="chips-row">
                                <?php
                                $checked_vals = is_array($fval_raw) ? $fval_raw : [];
                                foreach ($f['o'] as $opt):
                                    $is_checked = in_array($opt, $checked_vals, true);
                                ?>
                                <span class="chip-label <?php echo $is_checked ? 'checked' : ''; ?>"
                                      onclick="toggleChip(this)">
                                    <input type="checkbox" name="<?php echo $fname; ?>[]"
                                           value="<?php echo htmlspecialchars($opt); ?>"
                                           style="display:none;pointer-events:none"
                                           <?php echo $is_checked ? 'checked' : ''; ?>>
                                    <?php echo htmlspecialchars($opt); ?>
                                </span>
                                <?php endforeach; ?>
                            </div>
                        <?php elseif ($f['t'] === 'textarea'): ?>
                            <textarea name="<?php echo $fname; ?>" class="form-textarea" rows="3"
                                      placeholder="<?php echo htmlspecialchars($f['ph'] ?? ''); ?>"
                                      style="font-size:13px;"><?php echo htmlspecialchars(is_string($fval_raw) ? $fval_raw : ''); ?></textarea>
                        <?php elseif ($f['t'] === 'number'): ?>
                            <input type="number" name="<?php echo $fname; ?>" class="form-input"
                                   min="0" step="any"
                                   placeholder="<?php echo htmlspecialchars($f['ph'] ?? '0'); ?>"
                                   style="font-size:13px;"
                                   value="<?php echo htmlspecialchars(is_scalar($fval_raw) ? $fval_raw : ''); ?>">
                        <?php else: ?>
                            <input type="text" name="<?php echo $fname; ?>" class="form-input"
                                   placeholder="<?php echo htmlspecialchars($f['ph'] ?? ''); ?>"
                                   style="font-size:13px;"
                                   value="<?php echo htmlspecialchars(is_scalar($fval_raw) ? $fval_raw : ''); ?>">
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <style>
            .type-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(110px,1fr));gap:12px;margin-bottom:24px;}
            .type-card{display:flex;flex-direction:column;align-items:center;gap:8px;padding:16px 10px;border:2px solid rgba(255,255,255,.1);border-radius:12px;cursor:pointer;transition:all .2s;font-size:12px;text-align:center;color:var(--text-secondary);}
            .type-card i{font-size:22px;color:var(--tc,#888);}
            .type-card:hover{border-color:var(--tc,#888);background:rgba(255,255,255,.05);}
            .type-card.active{border-color:var(--tc,#888);background:rgba(255,255,255,.08);color:#fff;}
            .attr-panel{margin-top:6px;}
            .attr-panel-header{display:flex;align-items:center;gap:10px;padding:10px 16px;background:rgba(255,255,255,.04);border-left:3px solid #888;border-radius:4px;margin-bottom:16px;font-size:14px;}
            .attr-fields-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px;}
            .chips-row{display:flex;flex-wrap:wrap;gap:6px;margin-top:4px;}
            .chip-label{display:inline-flex;align-items:center;padding:5px 12px;border:1.5px solid rgba(255,255,255,.2);border-radius:999px;font-size:12px;cursor:pointer;transition:all .15s;color:var(--text-secondary);}
            .chip-label.checked{border-color:var(--neon-cyan);background:rgba(0,229,255,.12);color:var(--neon-cyan);}
            </style>
        </div>

        <!-- ================================================ -->
        <!-- SECTION 3 : Prix et Stock                        -->
        <!-- ================================================ -->
        <div class="form-section">
            <h3 class="form-section-title">
                <i class="fas fa-dollar-sign"></i>
                Prix et Stock
            </h3>

            <div class="form-grid-3">
                <div class="form-group">
                    <label class="form-label">
                        Prix <span class="required-indicator">*</span>
                    </label>
                    <div class="price-currency-wrap">
                        <input type="number" name="price" id="price" class="form-input"
                               required min="0" step="0.01" placeholder="0.00"
                               value="<?php echo htmlspecialchars($product['price'] ?? ''); ?>">
                        <select name="price_currency" id="price_currency" class="currency-select">
                            <option value="HTG" selected>HTG</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                    <div class="form-help price-converted-preview" id="price_converted"></div>
                </div>

                <div class="form-group">
                    <label class="form-label">Ancien Prix</label>
                    <div class="price-currency-wrap">
                        <input type="number" name="old_price" id="old_price" class="form-input"
                               min="0" step="0.01" placeholder="Laisser vide si pas de réduction"
                               value="<?php echo htmlspecialchars($product['old_price'] ?? ''); ?>">
                        <select name="old_price_currency" id="old_price_currency" class="currency-select">
                            <option value="HTG" selected>HTG</option>
                            <option value="USD">USD</option>
                        </select>
                    </div>
                    <div class="form-help price-converted-preview" id="old_price_converted"></div>
                    <div class="form-help">Pour afficher une réduction</div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        Stock <span class="required-indicator">*</span>
                    </label>
                    <input type="number" name="stock" id="stock" class="form-input"
                           required min="0" step="1"
                           value="<?php echo htmlspecialchars($product['stock'] ?? 0); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Seuil d'Alerte Stock</label>
                    <input type="number" name="stock_threshold" class="form-input"
                           min="0" step="1"
                           value="<?php echo htmlspecialchars($product['stock_threshold'] ?? 5); ?>">
                    <div class="form-help">Vous serez alerté quand le stock atteint ce seuil</div>
                </div>
            </div>
        </div>

        <!-- ================================================ -->
        <!-- SECTION 4 : Photos du produit (galerie)          -->
        <!-- ================================================ -->
        <div class="form-section">
            <h3 class="form-section-title">
                <i class="fas fa-images"></i>
                Photos du Produit
                <span style="font-size:14px;font-weight:400;color:var(--text-secondary);">(max 5)</span>
            </h3>
            <p class="form-help" style="margin-bottom:18px;font-size:13px;color:var(--text-secondary);">
                La première photo est l'image principale. Cliquez sur &times; pour supprimer une photo existante.
                Formats acceptés&nbsp;: JPG, PNG, GIF, WEBP — Max 5 MB par photo.
            </p>

            <input type="hidden" id="delete-inputs">
            <div class="gallery-grid">
                <?php foreach ($gallery as $gi => $gitem): ?>
                <div class="gallery-slot has-image" id="existing-slot-<?php echo $gitem['id']; ?>">
                    <div class="gallery-badge <?php echo ($gi === 0) ? '' : 'secondary'; ?>">
                        <?php echo ($gi === 0) ? 'Principale' : 'Photo ' . ($gi + 1); ?>
                    </div>

                    <img class="gallery-preview-img"
                         src="/uploads/products/<?php echo htmlspecialchars($gitem['filename']); ?>"
                         alt="Photo <?php echo ($gi + 1); ?>">

                    <button type="button"
                            class="gallery-remove-btn"
                            id="del-btn-<?php echo $gitem['id']; ?>"
                            onclick="markExistingDelete(<?php echo $gitem['id']; ?>)"
                            title="Supprimer cette photo">
                        <i class="fas fa-times"></i>
                    </button>

                    <button type="button"
                            class="gallery-undo-btn"
                            id="undo-btn-<?php echo $gitem['id']; ?>"
                            onclick="undoExistingDelete(<?php echo $gitem['id']; ?>)"
                            style="display:none">
                        Annuler
                    </button>

                    <span class="gallery-deleted-label" id="del-label-<?php echo $gitem['id']; ?>">
                        À supprimer
                    </span>
                </div>
                <?php endforeach; ?>

                <!-- Emplacements vides pour nouvelles photos -->
                <?php for ($ni = 0; $ni < $empty_slots; $ni++): ?>
                <div class="gallery-slot" id="new-slot-<?php echo $ni; ?>">
                    <div class="gallery-badge secondary">
                        Photo <?php echo count($gallery) + $ni + 1; ?>
                    </div>

                    <div class="gallery-slot-inner" id="new-inner-<?php echo $ni; ?>"
                         onclick="document.getElementById('new_gallery_<?php echo $ni; ?>').click()">
                        <i class="fas fa-plus gallery-plus-icon"></i>
                        <span class="gallery-slot-label">Cliquer pour<br>ajouter</span>
                    </div>

                    <img class="gallery-preview-img"
                         id="new-preview-<?php echo $ni; ?>"
                         src="" alt=""
                         style="display:none;position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;">

                    <button type="button"
                            class="gallery-remove-btn"
                            id="new-btn-<?php echo $ni; ?>"
                            onclick="clearNewEditSlot(<?php echo $ni; ?>)"
                            style="display:none"
                            title="Retirer cette photo">
                        <i class="fas fa-times"></i>
                    </button>

                    <input type="file"
                           name="new_gallery[<?php echo $ni; ?>]"
                           id="new_gallery_<?php echo $ni; ?>"
                           accept="image/*"
                           style="display:none"
                           onchange="previewNewEditSlot(this, <?php echo $ni; ?>)">
                </div>
                <?php endfor; ?>

            </div>
        </div>

        <!-- ================================================ -->
        <!-- SECTION 5 : Options                              -->
        <!-- ================================================ -->
        <div class="form-section">
            <h3 class="form-section-title">
                <i class="fas fa-cog"></i>
                Options
            </h3>

            <div class="form-grid">
                <div class="checkbox-group">
                    <input type="checkbox" name="is_active" id="is_active"
                           <?php echo ($product['is_active'] ?? 1) ? 'checked' : ''; ?>>
                    <label for="is_active">
                        <strong>Produit Actif</strong>
                        <div class="form-help">Le produit sera visible sur le site</div>
                    </label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" name="is_featured" id="is_featured"
                           <?php echo ($product['is_featured'] ?? 0) ? 'checked' : ''; ?>>
                    <label for="is_featured">
                        <strong>Produit Featured</strong>
                        <div class="form-help">Mis en avant sur le site</div>
                    </label>
                </div>
            </div>
        </div>

        <!-- ================================================ -->
        <!-- ACTIONS                                          -->
        <!-- ================================================ -->
        <div class="form-actions">
            <a href="products-list.php" class="btn btn-secondary">
                <i class="fas fa-times"></i>
                Annuler
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                Enregistrer les Modifications
            </button>
        </div>

    </form>
</div>

<script>
var ATL_USD_RATE = <?php echo json_encode($atl_usd_rate); ?>;

function atlPriceConvertedPreview(inputId, selectId, outId) {
    var input = document.getElementById(inputId);
    var sel   = document.getElementById(selectId);
    var out   = document.getElementById(outId);
    if (!input || !sel || !out) return;
    function update() {
        var v = parseFloat(input.value);
        if (!v || v <= 0) { out.textContent = ''; return; }
        if (sel.value === 'USD') {
            out.textContent = '≈ ' + Math.round(v * ATL_USD_RATE).toLocaleString('fr-FR') + ' HTG';
        } else {
            out.textContent = '≈ $' + Math.round(v / ATL_USD_RATE).toLocaleString('fr-FR');
        }
    }
    input.addEventListener('input', update);
    sel.addEventListener('change', update);
    update();
}
document.addEventListener('DOMContentLoaded', function() {
    atlPriceConvertedPreview('price', 'price_currency', 'price_converted');
    atlPriceConvertedPreview('old_price', 'old_price_currency', 'old_price_converted');
});

/* Validation avant soumission */
function validateEditForm() {
    const name  = document.getElementById('name').value.trim();
    const sku   = document.getElementById('sku').value.trim();
    const price = parseFloat(document.getElementById('price').value);
    const stock = parseInt(document.getElementById('stock').value);
    if (!name)  { alert('Le nom du produit est requis.'); return false; }
    if (!sku)   { alert('Le SKU est requis.'); return false; }
    if (!price || price <= 0) { alert('Le prix doit être supérieur à 0.'); return false; }
    if (isNaN(stock) || stock < 0) { alert('Le stock doit être un nombre positif.'); return false; }
    return true;
}

/* Galerie - photos existantes : marquer/annuler suppression */
function markExistingDelete(imageId) {
    const slot      = document.getElementById('existing-slot-' + imageId);
    const delBtn    = document.getElementById('del-btn-' + imageId);
    const undoBtn   = document.getElementById('undo-btn-' + imageId);
    const delLabel  = document.getElementById('del-label-' + imageId);
    const container = document.getElementById('delete-inputs');
    slot.classList.add('marked-delete');
    delBtn.style.display  = 'none';
    undoBtn.style.display = 'inline-block';
    const hidden = document.createElement('input');
    hidden.type  = 'hidden';
    hidden.name  = 'delete_gallery_ids[]';
    hidden.value = imageId;
    hidden.id    = 'hidden-del-' + imageId;
    container.appendChild(hidden);
}

function undoExistingDelete(imageId) {
    const slot     = document.getElementById('existing-slot-' + imageId);
    const delBtn   = document.getElementById('del-btn-' + imageId);
    const undoBtn  = document.getElementById('undo-btn-' + imageId);
    const delLabel = document.getElementById('del-label-' + imageId);
    const hidden   = document.getElementById('hidden-del-' + imageId);
    slot.classList.remove('marked-delete');
    delBtn.style.display   = 'flex';
    undoBtn.style.display  = 'none';
    delLabel.style.display = 'none';
    if (hidden) hidden.remove();
}

/* Galerie - nouveaux emplacements : aperçu / effacer */
function previewNewEditSlot(input, idx) {
    if (!input.files || !input.files[0]) return;
    const slot  = document.getElementById('new-slot-' + idx);
    const inner = document.getElementById('new-inner-' + idx);
    const img   = document.getElementById('new-preview-' + idx);
    const btn   = document.getElementById('new-btn-' + idx);
    const reader = new FileReader();
    reader.onload = function(e) {
        img.src             = e.target.result;
        img.style.display   = 'block';
        inner.style.display = 'none';
        btn.style.display   = 'flex';
        slot.classList.add('has-image');
    };
    reader.readAsDataURL(input.files[0]);
}

function clearNewEditSlot(idx) {
    const slot  = document.getElementById('new-slot-' + idx);
    const inner = document.getElementById('new-inner-' + idx);
    const img   = document.getElementById('new-preview-' + idx);
    const btn   = document.getElementById('new-btn-' + idx);
    const fi    = document.getElementById('new_gallery_' + idx);
    fi.value            = '';
    img.src             = '';
    img.style.display   = 'none';
    inner.style.display = 'flex';
    btn.style.display   = 'none';
    slot.classList.remove('has-image');
}

/* Sélecteur de type de produit + affichage attributs */
function selectProductType(type) {
    document.getElementById('product_type_hidden').value = type;
    document.querySelectorAll('.type-card').forEach(function(c) {
        c.classList.toggle('active', c.dataset.type === type);
    });
    document.querySelectorAll('.attr-panel').forEach(function(p) {
        p.style.display = (p.id === 'panel_' + type) ? '' : 'none';
    });
}

/* Chips toggle */
function toggleChip(el) {
    el.classList.toggle('checked');
    const chk = el.querySelector('input[type="checkbox"]');
    if (chk) chk.checked = el.classList.contains('checked');
}

/* Color swatches */
function toggleColorSwatch(el, cid) {
    el.classList.toggle('selected');
    const chk = el.querySelector('input[type="checkbox"]');
    if (chk) chk.checked = el.classList.contains('selected');
    const wrap = document.getElementById('cprice_wrap_' + cid);
    if (wrap) {
        wrap.style.display = el.classList.contains('selected') ? '' : 'none';
        if (!el.classList.contains('selected')) {
            const inp = wrap.querySelector('input[type="number"]');
            if (inp) inp.value = '';
        }
    }
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
