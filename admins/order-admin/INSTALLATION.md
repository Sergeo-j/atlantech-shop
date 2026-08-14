# 📥 GUIDE D'INSTALLATION - ORDER-ADMIN

## Structure actuelle de votre projet
```
atlantech-shop/
└── admins/
    ├── superadmin/
    ├── client-admin/
    ├── product-admin/
    └── order-admin/     ← À ajouter ici
```

## 🚀 Installation rapide

### Étape 1 : Copier le dossier
Copiez le dossier **order-admin** dans :
```
C:\wamp64\www\atlantech-shop\admins\order-admin\
```

### Étape 2 : Vérifier la structure
Après copie, vous devriez avoir :
```
C:\wamp64\www\atlantech-shop\admins\order-admin\
├── assets/
│   └── css/
│       └── style.css
├── includes/
│   ├── auth_check.php
│   ├── db.php
│   └── functions.php
├── actions/
│   └── login_process.php
├── pages/              ← À créer (vide pour l'instant)
├── setup/
│   ├── create_order_admin.php
│   └── create_order_admin.sql
├── index.php
├── login.php
├── logout.php
└── README.md
```

### Étape 3 : Créer un compte Order Admin

**Option A - Via ligne de commande :**
```bash
cd C:\wamp64\www\atlantech-shop\admins\order-admin\setup
php create_order_admin.php
```

**Option B - Via PhpMyAdmin :**
1. Ouvrir PhpMyAdmin
2. Sélectionner la base de données `atldb`
3. Aller dans l'onglet SQL
4. Coller ce code :
```sql
INSERT INTO admins (name, email, password, role, is_active, created_at)
VALUES (
    'Order Manager',
    'order@atlantech.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'order_admin',
    1,
    NOW()
);
```
5. Exécuter la requête

**Option C - Via le script SQL :**
Dans PhpMyAdmin, importer le fichier :
```
admins/order-admin/setup/create_order_admin.sql
```

### Étape 4 : Tester la connexion

1. Ouvrir votre navigateur
2. Aller à : `http://localhost/atlantech-shop/admins/order-admin/login.php`
3. Se connecter avec :
   - **Email** : order@atlantech.com
   - **Mot de passe** : Order2024!

## ✅ Vérification

### Dashboard doit afficher :
- ✅ Statistiques des commandes (6 cartes)
- ✅ Graphique en donut (statuts)
- ✅ Graphique linéaire (7 derniers jours)
- ✅ Tableau des 10 dernières commandes
- ✅ Navbar avec navigation

### En cas de problème :

**Erreur : "Erreur de connexion à la base de données"**
→ Vérifier `includes/db.php` :
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'atldb');
define('DB_USER', 'root');
define('DB_PASS', '');
```

**Erreur : "Email ou mot de passe incorrect"**
→ Vérifier que le compte existe :
```sql
SELECT * FROM admins WHERE role = 'order_admin';
```

**Erreur : Page blanche**
→ Activer l'affichage des erreurs PHP :
```php
error_reporting(E_ALL);
ini_set('display_errors', 1);
```

## 🎯 Structure identique aux autres modules

Le module **order-admin** suit exactement la même structure que :
- `client-admin/`
- `product-admin/`

**Organisation :**
```
order-admin/
├── assets/         → CSS et ressources
├── includes/       → Fichiers communs (auth, db, functions)
├── actions/        → Traitements backend (AJAX, formulaires)
├── pages/          → Pages secondaires (à venir)
├── setup/          → Scripts d'installation
├── index.php       → Dashboard principal
├── login.php       → Page de connexion
└── logout.php      → Déconnexion
```

## 📋 Prochaines pages à créer

Une fois le login fonctionnel, on créera dans `pages/` :
1. `orders.php` - Liste des commandes
2. `order-details.php` - Détails d'une commande
3. `preparing.php` - Interface de préparation
4. `returns.php` - Gestion des retours

## 🔐 Sécurité

✅ **Déjà implémenté :**
- Argon2id password hashing
- Anti-bruteforce (5 tentatives / 15 min)
- CSRF protection
- Session validation
- Activity logging
- Prepared statements (PDO)

## 💡 Conseils

1. **Changez le mot de passe par défaut** après la première connexion
2. **Sauvegardez** la base de données régulièrement
3. **Surveillez** les logs dans la table `admin_logs`
4. **Testez** sur un environnement de développement d'abord

## 📞 Support

En cas de problème, vérifier :
1. Les logs Apache/PHP
2. La console développeur du navigateur (F12)
3. Les logs dans `admin_logs`

---

**Version** : 1.0.0  
**Date** : Décembre 2024  
**Développeur** : Serge - Atlantech Shop
