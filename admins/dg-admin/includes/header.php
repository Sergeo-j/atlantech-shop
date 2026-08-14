<?php
// Doit être inclus APRÈS require_dg_auth() pour avoir la session DG active.
$page_title = $page_title ?? 'DG Admin';
$dg_base    = dg_base_url();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — AtlanTech DG</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($dg_base) ?>/assets/css/style.css">
</head>
<body>

<!-- Mobile top bar (hamburger) — hidden on desktop -->
<div class="mobile-header">
    <span class="mobile-logo"><i class="fas fa-chart-line" style="margin-right:6px"></i>ATLANTECH DG</span>
    <button class="hamburger" id="hamburger-btn" aria-label="Ouvrir le menu">
        <i class="fas fa-bars"></i>
    </button>
</div>

<!-- Overlay for mobile sidebar -->
<div class="sidebar-overlay" id="sidebar-overlay"></div>

<div class="layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
        <div class="container">
