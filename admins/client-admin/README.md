# 🚀 Atlantech Shop - Client Admin Dashboard

## 📋 Description

Dashboard d'administration futuriste et sécurisé pour la gestion des clients d'Atlantech Shop. Design cyberpunk avec des effets néon, des animations fluides et une interface ultra-moderne inspirée de Tesla et du style cyberpunk.

## ✨ Fonctionnalités

### 🔐 Sécurité
- ✅ Système d'authentification sécurisé avec PHP sessions
- ✅ Protection CSRF avec tokens
- ✅ **Hashage des mots de passe avec Argon2id** (ou BCrypt en fallback)
- ✅ **Migration automatique** des anciens hash vers Argon2id
- ✅ Protection contre les injections SQL avec PDO prepared statements
- ✅ Vérification des rôles (seul le rôle 'client' peut accéder)
- ✅ Timeout automatique de session
- ✅ Logs de toutes les activités administratives

### 📊 Dashboard Principal
- Statistiques en temps réel (total clients, actifs, inactifs, nouveaux)
- Graphique de croissance des clients sur 6 mois
- Cartes statistiques animées
- Actions rapides (export CSV/PDF, statistiques détaillées)
- Historique des activités récentes

### 👥 Gestion des Clients
- **Liste complète** avec pagination
- **Recherche en temps réel** (nom, email, téléphone)
- **Filtres** par statut (actif/inactif)
- **Actions** : Voir, Modifier, Activer/Désactiver
- **Vue détaillée** : informations complètes, statistiques, historique des commandes
- **Modification** : formulaire complet avec validation

### 🎨 Design
- Style futuriste cyberpunk avec néon bleu/cyan
- Effets glassmorphism sur les cartes
- Animations fluides et transitions élégantes
- Interface responsive (desktop, tablet, mobile)
- Menu latéral avec icônes Font Awesome
- Dark mode natif avec dégradés animés

## 📁 Structure du Projet

```
client-admin/
├── assets/
│   ├── css/
│   │   └── style.css          # Styles futuristes complets
│   ├── js/
│   │   └── app.js             # JavaScript interactif
│   └── img/                   # Images et icônes
├── includes/
│   ├── config.php             # Configuration DB et constantes
│   ├── auth.php               # Système d'authentification Argon2id
│   ├── functions.php          # Fonctions utilitaires
│   ├── header.php             # Header réutilisable
│   ├── sidebar.php            # Menu latéral
│   └── footer.php             # Footer réutilisable
├── pages/
│   ├── dashboard.php          # Tableau de bord principal
│   ├── clients-list.php       # Liste des clients
│   ├── client-view.php        # Voir un client
│   └── client-edit.php        # Modifier un client
├── login.php                  # Page de connexion
├── logout.php                 # Déconnexion
├── generate_hash.php          # 🔐 Générateur de hash Argon2id
├── migrate_to_argon2.php      # 🔄 Script de migration
├── database.sql               # Script SQL
├── ARGON2_GUIDE.md            # 📚 Guide complet Argon2id
└── README.md                  # Ce fichier
```

## 🛠️ Installation

### Prérequis
- PHP 7.2 ou supérieur (pour Argon2id) ou PHP 7.4+ (recommandé)
- MySQL 5.7 ou supérieur (ou MariaDB)
- Serveur web (Apache/Nginx)
- Extension PHP PDO activée

**Note** : Si PHP < 7.2, le système utilisera automatiquement BCrypt comme fallback.

### Étape 1 : Base de données

```sql
-- Créer la base de données
CREATE DATABASE IF NOT EXISTS atldb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Importer le fichier SQL
mysql -u root -p atldb < database.sql
```

Ou via phpMyAdmin :
1. Créez la base de données `atldb`
2. Importez le fichier `database.sql`

### Étape 2 : Configuration

Modifiez `includes/config.php` avec vos paramètres :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'atldb');
define('DB_USER', 'root');
define('DB_PASS', 'votre_mot_de_passe');
define('BASE_URL', 'http://localhost/client-admin/');
```

### Étape 3 : Permissions

```bash
chmod -R 755 client-admin/
chmod -R 777 client-admin/assets/
```

### Étape 4 : Connexion

Accédez à : `http://localhost/client-admin/login.php`

**Identifiants par défaut :**
- Email : `admin.client@atlantech.com`
- Mot de passe : `admin123`

⚠️ **IMPORTANT** : Changez le mot de passe immédiatement après la première connexion !

## 🔑 Créer un Nouvel Admin

### Méthode 1 : Via PHP

```php
<?php
$password = password_hash('nouveau_mot_de_passe', PASSWORD_DEFAULT);
echo $password; // Copiez ce hash
?>
```

### Méthode 2 : Via SQL

```sql
INSERT INTO admins (full_name, name, email, password, phone, role, is_active)
VALUES (
    'Nom Complet',
    'Nom Court',
    'email@example.com',
    '$2y$10$hash_genere_ci_dessus',
    '+509 37 12 34 56',
    'client',
    1
);
```

