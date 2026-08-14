<?php
/**
 * Gestion des Avis Produits
 * Atlantech Shop - Client Admin Dashboard
 */

require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

// Vérifier l'authentification
check_auth();

// ── Recalcul de la note moyenne d'un produit ────────────────
function recalc_product_rating(PDO $pdo, int $product_id): void {
    $stmt = $pdo->prepare("
        UPDATE products
        SET rating = (
            SELECT COALESCE(ROUND(AVG(rating), 2), 0)
            FROM reviews
            WHERE product_id = :pid1 AND status = 'approved'
        )
        WHERE id = :pid2
    ");
    $stmt->execute([':pid1' => $product_id, ':pid2' => $product_id]);
}

// ── Actions POST : publier / masquer / supprimer ────────────
$success_message = '';
$error_message   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['token'] ?? '')) {
        $error_message = 'Jeton de sécurité invalide. Réessayez.';
    } else {
        $action    = $_POST['action']    ?? '';
        $review_id = (int)($_POST['review_id'] ?? 0);

        $stmt = $pdo->prepare("SELECT product_id FROM reviews WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $review_id]);
        $rv = $stmt->fetch();

        if (!$rv) {
            $error_message = 'Avis introuvable.';
        } else {
            $pid = (int)$rv['product_id'];
            if ($action === 'approve') {
                $pdo->prepare("UPDATE reviews SET status = 'approved', is_approved = 1 WHERE id = :id")->execute([':id' => $review_id]);
                recalc_product_rating($pdo, $pid);
                log_admin_action('REVIEW_APPROVE', "Avis #$review_id publié (produit #$pid)");
                $success_message = "Avis #$review_id publié.";
            } elseif ($action === 'reject') {
                $pdo->prepare("UPDATE reviews SET status = 'rejected', is_approved = 0 WHERE id = :id")->execute([':id' => $review_id]);
                recalc_product_rating($pdo, $pid);
                log_admin_action('REVIEW_REJECT', "Avis #$review_id masqué (produit #$pid)");
                $success_message = "Avis #$review_id masqué.";
            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM reviews WHERE id = :id")->execute([':id' => $review_id]);
                recalc_product_rating($pdo, $pid);
                log_admin_action('REVIEW_DELETE', "Avis #$review_id supprimé (produit #$pid)");
                $success_message = "Avis #$review_id supprimé définitivement.";
            } else {
                $error_message = 'Action inconnue.';
            }
        }
    }
}

