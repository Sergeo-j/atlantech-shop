<?php
$page_title = $page_title ?? 'Marketing Admin';
$mkt_base   = mkt_base_url();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> — AtlanTech Marketing</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- On réutilise le CSS du dg-admin (palette violet/or, même look) -->
    <link rel="stylesheet" href="<?= htmlspecialchars($mkt_base) ?>/../dg-admin/assets/css/style.css">
    <style>
        /* Petite touche distinctive : accent orange/rose pour le marketing */
        :root {
            --dg-accent: #f97316;       /* orange */
            --dg-primary: #ec4899;      /* rose magenta */
            --dg-primary-light: #f472b6;
            --dg-primary-dark: #be185d;
        }
    </style>
</head>
<body>
<div class="layout">
    <?php include __DIR__ . '/sidebar.php'; ?>
    <main class="main-content">
        <div class="container">
