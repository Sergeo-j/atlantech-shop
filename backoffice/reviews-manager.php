<?php
// Définir les variables pour le header
$page_title = "Gestion des Avis";
$page_icon = "fa-star";

require_once 'config.php';
require_once __DIR__ . '/includes/csrf.php';

// ── Recalcul de la note moyenne d'un produit ────────────────
function recalc_rating(PDO $pdo, int $product_id): void {
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

// ── Actions POST : approuver / masquer / supprimer ──────────
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action    = $_POST['action']    ?? '';
    $review_id = (int)($_POST['review_id'] ?? 0);

    if ($review_id > 0) {
        $stmt = $pdo->prepare("SELECT product_id FROM reviews WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $review_id]);
        $rv = $stmt->fetch();

        if ($rv) {
            $pid = (int)$rv['product_id'];
            if ($action === 'approve') {
                $pdo->prepare("UPDATE reviews SET status = 'approved', is_approved = 1 WHERE id = :id")
                    ->execute([':id' => $review_id]);
                recalc_rating($pdo, $pid);
                $message = "Avis #$review_id approuvé.";
            } elseif ($action === 'reject') {
                $pdo->prepare("UPDATE reviews SET status = 'rejected', is_approved = 0 WHERE id = :id")
                    ->execute([':id' => $review_id]);
                recalc_rating($pdo, $pid);
                $message = "Avis #$review_id masqué.";
            } elseif ($action === 'delete') {
                $pdo->prepare("DELETE FROM reviews WHERE id = :id")->execute([':id' => $review_id]);
                recalc_rating($pdo, $pid);
                $message = "Avis #$review_id supprimé définitivement.";
            } else {
                $message = "Action inconnue."; $message_type = 'error';
            }
        } else {
            $message = "Avis introuvable."; $message_type = 'error';
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
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 15;
$offset   = ($page - 1) * $per_page;

$where  = [];
$params = [];

if (!empty($_GET['status'])) {
    $where[] = "r.status = :status";
    $params[':status'] = $_GET['status'];
}
if (!empty($_GET['search'])) {
    $where[] = "(p.name LIKE :search OR u.name LIKE :search2 OR r.comment LIKE :search3)";
    $s = '%' . $_GET['search'] . '%';
    $params[':search'] = $s; $params[':search2'] = $s; $params[':search3'] = $s;
}
if (!empty($_GET['rating'])) {
    $where[] = "r.rating = :rating";
    $params[':rating'] = (int)$_GET['rating'];
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

function status_badge(string $s): string {
    return match($s) {
        'approved' => '<span style="background:#d4edda;color:#155724;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Publié</span>',
        'pending'  => '<span style="background:#fff3cd;color:#856404;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">En attente</span>',
        default    => '<span style="background:#f8d7da;color:#721c24;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;">Masqué</span>',
    };
}

function stars_html(int $n): string {
    $out = '';
    for ($i = 1; $i <= 5; $i++) {
        $out .= '<i class="fa' . ($i <= $n ? 's' : 'r') . ' fa-star" style="color:' . ($i <= $n ? '#f5a623' : '#ddd') . ';font-size:12px;"></i>';
    }
    return $out;
}
?>
<?php
include 'includes/admin-header.php';
include 'includes/admin-sidebar.php';
?>

    <div class="admin-container" style="max-width: 100%; padding: 0;">

        <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
            <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon primary"><i class="fas fa-comments"></i></div>
                <div class="stat-content">
                    <h3><?php echo number_format((int)$stats['total']); ?></h3>
                    <p>Total Avis</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon success"><i class="fas fa-check-circle"></i></div>
                <div class="stat-content">
                    <h3><?php echo number_format((int)$stats['approved']); ?></h3>
                    <p>Publiés</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon warning"><i class="fas fa-clock"></i></div>
                <div class="stat-content">
                    <h3><?php echo number_format((int)$stats['pending']); ?></h3>
                    <p>En attente</p>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon danger"><i class="fas fa-star"></i></div>
                <div class="stat-content">
                    <h3><?php echo number_format((float)$stats['avg_rating'], 2); ?></h3>
                    <p>Note Moyenne Globale</p>
                </div>
            </div>
        </div>

        <!-- Filtres -->
        <div class="filters-section" style="background: var(--white); padding: 1.25rem; border-radius: var(--radius-lg); margin-bottom: 1.5rem; box-shadow: var(--shadow-md);">
            <form method="GET" action="" style="display:flex; gap:10px; flex-wrap:wrap; align-items:center;">
                <input type="text" name="search" placeholder="Produit, client ou commentaire..."
                       value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>"
                       style="padding:8px 12px; border:1px solid #ddd; border-radius:6px; min-width:220px;">
                <select name="status" style="padding:8px 12px; border:1px solid #ddd; border-radius:6px;">
                    <option value="">Tous les statuts</option>
                    <option value="approved" <?php echo ($_GET['status'] ?? '') === 'approved' ? 'selected' : ''; ?>>Publiés</option>
                    <option value="pending"  <?php echo ($_GET['status'] ?? '') === 'pending'  ? 'selected' : ''; ?>>En attente</option>
                    <option value="rejected" <?php echo ($_GET['status'] ?? '') === 'rejected' ? 'selected' : ''; ?>>Masqués</option>
                </select>
                <select name="rating" style="padding:8px 12px; border:1px solid #ddd; border-radius:6px;">
                    <option value="">Toutes les notes</option>
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?php echo $i; ?>" <?php echo ($_GET['rating'] ?? '') == $i ? 'selected' : ''; ?>><?php echo $i; ?> étoile<?php echo $i > 1 ? 's' : ''; ?></option>
                    <?php endfor; ?>
                </select>
                <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filtrer</button>
                <a href="reviews-manager.php" class="btn btn-secondary">Réinitialiser</a>
            </form>
        </div>

        <!-- Liste des avis -->
        <div style="background: var(--white); border-radius: var(--radius-lg); box-shadow: var(--shadow-md); overflow-x:auto;">
            <table style="width:100%; border-collapse:collapse; font-size:14px;">
                <thead>
                    <tr style="background:#f8f9fa; text-align:left;">
                        <th style="padding:12px;">Date</th>
                        <th style="padding:12px;">Produit</th>
                        <th style="padding:12px;">Client</th>
                        <th style="padding:12px;">Note</th>
                        <th style="padding:12px; min-width:240px;">Commentaire</th>
                        <th style="padding:12px;">Statut</th>
                        <th style="padding:12px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if (empty($reviews)): ?>
                    <tr><td colspan="7" style="padding:30px; text-align:center; color:#999;">Aucun avis trouvé.</td></tr>
                <?php else: foreach ($reviews as $r): ?>
                    <tr style="border-top:1px solid #eee;">
                        <td style="padding:12px; white-space:nowrap; color:#666;">
                            <?php echo date('d/m/Y H:i', strtotime($r['created_at'])); ?>
                        </td>
                        <td style="padding:12px;">
                            <a href="../shop-single.php?id=<?php echo (int)$r['product_id']; ?>" target="_blank" style="color:#0066c0; text-decoration:none;">
                                <?php echo htmlspecialchars(mb_substr($r['product_name'] ?? '(supprimé)', 0, 40)); ?>
                            </a>
                        </td>
                        <td style="padding:12px;">
                            <?php echo htmlspecialchars($r['user_name'] ?? 'Anonyme'); ?><br>
                            <small style="color:#999;"><?php echo htmlspecialchars($r['user_email'] ?? ''); ?></small>
                        </td>
                        <td style="padding:12px; white-space:nowrap;"><?php echo stars_html((int)$r['rating']); ?></td>
                        <td style="padding:12px; color:#444;">
                            <?php
                            $c = (string)$r['comment'];
                            echo nl2br(htmlspecialchars(mb_substr($c, 0, 180)));
                            if (mb_strlen($c) > 180) echo '…';
                            ?>
                        </td>
                        <td style="padding:12px;"><?php echo status_badge((string)$r['status']); ?></td>
                        <td style="padding:12px; white-space:nowrap;">
                            <?php if ($r['status'] !== 'approved'): ?>
                            <form method="POST" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="approve">
                                <input type="hidden" name="review_id" value="<?php echo (int)$r['id']; ?>">
                                <button type="submit" class="btn btn-success" style="padding:5px 10px; font-size:12px;" title="Publier">
                                    <i class="fas fa-check"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <?php if ($r['status'] === 'approved'): ?>
                            <form method="POST" style="display:inline;">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="reject">
                                <input type="hidden" name="review_id" value="<?php echo (int)$r['id']; ?>">
                                <button type="submit" class="btn btn-secondary" style="padding:5px 10px; font-size:12px;" title="Masquer">
                                    <i class="fas fa-eye-slash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Supprimer définitivement cet avis ?');">
                                <?php echo csrf_field(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="review_id" value="<?php echo (int)$r['id']; ?>">
                                <button type="submit" class="btn btn-danger" style="padding:5px 10px; font-size:12px;" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div style="display:flex; gap:6px; justify-content:center; margin:1.5rem 0;">
            <?php
            $qs = $_GET;
            for ($i = 1; $i <= $total_pages; $i++):
                $qs['page'] = $i;
                $url = 'reviews-manager.php?' . http_build_query($qs);
            ?>
            <a href="<?php echo htmlspecialchars($url); ?>"
               style="padding:7px 13px; border-radius:6px; text-decoration:none; <?php echo $i === $page ? 'background:#f97316; color:#fff;' : 'background:#f0f0f0; color:#333;'; ?>">
                <?php echo $i; ?>
            </a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>

    </div>

<?php include 'includes/admin-footer.php'; ?>
