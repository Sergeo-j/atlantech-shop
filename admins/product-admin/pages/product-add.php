<?php
/**
 * Ajouter un Produit — AtlanTech Shop
 * Formulaire dynamique par type de produit (12 types)
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_auth();

$atl_usd_rate = atl_pa_usd_rate();

$page_title   = 'Ajouter un Produit';
$current_page = 'add-product';
$error   = '';
$success = '';

/* =====================================================================
   SCHÉMA DES ATTRIBUTS PAR TYPE
   Chaque entrée : 'n' = name_key, 'l' = label, 't' = type, 'o' = options, 'ph' = placeholder
   Types de champs : select | chips | text | number | textarea
   ===================================================================== */
$ATTR_SCHEMA = [
  'vetements' => [
    'label' => 'Vêtements (Homme / Femme / Enfant)', 'icon' => 'fa-tshirt', 'color' => '#e91e8c',
    'fields' => [
      ['n'=>'genre',          'l'=>'Genre',              't'=>'select', 'o'=>['Homme','Femme','Enfant','Unisexe']],
      ['n'=>'tailles',        'l'=>'Taille(s)',          't'=>'chips',  'o'=>['XS','S','M','L','XL','XXL','XXXL','Unique']],
      ['n'=>'matiere',        'l'=>'Matière',            't'=>'text',   'ph'=>'Ex: coton, polyester, lin...'],
      ['n'=>'motif',          'l'=>'Motif',              't'=>'select', 'o'=>['Uni','Rayé','Floral','Carreaux','Imprimé','Brodé','Autre']],
      ['n'=>'style',          'l'=>'Style',              't'=>'select', 'o'=>['Casual','Chic','Sport','Streetwear','Formel','Bohème']],
      ['n'=>'coupe',          'l'=>'Coupe',              't'=>'select', 'o'=>['Slim','Regular','Oversize','Fitted','Loose']],
      ['n'=>'saison',         'l'=>'Saison',             't'=>'chips',  'o'=>['Printemps','Été','Automne','Hiver','Toutes saisons']],
      ['n'=>'longueur',       'l'=>'Longueur',           't'=>'text',   'ph'=>'Ex: court, mi-long, long, maxi...'],
      ['n'=>'type_col',       'l'=>'Type de col',        't'=>'text',   'ph'=>'Ex: col rond, col V, polo...'],
      ['n'=>'type_manche',    'l'=>'Type de manche',     't'=>'text',   'ph'=>'Ex: sans manches, courtes, longues...'],
      ['n'=>'fermeture',      'l'=>'Fermeture',          't'=>'select', 'o'=>['Aucune','Zip','Bouton','Lacet','Velcro','Élastique']],
      ['n'=>'occasion',       'l'=>'Occasion',           't'=>'chips',  'o'=>['Quotidien','Travail','Soirée','Sport','Plage','Cérémonie']],
      ['n'=>'pays_fab',       'l'=>'Pays de fabrication','t'=>'text',  'ph'=>'Ex: Chine, Bangladesh, France...'],
      ['n'=>'poids',          'l'=>'Poids (g)',           't'=>'number', 'ph'=>'0'],
    ]
  ],
  'chaussures' => [
    'label' => 'Chaussures', 'icon' => 'fa-shoe-prints', 'color' => '#ff6b35',
    'fields' => [
      ['n'=>'genre',          'l'=>'Genre',              't'=>'select', 'o'=>['Homme','Femme','Enfant','Unisexe']],
      ['n'=>'type_chaussure', 'l'=>'Type',               't'=>'select', 'o'=>['Basket / Sneaker','Sandale','Habillée','Botte','Bottine','Escarpin','Mocassin','Tong','Autre']],
      ['n'=>'pointures',      'l'=>'Pointure(s)',        't'=>'chips',  'o'=>['35','36','37','38','39','40','41','42','43','44','45','46','47','48']],
      ['n'=>'matiere_ext',    'l'=>'Matière extérieure', 't'=>'text',   'ph'=>'Ex: cuir, synthétique, toile...'],
      ['n'=>'matiere_int',    'l'=>'Matière intérieure', 't'=>'text',   'ph'=>'Ex: cuir, textile, fourrure...'],
      ['n'=>'semelle',        'l'=>'Semelle',            't'=>'text',   'ph'=>'Ex: caoutchouc, cuir, EVA...'],
      ['n'=>'hauteur_talon',  'l'=>'Hauteur talon (cm)', 't'=>'number', 'ph'=>'0'],
      ['n'=>'fermeture',      'l'=>'Fermeture',          't'=>'select', 'o'=>['Lacets','Velcro','Zip','Élastique','Slip-on','Boucle']],
      ['n'=>'style',          'l'=>'Style',              't'=>'select', 'o'=>['Sport','Casual','Chic','Formel','Outdoor','Urbain']],
      ['n'=>'saison',         'l'=>'Saison',             't'=>'select', 'o'=>['Printemps/Été','Automne/Hiver','Toutes saisons']],
      ['n'=>'usage',          'l'=>'Usage',              't'=>'chips',  'o'=>['Sport','Ville','Travail','Soirée','Plage','Randonnée']],
      ['n'=>'poids',          'l'=>'Poids (g)',           't'=>'number', 'ph'=>'0'],
    ]
  ],
  'sacs' => [
    'label' => 'Sacs & Accessoires Mode', 'icon' => 'fa-shopping-bag', 'color' => '#9c27b0',
    'fields' => [
      ['n'=>'type_sac',       'l'=>'Type de sac',              't'=>'select', 'o'=>['Sac à main','Sac à dos','Portefeuille','Pochette','Bandoulière','Tote bag','Voyage','Ceinture']],
      ['n'=>'genre',          'l'=>'Genre',                    't'=>'select', 'o'=>['Femme','Homme','Unisexe']],
      ['n'=>'matiere',        'l'=>'Matière',                  't'=>'text',   'ph'=>'Ex: cuir, synthétique, tissu, paille...'],
      ['n'=>'dimensions',     'l'=>'Dimensions (L×H×P cm)',    't'=>'text',   'ph'=>'Ex: 35×25×12'],
      ['n'=>'nb_compart',     'l'=>'Nombre de compartiments',  't'=>'number', 'ph'=>'1'],
      ['n'=>'fermeture',      'l'=>'Fermeture',                't'=>'select', 'o'=>['Zip','Magnétique','Bouton pression','Rabat','Ouvert','Cadenas']],
      ['n'=>'style',          'l'=>'Style',                    't'=>'select', 'o'=>['Casual','Chic','Sport','Luxe','Vintage','Minimaliste']],
      ['n'=>'occasion',       'l'=>'Occasion',                 't'=>'chips',  'o'=>['Quotidien','Travail','Soirée','Voyage','Sport','Plage']],
      ['n'=>'capacite',       'l'=>'Capacité (L)',             't'=>'text',   'ph'=>'Ex: 10L, 25L...'],
      ['n'=>'poids',          'l'=>'Poids (g)',                't'=>'number', 'ph'=>'0'],
    ]
  ],
  'bijoux' => [
    'label' => 'Bijoux', 'icon' => 'fa-gem', 'color' => '#ffc107',
    'fields' => [
      ['n'=>'type_bijou',     'l'=>'Type',                     't'=>'select', 'o'=>['Collier','Bracelet','Bague','Boucles d\'oreilles','Pendentif','Montre','Broche','Parure']],
      ['n'=>'metal',          'l'=>'Métal',                    't'=>'select', 'o'=>['Acier inoxydable','Argent 925','Or 18K','Or 14K','Plaqué or','Cuivre','Titane','Autre']],
      ['n'=>'pierre',         'l'=>'Pierre',                   't'=>'text',   'ph'=>'Ex: diamant, zircon, cristal, aucune...'],
      ['n'=>'taille',         'l'=>'Taille / Circonférence',   't'=>'text',   'ph'=>'Ex: 45cm, T52, unique...'],
      ['n'=>'style',          'l'=>'Style',                    't'=>'select', 'o'=>['Classique','Moderne','Bohème','Minimaliste','Vintage','Luxe']],
      ['n'=>'hypoallergenique','l'=>'Hypoallergénique',        't'=>'select', 'o'=>['Oui','Non','Non spécifié']],
      ['n'=>'resistant_eau',  'l'=>'Résistant à l\'eau',       't'=>'select', 'o'=>['Oui','Non','Non spécifié']],
      ['n'=>'genre',          'l'=>'Genre',                    't'=>'select', 'o'=>['Femme','Homme','Unisexe']],
      ['n'=>'occasion',       'l'=>'Occasion',                 't'=>'chips',  'o'=>['Quotidien','Soirée','Mariage','Cadeau','Cérémonie']],
    ]
  ],
  'beaute' => [
    'label' => 'Beauté & Cosmétiques', 'icon' => 'fa-spa', 'color' => '#e91e63',
    'fields' => [
      ['n'=>'type_beaute',    'l'=>'Type',                     't'=>'select', 'o'=>['Fond de teint','Rouge à lèvres','Mascara','Eye-liner','Fard à paupières','Parfum','Crème visage','Sérum','Hydratant','Nettoyant','Shampoing','Après-shampoing','Soin corps','Autre']],
      ['n'=>'volume',         'l'=>'Volume / Quantité',        't'=>'text',   'ph'=>'Ex: 50ml, 100g, 1 flacon...'],
      ['n'=>'type_peau',      'l'=>'Type de peau',             't'=>'chips',  'o'=>['Normale','Sèche','Grasse','Mixte','Sensible','Toutes peaux']],
      ['n'=>'fonction',       'l'=>'Fonction(s)',              't'=>'text',   'ph'=>'Ex: hydratant, anti-âge, illuminateur...'],
      ['n'=>'ingredients',    'l'=>'Ingrédients clés',         't'=>'textarea','ph'=>'Liste des ingrédients principaux...'],
      ['n'=>'parfum_prod',    'l'=>'Parfum / Senteur',         't'=>'text',   'ph'=>'Ex: rose, vanille, sans parfum...'],
      ['n'=>'dlc',            'l'=>'DLC (après ouverture)',    't'=>'text',   'ph'=>'Ex: 12M, 24M...'],
      ['n'=>'certification',  'l'=>'Certification',            't'=>'text',   'ph'=>'Ex: Vegan, Cruelty-free, Bio, Halal...'],
      ['n'=>'usage_beaute',   'l'=>'Usage',                    't'=>'select', 'o'=>['Visage','Corps','Cheveux','Yeux','Lèvres','Mixte']],
    ]
  ],
  'electronique' => [
    'label' => 'Électronique', 'icon' => 'fa-microchip', 'color' => '#00bcd4',
    'fields' => [
      ['n'=>'sous_type',      'l'=>'Sous-catégorie',           't'=>'select', 'o'=>['Smartphone','Tablette','Télévision','Enceinte','Écouteurs / Casque','Appareil photo','Caméra','Console de jeu','Montre connectée','Drone','Autre']],
      ['n'=>'modele',         'l'=>'Modèle',                   't'=>'text',   'ph'=>'Ex: Galaxy S24 Ultra, iPhone 15 Pro...'],
      ['n'=>'reference',      'l'=>'Référence fabricant',      't'=>'text',   'ph'=>'Ex: SM-S928B...'],
      ['n'=>'couleur',        'l'=>'Couleur(s)',               't'=>'text',   'ph'=>'Ex: Noir, Blanc, Bleu titane...'],
      ['n'=>'ecran',          'l'=>'Taille écran (pouces)',    't'=>'text',   'ph'=>'Ex: 6.7"'],
      ['n'=>'resolution',     'l'=>'Résolution écran',         't'=>'text',   'ph'=>'Ex: 2556×1179 px, AMOLED...'],
      ['n'=>'ram',            'l'=>'RAM',                      't'=>'text',   'ph'=>'Ex: 8 Go, 12 Go...'],
      ['n'=>'stockage',       'l'=>'Stockage',                 't'=>'text',   'ph'=>'Ex: 128 Go, 256 Go...'],
      ['n'=>'processeur',     'l'=>'Processeur',               't'=>'text',   'ph'=>'Ex: Snapdragon 8 Gen 3, A17 Pro...'],
      ['n'=>'batterie',       'l'=>'Batterie (mAh)',           't'=>'text',   'ph'=>'Ex: 5000 mAh'],
      ['n'=>'camera_avant',   'l'=>'Caméra avant',             't'=>'text',   'ph'=>'Ex: 12 MP'],
      ['n'=>'camera_arriere', 'l'=>'Caméra arrière',           't'=>'text',   'ph'=>'Ex: 200 MP + 10 MP + 12 MP'],
      ['n'=>'sim',            'l'=>'SIM',                      't'=>'select', 'o'=>['Nano SIM','eSIM','Dual SIM','eSIM + SIM','Non applicable']],
      ['n'=>'reseau',         'l'=>'Réseau',                   't'=>'chips',  'o'=>['2G','3G','4G','5G','WiFi 6','Bluetooth 5','Non applicable']],
      ['n'=>'garantie',       'l'=>'Garantie',                 't'=>'text',   'ph'=>'Ex: 1 an, 2 ans, Sans garantie...'],
      ['n'=>'pays_origine',   'l'=>'Pays d\'origine',          't'=>'text',   'ph'=>'Ex: Chine, Corée du Sud...'],
    ]
  ],
  'informatique' => [
    'label' => 'Informatique', 'icon' => 'fa-laptop', 'color' => '#3f51b5',
    'fields' => [
      ['n'=>'sous_type',      'l'=>'Sous-catégorie',           't'=>'select', 'o'=>['Ordinateur portable','Ordinateur de bureau','Souris','Clavier','Écran / Moniteur','Imprimante','Scanner','Disque dur / SSD externe','Carte mémoire','Hub USB','Câble','Autre accessoire']],
      ['n'=>'modele',         'l'=>'Modèle',                   't'=>'text',   'ph'=>'Ex: MacBook Pro 14, Dell XPS 15...'],
      ['n'=>'processeur',     'l'=>'Processeur (CPU)',         't'=>'text',   'ph'=>'Ex: Intel Core i7-13700H, AMD Ryzen 9...'],
      ['n'=>'gen_cpu',        'l'=>'Génération CPU',           't'=>'text',   'ph'=>'Ex: 13ème génération, Zen 4...'],
      ['n'=>'ram',            'l'=>'RAM',                      't'=>'select', 'o'=>['4 Go','8 Go','16 Go','32 Go','64 Go','128 Go','Autre']],
      ['n'=>'type_ram',       'l'=>'Type de RAM',              't'=>'select', 'o'=>['DDR4','DDR5','LPDDR4','LPDDR5','Non applicable']],
      ['n'=>'stockage',       'l'=>'Stockage',                 't'=>'select', 'o'=>['128 Go','256 Go','512 Go','1 To','2 To','4 To','Autre']],
      ['n'=>'type_stockage',  'l'=>'Type de stockage',         't'=>'select', 'o'=>['SSD NVMe','SSD SATA','HDD','eMMC','Hybride (SSD+HDD)','Non applicable']],
      ['n'=>'ecran',          'l'=>'Taille écran (pouces)',    't'=>'text',   'ph'=>'Ex: 14", 15.6", 27"...'],
      ['n'=>'resolution',     'l'=>'Résolution écran',         't'=>'text',   'ph'=>'Ex: 2560×1600 (2K), 3840×2160 (4K)...'],
      ['n'=>'gpu',            'l'=>'Carte graphique (GPU)',    't'=>'text',   'ph'=>'Ex: NVIDIA RTX 4060, Intel Iris Xe...'],
      ['n'=>'batterie',       'l'=>'Batterie',                 't'=>'text',   'ph'=>'Ex: 100 Wh, 10h autonomie...'],
      ['n'=>'clavier',        'l'=>'Langue du clavier',        't'=>'select', 'o'=>['AZERTY (FR)','QWERTY (EN/US)','QWERTZ (DE)','Rétroéclairé','Autre']],
      ['n'=>'os',             'l'=>'Système d\'exploitation',  't'=>'select', 'o'=>['Windows 11','Windows 10','macOS','Linux','Chrome OS','Sans OS','Autre']],
      ['n'=>'ports',          'l'=>'Ports disponibles',        't'=>'text',   'ph'=>'Ex: USB-A×2, USB-C×2, HDMI, SD...'],
      ['n'=>'wifi_bt',        'l'=>'WiFi / Bluetooth',         't'=>'text',   'ph'=>'Ex: WiFi 6E, Bluetooth 5.3...'],
      ['n'=>'garantie',       'l'=>'Garantie',                 't'=>'text',   'ph'=>'Ex: 1 an constructeur, 2 ans...'],
      ['n'=>'connexion',      'l'=>'Connexion (accessoires)',  't'=>'chips',  'o'=>['USB-A','USB-C','Bluetooth','WiFi','2.4 GHz sans fil','Jack 3.5mm','Autre']],
      ['n'=>'compatibilite',  'l'=>'Compatibilité',            't'=>'text',   'ph'=>'Ex: Windows/Mac/Linux, USB universel...'],
    ]
  ],
  'maison' => [
    'label' => 'Maison & Décoration', 'icon' => 'fa-couch', 'color' => '#795548',
    'fields' => [
      ['n'=>'type_prod',      'l'=>'Type de produit',          't'=>'text',   'ph'=>'Ex: canapé, tableau, lampe, rideau...'],
      ['n'=>'piece',          'l'=>'Pièce',                    't'=>'chips',  'o'=>['Salon','Chambre','Cuisine','Salle de bain','Bureau','Entrée','Jardin','Toute la maison']],
      ['n'=>'matiere',        'l'=>'Matière',                  't'=>'text',   'ph'=>'Ex: bois, métal, verre, tissu, plastique...'],
      ['n'=>'couleur',        'l'=>'Couleur(s)',               't'=>'text',   'ph'=>'Ex: blanc, noir, beige naturel...'],
      ['n'=>'dimensions',     'l'=>'Dimensions (cm)',          't'=>'text',   'ph'=>'Ex: L120×H80×P45'],
      ['n'=>'poids',          'l'=>'Poids (kg)',               't'=>'text',   'ph'=>'Ex: 5.5 kg'],
      ['n'=>'style',          'l'=>'Style',                    't'=>'select', 'o'=>['Moderne','Classique','Scandinave','Industriel','Bohème','Minimaliste','Baroque','Rustique']],
      ['n'=>'installation',   'l'=>'Installation',             't'=>'select', 'o'=>['Non, prêt à l\'emploi','Oui, simple (vis)','Oui, professionnel recommandé']],
      ['n'=>'entretien',      'l'=>'Entretien',                't'=>'text',   'ph'=>'Ex: lavable, essuyage humide, démontable...'],
      ['n'=>'usage',          'l'=>'Usage',                    't'=>'select', 'o'=>['Intérieur','Extérieur','Intérieur / Extérieur']],
    ]
  ],
  'cuisine' => [
    'label' => 'Cuisine', 'icon' => 'fa-utensils', 'color' => '#ff5722',
    'fields' => [
      ['n'=>'type_prod',      'l'=>'Type',                     't'=>'select', 'o'=>['Ustensile','Électroménager','Vaisselle','Accessoire','Rangement','Autre']],
      ['n'=>'matiere',        'l'=>'Matière',                  't'=>'text',   'ph'=>'Ex: inox, plastique, céramique, silicone...'],
      ['n'=>'capacite',       'l'=>'Capacité (L)',             't'=>'text',   'ph'=>'Ex: 5L, 2.5L...'],
      ['n'=>'dimensions',     'l'=>'Dimensions (cm)',          't'=>'text',   'ph'=>'Ex: 30×20×15'],
      ['n'=>'puissance',      'l'=>'Puissance (W)',            't'=>'number', 'ph'=>'0'],
      ['n'=>'voltage',        'l'=>'Voltage',                  't'=>'select', 'o'=>['110V','220V','110-220V (universel)','Non applicable']],
      ['n'=>'compatibilite',  'l'=>'Compatibilité',            't'=>'text',   'ph'=>'Ex: induction, gaz, tous feux...'],
      ['n'=>'lave_vaisselle', 'l'=>'Lavable au lave-vaisselle','t'=>'select', 'o'=>['Oui','Non','Certaines pièces uniquement']],
      ['n'=>'garantie',       'l'=>'Garantie',                 't'=>'text',   'ph'=>'Ex: 2 ans, 1 an...'],
    ]
  ],
  'sport' => [
    'label' => 'Sport & Fitness', 'icon' => 'fa-dumbbell', 'color' => '#4caf50',
    'fields' => [
      ['n'=>'type_sport',     'l'=>'Sport concerné',           't'=>'text',   'ph'=>'Ex: football, yoga, natation, musculation...'],
      ['n'=>'niveau',         'l'=>'Niveau',                   't'=>'select', 'o'=>['Débutant','Intermédiaire','Avancé','Professionnel','Tous niveaux']],
      ['n'=>'taille',         'l'=>'Taille(s)',                't'=>'text',   'ph'=>'Ex: S/M/L ou 30×50 cm ou unique...'],
      ['n'=>'couleur',        'l'=>'Couleur(s)',               't'=>'text',   'ph'=>'Ex: noir, rouge, multicolore...'],
      ['n'=>'matiere',        'l'=>'Matière',                  't'=>'text',   'ph'=>'Ex: néoprène, coton, polyester, caoutchouc...'],
      ['n'=>'poids',          'l'=>'Poids (kg)',               't'=>'text',   'ph'=>'Ex: 2 kg, 5 kg...'],
      ['n'=>'dimensions',     'l'=>'Dimensions (cm)',          't'=>'text',   'ph'=>'Ex: 183×61 cm...'],
      ['n'=>'resistance',     'l'=>'Résistance / Charge max',  't'=>'text',   'ph'=>'Ex: 120 kg, niveau 3/5...'],
      ['n'=>'genre',          'l'=>'Genre',                    't'=>'select', 'o'=>['Mixte','Homme','Femme','Enfant']],
    ]
  ],
  'jouets' => [
    'label' => 'Jouets & Enfants', 'icon' => 'fa-baby', 'color' => '#ff9800',
    'fields' => [
      ['n'=>'age_min',        'l'=>'Âge minimum recommandé',   't'=>'select', 'o'=>['0+ mois','3+ mois','6+ mois','12+ mois','18+ mois','2+ ans','3+ ans','4+ ans','5+ ans','6+ ans','8+ ans','10+ ans','12+ ans','Adulte']],
      ['n'=>'type_jouet',     'l'=>'Type de jouet',            't'=>'select', 'o'=>['Peluche','Jeu de construction','Jeu éducatif','Poupée / Figurine','Voiture / Véhicule','Jeu de société','Puzzle','Jouet musical','Jouet d\'éveil','Déguisement','Autre']],
      ['n'=>'matiere',        'l'=>'Matière',                  't'=>'text',   'ph'=>'Ex: plastique ABS, bois, tissu, métal...'],
      ['n'=>'dimensions',     'l'=>'Dimensions (cm)',          't'=>'text',   'ph'=>'Ex: 30×20 cm...'],
      ['n'=>'securite',       'l'=>'Certifications sécurité',  't'=>'text',   'ph'=>'Ex: CE, EN71, ASTM...'],
      ['n'=>'batterie',       'l'=>'Batterie requise',         't'=>'select', 'o'=>['Non','Oui — incluse','Oui — non incluse (préciser)']],
      ['n'=>'type_batterie',  'l'=>'Type de batterie',         't'=>'text',   'ph'=>'Ex: 3×AA, Rechargeable USB...'],
      ['n'=>'fonction',       'l'=>'Fonctions',                't'=>'text',   'ph'=>'Ex: lumineux, musical, télécommandé...'],
      ['n'=>'genre',          'l'=>'Genre',                    't'=>'select', 'o'=>['Fille','Garçon','Mixte']],
    ]
  ],
  'automobile' => [
    'label' => 'Automobile', 'icon' => 'fa-car', 'color' => '#607d8b',
    'fields' => [
      ['n'=>'type_piece',     'l'=>'Type pièce / accessoire',  't'=>'text',   'ph'=>'Ex: filtre à huile, tapis de sol, caméra de recul...'],
      ['n'=>'marque_compat',  'l'=>'Marque compatible',        't'=>'text',   'ph'=>'Ex: Toyota, Honda, Universel...'],
      ['n'=>'modele_vehic',   'l'=>'Modèle de véhicule',       't'=>'text',   'ph'=>'Ex: Corolla, Civic, Tous modèles...'],
      ['n'=>'annee_vehic',    'l'=>'Année(s) compatible(s)',   't'=>'text',   'ph'=>'Ex: 2018-2024, Toutes années...'],
      ['n'=>'matiere',        'l'=>'Matière',                  't'=>'text',   'ph'=>'Ex: plastique ABS, aluminium, caoutchouc...'],
      ['n'=>'dimensions',     'l'=>'Dimensions (cm)',          't'=>'text',   'ph'=>'Ex: 50×30 cm...'],
      ['n'=>'installation',   'l'=>'Installation',             't'=>'select', 'o'=>['Plug & Play','Bricolage simple','Mécanicien recommandé','Professionnel requis']],
      ['n'=>'garantie',       'l'=>'Garantie',                 't'=>'text',   'ph'=>'Ex: 1 an, 2 ans...'],
    ]
  ],
];