// ── Statistiques ────────────────────────────────────────────
$stats = $pdo->query("SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved,
    SUM(CASE WHEN status = 'pending'  THEN 1 ELSE 0 END) AS pending,
    SUM(CASE WHEN status IN ('rejected','flagged','spam') THEN 1 ELSE 0 END) AS hidden,
    COALESCE(ROUND(AVG(CASE WHEN status = 'approved' THEN rating END), 2), 0) AS avg_rating
FROM reviews")->fetch();

// ── Filtres + pagination ────────────────────────────────────
$search        = clean_input($_GET['search'] ?? '');
$status_filter = clean_input($_GET['status'] ?? '');
$rating_filter = (int)($_GET['rating'] ?? 0);
$page          = max(1, intval($_GET['page'] ?? 1));
$per_page      = 15;
$offset        = ($page - 1) * $per_page;

$where  = [];
$params = [];

if ($status_filter !== '') {
    $where[] = "r.status = :status";
    $params[':status'] = $status_filter;
}
if ($search !== '') {
    $where[] = "(p.name LIKE :s1 OR u.name LIKE :s2 OR r.comment LIKE :s3)";
    $s = "%$search%";
    $params[':s1'] = $s; $params[':s2'] = $s; $params[':s3'] = $s;
}
if ($rating_filter >= 1 && $rating_filter <= 5) {
    $where[] = "r.rating = :rating";
    $params[':rating'] = $rating_filter;
}
$where_sql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count_stmt = $pdo->prepare("
    SELECT COUNT(*) FROM reviews r
    LEFT JOIN products p ON r.product_id = p.id
    LEFT JOIN users u ON r.user_id = u.id
    $where_sql");
$count_stmt->execute($params);
$total_reviews = (int)$count_stmt->fetchColumn();
$total_pages   = max(1, (int)ceil($total_reviews / $per_page));

$list_stmt = $pdo->prepare("
    SELECT r.*, p.name AS product_name, u.name AS user_name, u.email AS user_email
    FROM reviews r
    LEFT JOIN products p ON r.product_id = p.id
    LEFT JOIN users u ON r.user_id = u.id
    $where_sql
    ORDER BY r.created_at DESC
    LIMIT $per_page OFFSET $offset");
$list_stmt->execute($params);
$reviews = $list_stmt->fetchAll();

$csrf = generate_csrf_token();

function review_status_badge(string $s): string {
    return match($s) {
        'approved' => '<span class="badge badge-success">Publié</span>',
        'pending'  => '<span class="badge badge-warning">En attente</span>',
        default    => '<span class="badge badge-danger">Masqué</span>',
    };
}

function review_stars(int $n): string {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<i class="fa' . ($i <= $n ? 's' : 'r') . ' fa-star" style="color:' . ($i <= $n ? '#f5a623' : '#ccc') . ';font-size:12px;"></i>';
    }
    return $out;
}

// Variables pour le header
$page_title = 'Avis Produits';
$current_page_menu = 'reviews';
$current_page = 'reviews';

// Inclure le header
include __DIR__ . '/../includes/header.php';
?>

<?php if ($success_message): ?>
    <div class="alert alert-success">
        <i class="fas fa-check-circle"></i>
        <?php echo htmlspecialchars($success_message); ?>
    </div>
<?php endif; ?>
<?php if ($error_message): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-circle"></i>
        <?php echo htmlspecialchars($error_message); ?>
    </div>
<?php endif; ?>

<!-- Statistiques -->
<div class="card">
    <div style="display:flex; gap:24px; flex-wrap:wrap; padding:6px 4px;">
        <div><strong style="font-size:20px;"><?php echo (int)$stats['total']; ?></strong><br><span style="color:#888;font-size:13px;">Total avis</span></div>
        <div><strong style="font-size:20px;color:#28a745;"><?php echo (int)$stats['approved']; ?></strong><br><span style="color:#888;font-size:13px;">Publiés</span></div>
        <div><strong style="font-size:20px;color:#ffc107;"><?php echo (int)$stats['pending']; ?></strong><br><span style="color:#888;font-size:13px;">En attente</span></div>
        <div><strong style="font-size:20px;color:#dc3545;"><?php echo (int)$stats['hidden']; ?></strong><br><span style="color:#888;font-size:13px;">Masqués</span></div>
        <div><strong style="font-size:20px;color:#f5a623;"><?php echo number_format((float)$stats['avg_rating'], 2); ?> ★</strong><br><span style="color:#888;font-size:13px;">Note moyenne</span></div>
    </div>
</div>

<!-- Barre de recherche et filtres -->
<div class="card">
    <form method="GET" action="" class="search-filters">
        <div class="search-box">
            <input
                type="text"
                name="search"
                placeholder="Rechercher (produit, client, commentaire)..."
                value="<?php echo htmlspecialchars($search); ?>"
            >
            <i class="fas fa-search"></i>
        </div>

        <select name="status" class="filter-select">
            <option value="">Tous les statuts</option>
            <option value="approved" <?php echo $status_filter === 'approved' ? 'selected' : ''; ?>>Publiés</option>
            <option value="pending"  <?php echo $status_filter === 'pending'  ? 'selected' : ''; ?>>En attente</option>
            <option value="rejected" <?php echo $status_filter === 'rejected' ? 'selected' : ''; ?>>Masqués</option>
        </select>

        <select name="rating" class="filter-select">
            <option value="">Toutes les notes</option>
            <?php for ($i = 5; $i >= 1; $i--): ?>
            <option value="<?php echo $i; ?>" <?php echo $rating_filter === $i ? 'selected' : ''; ?>><?php echo $i; ?> étoile<?php echo $i > 1 ? 's' : ''; ?></option>
            <?php endfor; ?>
        </select>

        <button type="submit" class="btn btn-primary">
            <i class="fas fa-filter"></i>
            Filtrer
        </button>

        <a href="reviews-manager.php" class="btn btn-secondary">
            <i class="fas fa-redo"></i>
            Réinitialiser
        </a>
    </form>
</div>

<!-- Liste des avis -->
<div class="card">
    <div class="card-header">
        <h2 class="card-title">
            <i class="fas fa-star"></i>
            Avis Produits (<?php echo $total_reviews; ?>)
        </h2>
    </div>

    <div class="table-container">
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Produit</th>
                    <th>Client</th>
                    <th>Note</th>
                    <th style="min-width:220px;">Commentaire</th>
                    <th>Statut</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($reviews)): ?>
                <tr><td colspan="7" style="text-align:center; color:#999; padding:30px;">Aucun avis trouvé.</td></tr>
            <?php else: foreach ($reviews as $r): ?>
                <tr>
                    <td style="white-space:nowrap;"><?php echo date('d/m/Y H:i', strtotime($r['created_at'])); ?></td>
                    <td>
                        <a href="../../../shop-single.php?id=<?php echo (int)$r['product_id']; ?>" target="_blank" style="color:#0066c0;">
                            <?php echo htmlspecialchars(mb_substr($r['product_name'] ?? '(supprimé)', 0, 35)); ?>
                        </a>
                    </td>
                    <td>
                        <?php echo htmlspecialchars($r['user_name'] ?? 'Anonyme'); ?><br>
                        <small style="color:#999;"><?php echo htmlspecialchars($r['user_email'] ?? ''); ?></small>
                    </td>
                    <td style="white-space:nowrap;"><?php echo review_stars((int)$r['rating']); ?></td>
                    <td>
                        <?php
                        $c = (string)$r['comment'];
                        echo nl2br(htmlspecialchars(mb_substr($c, 0, 160)));
                        if (mb_strlen($c) > 160) echo '…';
                        ?>
                    </td>
                    <td><?php echo review_status_badge((string)$r['status']); ?></td>
                    <td style="white-space:nowrap;">
                        <?php if ($r['status'] !== 'approved'): ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="action" value="approve">
                            <input type="hidden" name="review_id" value="<?php echo (int)$r['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-primary" title="Publier"><i class="fas fa-check"></i></button>
                        </form>
                        <?php else: ?>
                        <form method="POST" style="display:inline;">
                            <input type="hidden" name="token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="action" value="reject">
                            <input type="hidden" name="review_id" value="<?php echo (int)$r['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-secondary" title="Masquer"><i class="fas fa-eye-slash"></i></button>
                        </form>
                        <?php endif; ?>
                        <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer définitivement cet avis ?');">
                            <input type="hidden" name="token" value="<?php echo $csrf; ?>">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="review_id" value="<?php echo (int)$r['id']; ?>">
                            <button type="submit" class="btn btn-sm btn-danger" title="Supprimer"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
            </tbody>
        </table>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
    <div class="pagination" style="display:flex; gap:6px; justify-content:center; padding:16px 0;">
        <?php
        $qs = $_GET;
        for ($i = 1; $i <= $total_pages; $i++):
            $qs['page'] = $i;
            $url = 'reviews-manager.php?' . http_build_query($qs);
        ?>
        <a href="<?php echo htmlspecialchars($url); ?>"
           style="padding:6px 12px; border-radius:6px; text-decoration:none; <?php echo $i === $page ? 'background:#f97316;color:#fff;' : 'background:#f0f0f0;color:#333;'; ?>">
            <?php echo $i; ?>
        </a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
