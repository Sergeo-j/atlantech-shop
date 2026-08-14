<?php
$dg_base = dg_base_url();
// Page courante pour mettre le lien actif
$current = basename($_SERVER['SCRIPT_NAME']);

$nav = [
    ['url' => $dg_base . '/index.php',                       'label' => 'Tableau de bord',     'icon' => 'fa-chart-line',     'match' => 'index.php'],
    ['url' => $dg_base . '/pages/admins-list.php',           'label' => 'Gestion des admins',  'icon' => 'fa-users-cog',      'match' => 'admins-list.php'],
    ['url' => $dg_base . '/pages/admin-create.php',          'label' => 'Créer un admin',      'icon' => 'fa-user-plus',      'match' => 'admin-create.php'],
    ['url' => $dg_base . '/pages/clients-list.php',          'label' => 'Clients',             'icon' => 'fa-users',          'match' => 'clients-list.php|client-view.php'],
    ['url' => $dg_base . '/pages/commissions.php',           'label' => 'Commissions',         'icon' => 'fa-money-bill-wave','match' => 'commissions.php'],
    ['url' => $dg_base . '/pages/commission-rules.php',      'label' => 'Taux de commission',  'icon' => 'fa-percentage',     'match' => 'commission-rules.php'],
    ['url' => $dg_base . '/pages/shipping-rates.php',        'label' => 'Tarifs livraison',    'icon' => 'fa-truck',          'match' => 'shipping-rates.php'],
    ['url' => $dg_base . '/pages/taux-change.php',           'label' => 'Taux de Change',      'icon' => 'fa-dollar-sign',    'match' => 'taux-change.php'],
    ['url' => $dg_base . '/pages/catalogue.php',             'label' => 'Catalogue',           'icon' => 'fa-th-large',       'match' => 'catalogue.php'],
    ['url' => $dg_base . '/pages/promo-codes.php',           'label' => 'Codes promo',         'icon' => 'fa-tags',           'match' => 'promo-codes.php'],
    ['url' => $dg_base . '/pages/activity-log.php',          'label' => 'Activité des admins', 'icon' => 'fa-history',        'match' => 'activity-log.php'],
];

$dgName    = $_SESSION['dg_name']      ?? 'DG';
$dgFull    = $_SESSION['dg_full_name'] ?? 'Directeur Général';
$initials  = strtoupper(substr($dgFull, 0, 1));
?>
<aside class="sidebar">
    <!-- Close button visible on mobile only -->
    <button class="sidebar-close" id="sidebar-close-btn" aria-label="Fermer le menu">
        <i class="fas fa-times"></i>
    </button>
    <div class="sidebar-brand">
        <h1>ATLANTECH</h1>
        <p>Direction Générale</p>
        <span class="badge">DG</span>
    </div>

    <div class="sidebar-user">
        <span class="avatar"><?= htmlspecialchars($initials) ?></span>
        <div style="display:inline-block;vertical-align:middle">
            <div class="name"><?= htmlspecialchars($dgFull) ?></div>
            <div class="role">Directeur Général</div>
        </div>
    </div>

    <nav>
        <ul>
            <?php foreach ($nav as $item):
                $matches = explode('|', $item['match']);
                $active  = in_array($current, $matches, true) ? ' class="active"' : '';
            ?>
            <li>
                <a href="<?= htmlspecialchars($item['url']) ?>"<?= $active ?>>
                    <i class="fas <?= $item['icon'] ?>"></i>
                    <?= htmlspecialchars($item['label']) ?>
                </a>
            </li>
            <?php endforeach; ?>
            <li style="margin-top:18px">
                <a href="<?= htmlspecialchars($dg_base) ?>/logout.php" style="color:#fca5a5">
                    <i class="fas fa-sign-out-alt"></i>
                    Déconnexion
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        © <?= date('Y') ?> AtlanTech<br>
        Direction Générale
    </div>
</aside>