/* =====================================================================
   TRAITEMENT DU FORMULAIRE POST
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $error = 'Token de sécurité invalide';
    } else {
        $allowed_conditions = ['new','refurbished','used_like_new','used_good','used_acceptable'];
        $cond = (string)($_POST['condition'] ?? 'new');
        if (!in_array($cond, $allowed_conditions, true)) $cond = 'new';

        $product_type = clean_input($_POST['product_type'] ?? '');

        // Construire le JSON des attributs
        $attributes_json = null;
        if (!empty($product_type) && isset($ATTR_SCHEMA[$product_type])) {
            $attrs = [];
            foreach ($ATTR_SCHEMA[$product_type]['fields'] as $f) {
                $val = $_POST['attr_' . $f['n']] ?? '';
                if (is_array($val)) {
                    $val = array_values(array_filter(array_map('trim', $val)));
                    if (!empty($val)) $attrs[$f['n']] = $val;
                } else {
                    $val = trim($val);
                    if ($val !== '') $attrs[$f['n']] = $val;
                }
            }
            $attributes_json = !empty($attrs) ? json_encode($attrs, JSON_UNESCAPED_UNICODE) : null;
        }

        $data = [
            'name'              => clean_input($_POST['name'] ?? ''),
            'sku'               => clean_input($_POST['sku'] ?? ''),
            'short_description' => clean_input($_POST['short_description'] ?? ''),
            'description'       => clean_input($_POST['description'] ?? ''),
            'price'             => atl_pa_to_htg(floatval($_POST['price'] ?? 0), ($_POST['price_currency'] ?? 'HTG') === 'USD' ? 'USD' : 'HTG'),
            'old_price'         => !empty($_POST['old_price']) ? atl_pa_to_htg(floatval($_POST['old_price']), ($_POST['old_price_currency'] ?? 'HTG') === 'USD' ? 'USD' : 'HTG') : null,
            'stock'             => intval($_POST['stock'] ?? 0),
            'stock_threshold'   => intval($_POST['stock_threshold'] ?? 5),
            'category_id'       => !empty($_POST['category_id']) ? intval($_POST['category_id']) : null,
            'brand_id'          => !empty($_POST['brand_id']) ? intval($_POST['brand_id']) : null,
            'condition'         => $cond,
            'product_type'      => $product_type ?: null,
            'attributes'        => $attributes_json,
            'is_active'         => isset($_POST['is_active']) ? 1 : 0,
            'is_featured'       => isset($_POST['is_featured']) ? 1 : 0,
            'image'             => null
        ];

        if (empty($data['name'])) {
            $error = 'Le nom du produit est requis';
        } elseif (empty($data['sku'])) {
            $error = 'Le SKU est requis';
        } elseif ($data['price'] <= 0) {
            $error = 'Le prix doit être supérieur à 0';
        } elseif ($data['stock'] < 0) {
            $error = 'Le stock ne peut pas être négatif';
        } else {
            $gallery_uploads = [];
            if (isset($_FILES['gallery'])) {
                $gf = $_FILES['gallery'];
                for ($i = 0; $i < 5; $i++) {
                    if (isset($gf['error'][$i]) && $gf['error'][$i] === UPLOAD_ERR_OK) {
                        $file = ['name'=>$gf['name'][$i],'type'=>$gf['type'][$i],'tmp_name'=>$gf['tmp_name'][$i],'error'=>$gf['error'][$i],'size'=>$gf['size'][$i]];
                        $upload_result = upload_product_image($file);
                        if ($upload_result['success']) {
                            if ($i === 0) $data['image'] = $upload_result['filename'];
                            $gallery_uploads[] = ['filename'=>$upload_result['filename'],'is_primary'=>($i === 0) ? 1 : 0];
                        } else {
                            $error = 'Photo ' . ($i + 1) . ' : ' . $upload_result['error'];
                            break;
                        }
                    }
                }
            }

            if (empty($error)) {
                $new_id = create_product($data);
                if ($new_id) {
                    foreach ($gallery_uploads as $gu) {
                        if (!$gu['is_primary']) add_gallery_image($new_id, $gu['filename'], 0);
                    }
                    $posted_color_ids    = $_POST['color_ids'] ?? [];
                    $posted_color_prices = $_POST['color_prices'] ?? [];
                    if (is_array($posted_color_ids)) {
                        set_product_colors($new_id, $posted_color_ids, is_array($posted_color_prices) ? $posted_color_prices : []);
                    }
                    log_admin_action('product_created', 'Produit créé : ' . $data['name']);
                    header('Location: products-list.php?success=added');
                    exit();
                } else {
                    $error = 'Erreur lors de la création du produit';
                }
            }
        }
    }
}

$categories = get_all_categories();
$brands     = get_all_brands();
$colors     = get_all_colors();

// Préparer la liste des types pour le JS
$attr_schema_js = [];
foreach ($ATTR_SCHEMA as $type_key => $type_def) {
    $attr_schema_js[$type_key] = array_column($type_def['fields'], 'n');
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ── Layout ── */
.form-grid   { display:grid; grid-template-columns:repeat(auto-fit,minmax(300px,1fr)); gap:20px; }
.form-grid-3 { display:grid; grid-template-columns:repeat(3,1fr); gap:20px; }
.form-section { margin-bottom:30px; padding-bottom:30px; border-bottom:1px solid var(--border-color); }
.form-section:last-child { border-bottom:none; }
.form-section-title { font-size:18px; font-weight:600; color:var(--neon-cyan); margin-bottom:20px; display:flex; align-items:center; gap:10px; }
.form-actions { display:flex; gap:15px; justify-content:flex-end; padding-top:30px; border-top:1px solid var(--border-color); margin-top:30px; }
.required-indicator { color:#ff0000; margin-left:4px; }
.form-help { font-size:12px; color:var(--text-secondary); margin-top:5px; }

/* ── Type de produit — grille de cartes ── */
.type-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 12px;
    margin-top: 12px;
}
.type-card {
    position: relative;
    border: 2px solid var(--border-color);
    border-radius: 12px;
    padding: 16px 12px 12px;
    text-align: center;
    cursor: pointer;
    transition: all .2s;
    background: rgba(255,255,255,.03);
}
.type-card:hover  { border-color: var(--neon-cyan); background: rgba(255,255,255,.06); }
.type-card.active { border-color: var(--neon-cyan); background: rgba(0,188,212,.12); }
.type-card input  { position:absolute; opacity:0; width:0; height:0; }
.type-card-icon   { font-size:26px; margin-bottom:8px; }
.type-card-label  { font-size:12px; font-weight:600; line-height:1.3; }

