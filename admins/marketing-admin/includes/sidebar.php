<?php
$mkt_base = mkt_base_url();
$current  = basename($_SERVER['SCRIPT_NAME']);

$nav = [
    ['url' => $mkt_base . '/index.php',                'label' => 'Tableau de bord', 'icon' => 'fa-chart-line', 'match' => 'index.php'],
    ['url' => $mkt_base . '/pages/promo-codes.php',    'label' => 'Codes promo',     'icon' => 'fa-tags',       'match' => 'promo-codes.php'],
];

$mktFull = $_SESSION['mkt_full_name'] ?? 'Marketing';
$initial = strtoupper(substr($mktFull, 0, 1));
?>
<aside class="sidebar">
    <div class="sidebar-brand">
        <h1>ATLANTECH</h1>
        <p>Marketing</p>
        <span class="badge">MKT</span>
    </div>

    <div class="sidebar-user">
        <span class="avatar"><?= htmlspecialchars($initial) ?></span>
        <div style="display:inline-block;vertical-align:middle">
            <div class="name"><?= htmlspecialchars($mktFull) ?></div>
            <div class="role">Marketing &amp; Promotions</div>
        </div>
    </div>

    <nav>
        <ul>
            <?php foreach ($nav as $item):
                $active = ($current === $item['match']) ? ' class="active"' : '';
            ?>
            <li>
                <a href="<?= htmlspecialchars($item['url']) ?>"<?= $active ?>>
                    <i class="fas <?= $item['icon'] ?>"></i>
                    <?= htmlspecialchars($item['label']) ?>
                </a>
            </li>
            <?php endforeach; ?>
            <li style="margin-top:18px">
                <a href="<?= htmlspecialchars($mkt_base) ?>/logout.php" style="color:#fca5a5">
                    <i class="fas fa-sign-out-alt"></i> Déconnexion
                </a>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        © <?= date('Y') ?> AtlanTech<br>Marketing
    </div>
</aside>
