# Modules de Gestion - Atlantech Shop
## Installation et Intégration

### 📁 Structure des fichiers créés

```
atlantech_admin_modules/
├── manage_admins.php          # Gestion des administrateurs
├── manage_users.php           # Gestion des clients/utilisateurs
├── ajax/
│   ├── create_admin.php       # Créer un admin
│   ├── get_admin.php          # Récupérer détails admin
│   ├── update_admin.php       # Modifier un admin
│   ├── toggle_admin_status.php # Activer/Désactiver admin
│   └── toggle_user_block.php  # Bloquer/Débloquer utilisateur
└── README.md                  # Ce fichier
```

---

## 🚀 Installation

### Étape 1: Copier les fichiers dans votre projet

1. **Copier manage_admins.php et manage_users.php** dans le dossier `/admin/` de votre projet
2. **Copier le dossier ajax/** dans `/admin/ajax/`

```
Votre_Projet/
├── admin/
│   ├── dashboard.php
│   ├── manage_admins.php     ← NOUVEAU
│   ├── manage_users.php      ← NOUVEAU
│   ├── ajax/
│   │   ├── create_admin.php  ← NOUVEAU
│   │   ├── get_admin.php     ← NOUVEAU
│   │   ├── update_admin.php  ← NOUVEAU
│   │   ├── toggle_admin_status.php ← NOUVEAU
│   │   └── toggle_user_block.php   ← NOUVEAU
│   └── ...
├── includes/
│   ├── config.php
│   ├── auth.php
│   └── functions.php
└── ...
```

---

### Étape 2: Vérifier les fichiers includes

Les modules nécessitent ces fichiers dans `/includes/`:

1. **config.php** - Connexion à la base de données
2. **auth.php** - Fonctions d'authentification
3. **functions.php** - Fonctions utilitaires

#### Fonction requise dans `auth.php`:

```php
function check_superadmin_auth() {
    if (!isset($_SESSION['superadmin_id'])) {
        header('Location: ../login.php');
        exit;
    }
}
```

#### Fonctions requises dans `functions.php`:

```php
function get_system_stats() {
    global $pdo;
    
    // Stats admins
    $admin_stats = $pdo->query("
        SELECT 
            COUNT(*) as total_admins,
            SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_admins,
            SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_admins
        FROM admins
    ")->fetch(PDO::FETCH_ASSOC);
    
    // Stats users
    $user_stats = $pdo->query("SELECT COUNT(*) as total_users FROM users")->fetch(PDO::FETCH_ASSOC);
    
    // Répartition par rôle
    $roles_stats = $pdo->query("
        SELECT ar.role_name, COUNT(a.id) as count
        FROM admin_roles ar
        LEFT JOIN admins a ON ar.id = a.admin_role_id
        GROUP BY ar.id, ar.role_name
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'total_admins' => $admin_stats['total_admins'],
        'active_admins' => $admin_stats['active_admins'],
        'inactive_admins' => $admin_stats['inactive_admins'],
        'total_users' => $user_stats['total_users'],
        'admins_by_role' => $roles_stats
    ];
}

function get_recent_logs($limit = 10) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            sal.*, 
            sa.full_name as user_name
        FROM superadmin_activity_logs sal
        LEFT JOIN superadmins sa ON sal.superadmin_id = sa.id
        ORDER BY sal.created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
```

---

## 🎨 Fonctionnalités

### Module Gestion des Admins (manage_admins.php)

✅ **Liste complète** des administrateurs
✅ **Filtres avancés**: recherche, rôle, statut
✅ **Statistiques**: total, actifs, inactifs, connexions récentes
✅ **Actions CRUD**:
   - ➕ Créer un nouvel admin
   - ✏️ Modifier un admin existant
   - 🔄 Activer/Désactiver
   - 👁️ Voir détails
✅ **Sécurité**: Hash Argon2id pour les mots de passe
✅ **Logs**: Toutes les actions sont enregistrées

### Module Gestion des Users (manage_users.php)

✅ **Liste complète** des clients/utilisateurs
✅ **Filtres avancés**: recherche, tier, statut, bloqué
✅ **Statistiques**: total, actifs, bloqués, vérifiés, nouveaux
✅ **Affichage enrichi**:
   - Nombre de commandes
   - Chiffre d'affaires total
   - Points de fidélité
   - Tier du compte (Bronze/Silver/Gold/Platinum)
✅ **Actions**:
   - 👁️ Voir profil détaillé
   - ✏️ Modifier utilisateur
   - 🚫 Bloquer/Débloquer
   - 📥 Exporter CSV
✅ **Logs**: Toutes les actions sont enregistrées

---

## 🔒 Sécurité Implémentée

1. ✅ **Authentification**: Vérification Super Admin obligatoire
2. ✅ **Hash sécurisé**: Argon2id avec fallback bcrypt
3. ✅ **Validation**: Tous les inputs sont validés
4. ✅ **Protection SQL**: PDO avec prepared statements
5. ✅ **Logs d'audit**: Toutes les actions critiques sont loggées
6. ✅ **Protection CSRF**: À implémenter avec tokens (recommandé)

---

## 🎯 Prochaines Étapes Recommandées

### À Court Terme:
1. ✅ Tester les modules sur votre environnement local
2. ⚠️ Ajouter la protection CSRF
3. 📧 Implémenter l'envoi d'email aux admins créés
4. 🔑 Ajouter la gestion des permissions granulaires

### Modules Complémentaires à Créer:
1. **manage_products.php** - Gestion des produits
2. **manage_orders.php** - Gestion des commandes
3. **manage_categories.php** - Gestion des catégories
4. **settings.php** - Paramètres système
5. **view_admin.php** - Page détails admin
6. **view_user.php** - Page profil utilisateur
7. **edit_user.php** - Édition utilisateur

---

## 🐛 Troubleshooting

### Erreur "function not found"
➡️ Vérifier que les fonctions requises sont dans `functions.php`

### Erreur de connexion base de données
➡️ Vérifier les credentials dans `config.php`

### Session non trouvée
➡️ Vérifier que `session_start()` est appelé dans `config.php`

### Chemins de fichiers incorrects
➡️ Ajuster les `require_once` selon votre structure

---

## 📝 Notes Importantes

- Les fichiers utilisent le style cyberpunk violet/gold de votre dashboard
- Tous les textes sont en français
- Le design est responsive
- Les modals utilisent du JavaScript vanilla (pas de dépendances)
- Compatible avec votre structure de BDD existante

---

## 💡 Personnalisation

Pour personnaliser les couleurs:
- Violet principal: `#a855f7`
- Or/Gold: `#ffd700`
- Fond sombre: `#020817`
- Cartes: `rgba(17, 34, 64, 0.6)`

---

## ✨ Améliorations Futures Suggérées

1. **Pagination** pour les grandes listes
2. **Export Excel** en plus du CSV
3. **Notifications push** pour les actions importantes
4. **Historique détaillé** par utilisateur/admin
5. **Dashboard analytique** avec graphiques avancés
6. **Système de rôles** encore plus granulaire
7. **2FA** pour les super admins

---

**Développé pour Atlantech Shop**
Style: Cyberpunk Violet/Gold
Version: 1.0
