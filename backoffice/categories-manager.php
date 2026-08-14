<?php
/**
 * Gestion des Catégories - AtlanTech Admin
 */

require_once '../config/config.php';

// Vérifier si l'utilisateur est admin
if (!isLoggedIn() || $_SESSION['user_role'] !== 'admin') {
    header('Location: ../index.php');
    exit();
}

// Traitement des actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        if ($action === 'add') {
            // Ajouter une catégorie
            $stmt = $pdo->prepare("
                INSERT INTO categories (name, description, parent_id, slug, icon, is_active) 
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $_POST['parent_id'] ?: null,
                $_POST['slug'],
                $_POST['icon'],
                $_POST['is_active'] ?? 1
            ]);
            $success = "Catégorie ajoutée avec succès";
            
        } elseif ($action === 'edit') {
            // Modifier une catégorie
            $stmt = $pdo->prepare("
                UPDATE categories 
                SET name = ?, description = ?, parent_id = ?, slug = ?, icon = ?, is_active = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $_POST['parent_id'] ?: null,
                $_POST['slug'],
                $_POST['icon'],
                $_POST['is_active'] ?? 1,
                $_POST['id']
            ]);
            $success = "Catégorie modifiée avec succès";
            
        } elseif ($action === 'delete') {
            // Supprimer une catégorie
            $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            $success = "Catégorie supprimée avec succès";
        }
    } catch (PDOException $e) {
        $error = "Erreur : " . $e->getMessage();
    }
}

// Récupérer toutes les catégories
$stmt = $pdo->query("
    SELECT c.*, p.name as parent_name,
           (SELECT COUNT(*) FROM products WHERE category_id = c.id) as product_count
    FROM categories c
    LEFT JOIN categories p ON c.parent_id = p.id
    ORDER BY c.parent_id, c.name
");
$categories = $stmt->fetchAll();

// Récupérer les catégories parentes pour le dropdown
$stmt = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name");
$parent_categories = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Catégories - AtlanTech Admin</title>
    <link rel="stylesheet" href="../assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/fontawesome.css">
    <style>
        body {
            background: #f5f5f5;
            font-family: Arial, sans-serif;
        }
        .admin-container {
            max-width: 1400px;
            margin: 30px auto;
            padding: 20px;
        }
        .admin-header {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .category-table {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .category-parent {
            background: #f8f9fa;
            font-weight: bold;
        }
        .category-child {
            padding-left: 30px;
        }
        .btn-action {
            padding: 5px 10px;
            margin: 0 2px;
            font-size: 12px;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: white;
            padding: 30px;
            border-radius: 8px;
            max-width: 600px;
            width: 90%;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .alert {
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div style="display: flex; justify-content: space-between; align-items: center;">
                <div>
                    <h1 style="margin: 0;">Gestion des Catégories</h1>
                    <p style="margin: 5px 0 0 0; color: #666;">
                        Total: <?php echo count($categories); ?> catégories
                    </p>
                </div>
                <div>
                    <button class="btn btn-primary" onclick="openModal('add')">
                        <i class="fas fa-plus"></i> Nouvelle Catégorie
                    </button>
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Retour
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <div class="category-table">
            <table class="table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nom</th>
                        <th>Slug</th>
                        <th>Parent</th>
                        <th>Produits</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                        <tr class="<?php echo $cat['parent_id'] ? 'category-child' : 'category-parent'; ?>">
                            <td><?php echo $cat['id']; ?></td>
                            <td>
                                <?php if ($cat['parent_id']): ?>
                                    <i class="fas fa-level-up-alt fa-rotate-90"></i>
                                <?php endif; ?>
                                <?php echo htmlspecialchars($cat['name']); ?>
                            </td>
                            <td><code><?php echo htmlspecialchars($cat['slug']); ?></code></td>
                            <td><?php echo $cat['parent_name'] ? htmlspecialchars($cat['parent_name']) : '-'; ?></td>
                            <td>
                                <span class="badge badge-info"><?php echo $cat['product_count']; ?></span>
                            </td>
                            <td>
                                <?php if ($cat['is_active']): ?>
                                    <span class="badge badge-success">Actif</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">Inactif</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning btn-action" 
                                        onclick='editCategory(<?php echo json_encode($cat); ?>)'>
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-danger btn-action" 
                                        onclick="deleteCategory(<?php echo $cat['id']; ?>, '<?php echo htmlspecialchars($cat['name']); ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Modal Ajouter/Modifier -->
    <div id="categoryModal" class="modal">
        <div class="modal-content">
            <h3 id="modalTitle">Nouvelle Catégorie</h3>
            <form method="POST">
                <input type="hidden" name="action" id="formAction" value="add">
                <input type="hidden" name="id" id="categoryId">

                <div class="form-group">
                    <label for="name">Nom *</label>
                    <input type="text" class="form-control" name="name" id="name" required>
                </div>

                <div class="form-group">
                    <label for="slug">Slug *</label>
                    <input type="text" class="form-control" name="slug" id="slug" required>
                    <small>Ex: ordinateurs-laptops (sans espaces, avec tirets)</small>
                </div>

                <div class="form-group">
                    <label for="description">Description</label>
                    <textarea class="form-control" name="description" id="description" rows="3"></textarea>
                </div>

                <div class="form-group">
                    <label for="parent_id">Catégorie Parent</label>
                    <select class="form-control" name="parent_id" id="parent_id">
                        <option value="">-- Aucune (Catégorie principale) --</option>
                        <?php foreach ($parent_categories as $parent): ?>
                            <option value="<?php echo $parent['id']; ?>">
                                <?php echo htmlspecialchars($parent['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label for="icon">Icône</label>
                    <input type="text" class="form-control" name="icon" id="icon" placeholder="hc_01.svg">
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="is_active" value="1" checked>
                        Actif
                    </label>
                </div>

                <div style="margin-top: 20px; display: flex; gap: 10px;">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Enregistrer
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        Annuler
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Form Delete -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" id="deleteId">
    </form>

    <script>
        // Auto-generate slug from name
        document.getElementById('name').addEventListener('input', function() {
            const slug = this.value
                .toLowerCase()
                .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
                .replace(/[^a-z0-9]+/g, '-')
                .replace(/^-+|-+$/g, '');
            document.getElementById('slug').value = slug;
        });

        function openModal(action) {
            document.getElementById('categoryModal').classList.add('show');
            document.getElementById('formAction').value = action;
            
            if (action === 'add') {
                document.getElementById('modalTitle').textContent = 'Nouvelle Catégorie';
                document.getElementById('categoryForm').reset();
            }
        }

        function closeModal() {
            document.getElementById('categoryModal').classList.remove('show');
        }

        function editCategory(category) {
            openModal('edit');
            document.getElementById('modalTitle').textContent = 'Modifier Catégorie';
            document.getElementById('categoryId').value = category.id;
            document.getElementById('name').value = category.name;
            document.getElementById('slug').value = category.slug;
            document.getElementById('description').value = category.description || '';
            document.getElementById('parent_id').value = category.parent_id || '';
            document.getElementById('icon').value = category.icon || '';
            document.getElementById('is_active').checked = category.is_active == 1;
        }

        function deleteCategory(id, name) {
            if (confirm(`Êtes-vous sûr de vouloir supprimer la catégorie "${name}" ?`)) {
                document.getElementById('deleteId').value = id;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modal on outside click
        document.getElementById('categoryModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
    </script>
</body>
</html>