## 📊 Utilisation

### Dashboard
- Consultez les statistiques en temps réel
- Visualisez la croissance des clients
- Accédez rapidement aux actions principales

### Liste des Clients
1. **Rechercher** : Tapez dans la barre de recherche
2. **Filtrer** : Sélectionnez un statut (actif/inactif)
3. **Actions** :
   - 👁️ **Voir** : Détails complets du client
   - ✏️ **Modifier** : Éditer les informations
   - 🔄 **Toggle** : Activer/désactiver le compte

### Voir un Client
- Informations personnelles complètes
- Statistiques des commandes
- Historique d'achats
- Points de fidélité
- Dernière activité

### Modifier un Client
- Formulaire de modification sécurisé
- Validation côté client et serveur
- Confirmation avant enregistrement
- Logs automatiques des modifications

## 🔒 Sécurité

### Protection Implémentée
✅ Sessions PHP sécurisées avec régénération d'ID
✅ Tokens CSRF sur tous les formulaires
✅ PDO Prepared Statements (anti SQL injection)
✅ Validation et nettoyage des entrées
✅ Protection XSS avec `htmlspecialchars()`
✅ Vérification des rôles à chaque page
✅ Timeout automatique de session (1 heure)
✅ Logs de toutes les actions admin

### Bonnes Pratiques
- Ne jamais partager les identifiants
- Changer les mots de passe régulièrement
- Utiliser HTTPS en production
- Limiter les tentatives de connexion
- Surveiller les logs d'activité

## 🎨 Personnalisation

### Modifier les Couleurs

Dans `assets/css/style.css`, modifiez les variables CSS :

```css
:root {
    --primary-blue: #0a192f;
    --accent-cyan: #00d9ff;
    --accent-pink: #ff006e;
    --neon-blue: #00d9ff;
    /* ... */
}
```

### Ajouter une Page

1. Créez un fichier dans `/pages/`
2. Incluez header et footer :

```php
<?php
require_once __DIR__ . '/../includes/config.php';
require_once __DIR__ . '/../includes/auth.php';
check_auth();

$page_title = 'Ma Page';
$current_page = 'mon-slug';
include __DIR__ . '/../includes/header.php';
?>

<!-- Votre contenu ici -->

<?php include __DIR__ . '/../includes/footer.php'; ?>
```

3. Ajoutez un lien dans `includes/sidebar.php`

## 📱 Responsive

Le dashboard est entièrement responsive :
- **Desktop** (1024px+) : Layout complet avec sidebar
- **Tablet** (768px - 1023px) : Sidebar collapsible
- **Mobile** (< 768px) : Menu hamburger, cartes empilées

## 🐛 Dépannage

### Problème de Connexion à la DB
```
Erreur : SQLSTATE[HY000] [1045] Access denied
```
**Solution** : Vérifiez les identifiants dans `includes/config.php`

### Session Expire Trop Vite
```php
// Dans includes/config.php
define('SESSION_LIFETIME', 7200); // 2 heures au lieu de 1
```

### Erreur 404 sur les Pages
**Solution** : Vérifiez `BASE_URL` dans `includes/config.php`

### Graphique ne S'affiche Pas
**Solution** : Vérifiez que la table `users` contient des données

## 📝 Logs d'Activité

Toutes les actions sont loggées dans `admin_activity_logs` :

```sql
SELECT 
    al.*,
    a.name as admin_name
FROM admin_activity_logs al
INNER JOIN admins a ON al.admin_id = a.id
ORDER BY al.created_at DESC
LIMIT 50;
```

## 🚀 Déploiement en Production

### Checklist
- [ ] Changer tous les mots de passe par défaut
- [ ] Activer HTTPS (SSL/TLS)
- [ ] Configurer `secure` cookies en HTTPS
- [ ] Désactiver `error_reporting` en production
- [ ] Configurer des sauvegardes automatiques
- [ ] Restreindre l'accès aux fichiers sensibles
- [ ] Activer le rate limiting sur login
- [ ] Configurer fail2ban ou équivalent

### Configuration Production

```php
// includes/config.php
define('ENVIRONMENT', 'production');

if (ENVIRONMENT === 'production') {
    error_reporting(0);
    ini_set('display_errors', 0);
    $secure = true; // Cookies sécurisés en HTTPS
}
```

## 🆘 Support

Pour toute question ou problème :
- 📧 Email : support@atlantech.com
- 📱 Téléphone : +509 37 00 00 00
- 📖 Documentation : [docs.atlantech.com](https://docs.atlantech.com)

## 📄 Licence

© 2024 Atlantech Shop. Tous droits réservés.

## 👨‍💻 Développé par

**Claude AI** pour Atlantech Shop
Version 1.0.0 - Décembre 2024

---

**Note** : Ce dashboard fait partie du système d'administration modulaire d'Atlantech Shop. Les autres dashboards (Super Admin, Produits, Stock, Livraison) seront développés séparément.