/* ── Panneaux d'attributs ── */
.attr-panel { display:none; margin-top:20px; }
.attr-panel.active { display:block; }
.attr-panel-title { font-size:15px; font-weight:600; margin-bottom:18px; padding:10px 16px; border-radius:8px; display:flex; align-items:center; gap:10px; }
.attr-fields { display:grid; grid-template-columns:repeat(auto-fill, minmax(260px,1fr)); gap:16px; }
.attr-field textarea.form-textarea { min-height:80px; }

/* ── Chips (cases cochables) ── */
.chips-wrap { display:flex; flex-wrap:wrap; gap:8px; margin-top:6px; }
.chip-label { display:inline-flex; align-items:center; gap:6px; padding:5px 12px; border:2px solid var(--border-color); border-radius:999px; cursor:pointer; font-size:12px; font-weight:500; transition:all .15s; user-select:none; }
.chip-label:hover { border-color:var(--neon-cyan); }
.chip-label input { display:none; }
.chip-label.checked { border-color:var(--neon-cyan); background:rgba(0,188,212,.15); color:var(--neon-cyan); }

/* ── Galerie ── */
.gallery-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:14px; }
.gallery-slot { position:relative; border:2px dashed var(--border-color); border-radius:10px; overflow:hidden; aspect-ratio:1; background:rgba(255,255,255,.03); transition:border-color .25s; }
.gallery-slot:hover { border-color:var(--neon-cyan); }
.gallery-slot.has-image { border-style:solid; }
.gallery-slot-inner { width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:8px; cursor:pointer; padding:10px; }
.gallery-plus-icon  { font-size:26px; color:var(--text-secondary); }
.gallery-slot-label { font-size:11px; color:var(--text-secondary); text-align:center; line-height:1.3; }
.gallery-slot .gallery-preview-img { width:100%; height:100%; object-fit:cover; display:block; }
.gallery-badge { position:absolute; top:6px; left:6px; font-size:10px; padding:2px 8px; border-radius:10px; background:var(--neon-cyan); color:#000; font-weight:700; z-index:2; pointer-events:none; white-space:nowrap; }
.gallery-badge.secondary { background:rgba(255,255,255,.18); color:var(--text-primary); font-weight:500; }
.gallery-remove-btn { position:absolute; top:6px; right:6px; width:24px; height:24px; border-radius:50%; background:rgba(220,50,50,.85); color:#fff; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:11px; z-index:3; }
.gallery-remove-btn:hover { background:#ff2222; }

/* ── Couleurs — swatches ── */
.color-swatch-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(80px,1fr)); gap:10px; margin-top:8px; }
.color-swatch { display:flex; flex-direction:column; align-items:center; gap:6px; padding:10px 6px; border:2px solid var(--border-color); border-radius:12px; cursor:pointer; background:rgba(255,255,255,.03); transition:all .2s; user-select:none; }
.color-swatch:hover { border-color:var(--neon-cyan); background:rgba(0,188,212,.08); }
.color-swatch.selected { border-color:var(--neon-cyan); background:rgba(0,188,212,.12); }
.color-swatch input { display:none; }
.color-swatch-dot { width:36px; height:36px; border-radius:50%; border:3px solid rgba(255,255,255,.2); box-shadow:0 2px 8px rgba(0,0,0,.35); position:relative; flex-shrink:0; transition:transform .15s; }
.color-swatch.selected .color-swatch-dot { border-color:var(--neon-cyan); box-shadow:0 0 0 3px var(--neon-cyan),0 2px 8px rgba(0,0,0,.35); transform:scale(1.1); }
.color-swatch-name { font-size:11px; font-weight:600; color:var(--text-secondary); text-align:center; line-height:1.2; }
.color-swatch.selected .color-swatch-name { color:var(--neon-cyan); }
.color-swatch-check { position:absolute; top:-5px; right:-5px; width:16px; height:16px; border-radius:50%; background:var(--neon-cyan); color:#000; font-size:8px; display:none; align-items:center; justify-content:center; font-weight:900; }
.color-swatch.selected .color-swatch-check { display:flex; }
.color-price-row { margin-top:12px; display:flex; flex-wrap:wrap; gap:8px; }
.color-price-item { display:flex; align-items:center; gap:8px; background:rgba(255,255,255,.05); border:1px solid var(--border-color); border-radius:8px; padding:6px 10px; }
.color-price-dot { width:14px; height:14px; border-radius:50%; flex-shrink:0; border:2px solid rgba(255,255,255,.2); }
.color-price-label { font-size:11px; font-weight:600; color:var(--text-secondary); white-space:nowrap; }
.color-price-input { width:150px; padding:4px 8px; border:1px solid var(--border-color); border-radius:6px; font-size:12px; background:var(--bg-secondary); color:var(--text-primary); }
.price-currency-wrap { display:flex; gap:6px; }
.price-currency-wrap .form-input { flex:1; }
.currency-select { width:82px; padding:8px 6px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-secondary); color:var(--text-primary); font-size:13px; }
.price-converted-preview { color:var(--text-secondary); font-size:12px; margin-top:4px; min-height:16px; }

/* ── Checkbox group ── */
.checkbox-group { display:flex; align-items:center; gap:10px; padding:12px 15px; background:rgba(255,255,255,.05); border:1px solid var(--border-color); border-radius:8px; cursor:pointer; transition:all .3s; }
.checkbox-group:hover { background:rgba(255,255,255,.08); border-color:var(--neon-cyan); }
.checkbox-group input[type="checkbox"] { width:20px; height:20px; cursor:pointer; }
.checkbox-group label { cursor:pointer; margin:0; flex:1; }

@media(max-width:900px){ .gallery-grid { grid-template-columns:repeat(3,1fr); } }
@media(max-width:768px){ .form-grid-3 { grid-template-columns:1fr; } .gallery-grid { grid-template-columns:repeat(2,1fr); } .type-grid { grid-template-columns:repeat(3,1fr); } .attr-fields { grid-template-columns:1fr; } }
</style>


<div class="page-header">
    <h1><i class="fas fa-plus-circle"></i> Ajouter un Produit</h1>
</div>

<?php if ($error): ?>
<div class="alert alert-danger"><i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?></div>
<?php endif; ?>

<div class="card">
<form method="POST" enctype="multipart/form-data" onsubmit="return validateProductForm()">
<input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
<input type="hidden" name="product_type" id="product_type_hidden" value="<?php echo htmlspecialchars($_POST['product_type'] ?? ''); ?>">

<!-- Section 1 -->
<div class="form-section">
    <h3 class="form-section-title"><i class="fas fa-info-circle"></i> Informations de Base</h3>

    <div class="form-group">
        <label class="form-label">Nom du Produit <span class="required-indicator">*</span></label>
        <input type="text" name="name" id="name" class="form-input" required
               placeholder="Ex: iPhone 15 Pro Max 256GB"
               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
    </div>

    <div class="form-group">
        <label class="form-label">Description courte</label>
        <input type="text" name="short_description" class="form-input"
               placeholder="1-2 phrases accrocheuses pour les listes produits (max 200 caractères)"
               maxlength="500"
               value="<?php echo htmlspecialchars($_POST['short_description'] ?? ''); ?>">
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">SKU (Code Produit) <span class="required-indicator">*</span></label>
            <input type="text" name="sku" id="sku" class="form-input" required
                   placeholder="Ex: APL-IP15PM-256-001"
                   value="<?php echo htmlspecialchars($_POST['sku'] ?? ''); ?>">
            <div class="form-help">Code unique pour identifier le produit</div>
        </div>
        <div class="form-group">
            <label class="form-label">Catégorie <span class="required-indicator">*</span></label>
            <select name="category_id" class="form-select" required>
                <option value="">Sélectionner une catégorie...</option>
                <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_POST['category_id']) && $_POST['category_id'] == $cat['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($cat['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-grid">
        <div class="form-group">
            <label class="form-label">Marque</label>
            <select name="brand_id" class="form-select">
                <option value="">Sélectionner une marque...</option>
                <?php foreach ($brands as $brand): ?>
                <option value="<?php echo $brand['id']; ?>" <?php echo (isset($_POST['brand_id']) && $_POST['brand_id'] == $brand['id']) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($brand['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label class="form-label">État du produit <span class="required-indicator">*</span></label>
            <?php
            $cond_options  = ['new'=>'Neuf','refurbished'=>'Reconditionné','used_like_new'=>'Comme neuf','used_good'=>'Bon état','used_acceptable'=>'État acceptable'];
            $selected_cond = $_POST['condition'] ?? 'new';
            ?>
            <select name="condition" class="form-select" required>
                <?php foreach ($cond_options as $val => $lbl): ?>
                <option value="<?php echo $val; ?>" <?php echo ($selected_cond === $val) ? 'selected' : ''; ?>><?php echo $lbl; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label class="form-label">Description complète</label>
        <textarea name="description" class="form-textarea" rows="5"
                  placeholder="Description détaillée du produit..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
    </div>
</div>

<!-- Section 2 — Type & Attributs -->
<div class="form-section">
    <h3 class="form-section-title"><i class="fas fa-tags"></i> Type de Produit &amp; Attributs</h3>
    <div class="form-help" style="margin-bottom:14px;">Sélectionnez le type pour afficher les champs d'attributs spécifiques.</div>

    <div class="type-grid">
        <?php foreach ($ATTR_SCHEMA as $type_key => $type_def):
            $is_active = (($_POST['product_type'] ?? '') === $type_key);
        ?>
        <div class="type-card <?php echo $is_active ? 'active' : ''; ?>"
             data-type="<?php echo $type_key; ?>"
             onclick="selectProductType('<?php echo $type_key; ?>')"
             style="--tc:<?php echo $type_def['color']; ?>">
            <div class="type-card-icon"><i class="fas <?php echo $type_def['icon']; ?>"></i></div>
            <div class="type-card-label"><?php echo $type_def['label']; ?></div>
        </div>
        <?php endforeach; ?>
        <div class="type-card <?php echo empty($_POST['product_type']) ? 'active' : ''; ?>"
             data-type=""
             onclick="selectProductType('')"
             style="--tc:#888">
            <div class="type-card-icon"><i class="fas fa-ban"></i></div>
            <div class="type-card-label">Non spécifié</div>
        </div>
    </div>

    <?php foreach ($ATTR_SCHEMA as $type_key => $type_def):
        $is_active = (($_POST['product_type'] ?? '') === $type_key);
    ?>
    <div class="attr-panel <?php echo $is_active ? 'active' : ''; ?>" id="panel_<?php echo $type_key; ?>">
        <div class="attr-panel-title" style="background:<?php echo $type_def['color']; ?>22; border-left:4px solid <?php echo $type_def['color']; ?>; color:<?php echo $type_def['color']; ?>">
            <i class="fas <?php echo $type_def['icon']; ?>"></i>
            Attributs — <?php echo $type_def['label']; ?>
        </div>
        <div class="attr-fields">
        <?php foreach ($type_def['fields'] as $f):
            $fval = $_POST['attr_' . $f['n']] ?? '';
        ?>
            <div class="form-group">
                <label class="form-label"><?php echo htmlspecialchars($f['l']); ?></label>
                <?php if ($f['t'] === 'select'): ?>
                    <select name="attr_<?php echo $f['n']; ?>" class="form-select">
                        <option value="">— Sélectionner —</option>
                        <?php foreach ($f['o'] as $opt): ?>
                        <option value="<?php echo htmlspecialchars($opt); ?>" <?php echo ($fval === $opt) ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($opt); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                <?php elseif ($f['t'] === 'chips'): ?>
                    <?php $selected_chips = is_array($fval) ? $fval : (empty($fval) ? [] : [$fval]); ?>
                    <div class="chips-wrap">
                        <?php foreach ($f['o'] as $opt):
                            $is_chk = in_array($opt, $selected_chips);
                        ?>
                        <span class="chip-label <?php echo $is_chk ? 'checked' : ''; ?>"
                              onclick="toggleChip(this)">
                            <input type="checkbox" name="attr_<?php echo $f['n']; ?>[]"
                                   value="<?php echo htmlspecialchars($opt); ?>"
                                   style="display:none;pointer-events:none"
                                   <?php echo $is_chk ? 'checked' : ''; ?>>
                            <?php echo htmlspecialchars($opt); ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                <?php elseif ($f['t'] === 'textarea'): ?>
                    <textarea name="attr_<?php echo $f['n']; ?>" class="form-textarea"
                              rows="3" placeholder="<?php echo htmlspecialchars($f['ph'] ?? ''); ?>"><?php echo htmlspecialchars(is_string($fval) ? $fval : ''); ?></textarea>
                <?php elseif ($f['t'] === 'number'): ?>
                    <input type="number" name="attr_<?php echo $f['n']; ?>" class="form-input"
                           step="any" min="0"
                           placeholder="<?php echo htmlspecialchars($f['ph'] ?? '0'); ?>"
                           value="<?php echo htmlspecialchars(is_scalar($fval) ? $fval : ''); ?>">
                <?php else: ?>
                    <input type="text" name="attr_<?php echo $f['n']; ?>" class="form-input"
                           placeholder="<?php echo htmlspecialchars($f['ph'] ?? ''); ?>"
                           value="<?php echo htmlspecialchars(is_array($fval) ? implode(', ', $fval) : $fval); ?>">
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Section 3 — Couleurs -->
<div class="form-section">
    <h3 class="form-section-title"><i class="fas fa-palette"></i> Couleurs Disponibles</h3>
    <?php $posted_colors = $_POST['color_ids'] ?? []; if (!is_array($posted_colors)) $posted_colors = []; ?>

    <!-- Grille de swatches -->
    <div class="color-swatch-grid">
        <?php foreach ($colors as $c):
            $cid     = (int)$c['id'];
            $checked = in_array((string)$cid, array_map('strval', $posted_colors), true);
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
            $checked = in_array((string)$cid, array_map('strval', $posted_colors), true);
            $cprice  = $_POST['color_prices'][$cid] ?? '';
        ?>
        <div class="color-price-item" id="cprice_wrap_<?php echo $cid; ?>"
             style="<?php echo $checked ? '' : 'display:none;'; ?>">
            <span class="color-price-dot" style="background:<?php echo htmlspecialchars($c['hex_code']); ?>;"></span>
            <span class="color-price-label"><?php echo htmlspecialchars($c['name']); ?></span>
            <input type="number" step="0.01" min="0"
                   name="color_prices[<?php echo $cid; ?>]"
                   class="color-price-input"
                   placeholder="Prix spécifique (HTG)"
                   value="<?php echo htmlspecialchars($cprice); ?>">
        </div>
        <?php endforeach; ?>
    </div>

    <div class="form-help">Cliquez sur une couleur pour la sélectionner. Laissez le prix vide pour utiliser le prix de base.</div>
</div>

<!-- Section 4 — Prix & Stock -->
<div class="form-section">
    <h3 class="form-section-title"><i class="fas fa-dollar-sign"></i> Prix et Stock</h3>
    <div class="form-grid-3">
        <div class="form-group">
            <label class="form-label">Prix <span class="required-indicator">*</span></label>
            <div class="price-currency-wrap">
                <input type="number" step="0.01" name="price" id="price" class="form-input" required min="0"
                       placeholder="0.00" value="<?php echo htmlspecialchars($_POST['price'] ?? ''); ?>">
                <select name="price_currency" id="price_currency" class="currency-select">
                    <option value="HTG" <?php echo (($_POST['price_currency'] ?? 'HTG') === 'HTG') ? 'selected' : ''; ?>>HTG</option>
                    <option value="USD" <?php echo (($_POST['price_currency'] ?? 'HTG') === 'USD') ? 'selected' : ''; ?>>USD</option>
                </select>
            </div>
            <div class="form-help price-converted-preview" id="price_converted"></div>
        </div>
        <div class="form-group">
            <label class="form-label">Ancien Prix</label>
            <div class="price-currency-wrap">
                <input type="number" step="0.01" name="old_price" id="old_price" class="form-input" min="0"
                       placeholder="0.00" value="<?php echo htmlspecialchars($_POST['old_price'] ?? ''); ?>">
                <select name="old_price_currency" id="old_price_currency" class="currency-select">
                    <option value="HTG" <?php echo (($_POST['old_price_currency'] ?? 'HTG') === 'HTG') ? 'selected' : ''; ?>>HTG</option>
                    <option value="USD" <?php echo (($_POST['old_price_currency'] ?? 'HTG') === 'USD') ? 'selected' : ''; ?>>USD</option>
                </select>
            </div>
            <div class="form-help price-converted-preview" id="old_price_converted"></div>
            <div class="form-help">Pour afficher une réduction</div>
        </div>
        <div class="form-group">
            <label class="form-label">Stock <span class="required-indicator">*</span></label>
            <input type="number" name="stock" id="stock" class="form-input" required min="0"
                   placeholder="0" value="<?php echo htmlspecialchars($_POST['stock'] ?? '0'); ?>">
        </div>
    </div>
    <div class="form-group">
        <label class="form-label">Seuil d'Alerte Stock</label>
        <input type="number" name="stock_threshold" class="form-input" min="0"
               placeholder="5" value="<?php echo htmlspecialchars($_POST['stock_threshold'] ?? '5'); ?>">
        <div class="form-help">Alerte quand le stock atteint ce seuil</div>
    </div>
</div>

<!-- Section 5 — Photos -->
<div class="form-section">
    <h3 class="form-section-title"><i class="fas fa-images"></i> Photos du Produit <span style="font-size:13px;font-weight:400;color:var(--text-secondary);">(max 5)</span></h3>
    <p class="form-help" style="margin-bottom:18px;">La première photo sera l'image principale. Formats : JPG, PNG, GIF, WEBP — Max 5 MB.</p>
    <div class="gallery-grid" id="gallery-grid">
        <?php for ($gi = 0; $gi < 5; $gi++): ?>
        <div class="gallery-slot" id="slot-<?php echo $gi; ?>">
            <div class="gallery-badge <?php echo $gi > 0 ? 'secondary' : ''; ?>">
                <?php echo $gi === 0 ? 'Principale' : 'Photo ' . ($gi + 1); ?>
            </div>
            <div class="gallery-slot-inner" id="slot-inner-<?php echo $gi; ?>"
                 onclick="document.getElementById('gallery_<?php echo $gi; ?>').click()">
                <i class="fas fa-plus gallery-plus-icon"></i>
                <span class="gallery-slot-label">Cliquer pour<br>ajouter</span>
            </div>
            <img class="gallery-preview-img" id="gpreview_<?php echo $gi; ?>" src="" alt=""
                 style="display:none;position:absolute;top:0;left:0;width:100%;height:100%;object-fit:cover;">
            <button type="button" class="gallery-remove-btn" id="gbtn_<?php echo $gi; ?>"
                    onclick="clearGallerySlot(<?php echo $gi; ?>)" style="display:none" title="Retirer">
                <i class="fas fa-times"></i>
            </button>
            <input type="file" name="gallery[<?php echo $gi; ?>]" id="gallery_<?php echo $gi; ?>"
                   accept="image/*" style="display:none"
                   onchange="previewGallerySlot(this, <?php echo $gi; ?>)">
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- Section 6 — Options -->
<div class="form-section">
    <h3 class="form-section-title"><i class="fas fa-cog"></i> Options</h3>
    <div class="form-grid">
        <div class="checkbox-group">
            <input type="checkbox" name="is_active" id="is_active"
                   <?php echo (isset($_POST['is_active']) || !isset($_POST['name'])) ? 'checked' : ''; ?>>
            <label for="is_active">
                <strong>Produit Actif</strong>
                <div class="form-help">Le produit sera visible sur le site</div>
            </label>
        </div>
        <div class="checkbox-group">
            <input type="checkbox" name="is_featured" id="is_featured"
                   <?php echo isset($_POST['is_featured']) ? 'checked' : ''; ?>>
            <label for="is_featured">
                <strong>Produit Featured</strong>
                <div class="form-help">Mis en avant sur la page d'accueil</div>
            </label>
        </div>
    </div>
</div>

<div class="form-actions">
    <a href="products-list.php" class="btn btn-secondary"><i class="fas fa-times"></i> Annuler</a>
    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Enregistrer le Produit</button>
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

function selectProductType(type) {
    document.getElementById('product_type_hidden').value = type;
    document.querySelectorAll('.type-card').forEach(function(c) {
        c.classList.toggle('active', c.dataset.type === type);
    });
    document.querySelectorAll('.attr-panel').forEach(function(p) {
        p.classList.toggle('active', p.id === 'panel_' + type);
    });
}

function toggleChip(el) {
    el.classList.toggle('checked');
    var chk = el.querySelector('input[type="checkbox"]');
    if (chk) chk.checked = el.classList.contains('checked');
}

function toggleColorSwatch(el, cid) {
    el.classList.toggle('selected');
    var chk = el.querySelector('input[type="checkbox"]');
    if (chk) chk.checked = el.classList.contains('selected');
    var wrap = document.getElementById('cprice_wrap_' + cid);
    if (wrap) {
        wrap.style.display = el.classList.contains('selected') ? '' : 'none';
        if (!el.classList.contains('selected')) {
            var inp = wrap.querySelector('input[type="number"]');
            if (inp) inp.value = '';
        }
    }
}

function validateProductForm() {
    var name  = document.getElementById('name').value.trim();
    var sku   = document.getElementById('sku').value.trim();
    var price = parseFloat(document.getElementById('price').value);
    var stock = parseInt(document.getElementById('stock').value);
    if (!name)                { alert('Le nom du produit est requis'); return false; }
    if (!sku)                 { alert('Le SKU est requis'); return false; }
    if (!price || price <= 0) { alert('Le prix doit être supérieur à 0'); return false; }
    if (isNaN(stock)||stock<0){ alert('Le stock doit être un nombre positif'); return false; }
    return true;
}

/* color swatches handled by toggleColorSwatch() */

function previewGallerySlot(input, idx) {
    if (!input.files || !input.files[0]) return;
    var slot  = document.getElementById('slot-' + idx);
    var inner = document.getElementById('slot-inner-' + idx);
    var img   = document.getElementById('gpreview_' + idx);
    var btn   = document.getElementById('gbtn_' + idx);
    var reader = new FileReader();
    reader.onload = function(e) {
        img.src = e.target.result; img.style.display = 'block';
        inner.style.display = 'none'; btn.style.display = 'flex';
        slot.classList.add('has-image');
    };
    reader.readAsDataURL(input.files[0]);
}

function clearGallerySlot(idx) {
    document.getElementById('gallery_' + idx).value = '';
    document.getElementById('gpreview_' + idx).src   = '';
    document.getElementById('gpreview_' + idx).style.display = 'none';
    document.getElementById('slot-inner-' + idx).style.display = 'flex';
    document.getElementById('gbtn_' + idx).style.display = 'none';
    document.getElementById('slot-' + idx).classList.remove('has-image');
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
