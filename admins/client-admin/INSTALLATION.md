# 🚀 Installation Rapide - Client Admin Dashboard

## 📁 Structure à Installer

Extrayez le dossier `client-admin` dans votre projet :

```
C:\wamp64\www\atlantech-shop\
├── client-admin\              ← Votre nouveau dashboard
│   ├── assets\
│   ├── includes\
│   ├── pages\
│   └── login.php
```

## ⚙️ Configuration (2 étapes)

### 1️⃣ Configurer la Base de Données

Éditez `client-admin/includes/config.php` :

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'atldb');          // Votre base de données
define('DB_USER', 'root');
define('DB_PASS', '');               // Mot de passe MySQL
```

**C'EST TOUT !** Les chemins CSS/JS sont relatifs, plus besoin de BASE_URL.

### 2️⃣ Importer la Base de Données

Dans phpMyAdmin :
1. Ouvrez votre base `atldb`
2. Importez `database.sql`

Ou via ligne de commande :
```bash
mysql -u root -p atldb < database.sql
```

## 🔑 Connexion

Accédez à : 
```
http://localhost/atlantech-shop/client-admin/login.php
```

**Identifiants par défaut :**
- Email : `admin.client@atlantech.com`
- Mot de passe : `admin123`

## ✅ Résolution des Problèmes

### CSS ne charge pas ?

**Vérifiez** que votre structure est correcte :
```
client-admin/
├── assets/
│   └── css/
│       └── style.css       ← Ce fichier doit exister !
├── includes/
├── pages/
└── login.php
```

**Testez** : Ouvrez dans le navigateur
```
http://localhost/atlantech-shop/client-admin/assets/css/style.css
```

Si le CSS s'affiche, c'est bon ! Sinon :
1. Vérifiez que le dossier `assets` est bien là
2. Videz le cache du navigateur (Ctrl+F5)

### Erreur "Could not connect to database" ?

1. Vérifiez que WAMP est démarré (icône verte)
2. Vérifiez vos identifiants dans `includes/config.php`
3. Vérifiez que la base `atldb` existe dans phpMyAdmin

### Erreur "Session expired" immédiatement ?

Éditez `includes/config.php` et augmentez le timeout :
```php
define('SESSION_LIFETIME', 7200); // 2 heures au lieu de 1
```

## 📂 Chemins Utilisés

**Login** : Chemins relatifs depuis la racine
```html
<link href="assets/css/style.css">
```

**Pages internes** : Chemins relatifs depuis `/pages/`
```html
<link href="../assets/css/style.css">
```

## 🔐 Créer un Nouveau Admin (Argon2id)

```bash
cd C:\wamp64\www\atlantech-shop\client-admin
php generate_hash.php
```

Puis copiez le hash généré dans votre requête SQL.

## 📝 Résumé

✅ **Simple** : Pas de configuration d'URL complexe  
✅ **Portable** : Fonctionne n'importe où  
✅ **Sécurisé** : Argon2id par défaut  
✅ **Prêt** : Aucune autre configuration nécessaire  

---

**Besoin d'aide ?** Consultez le `README.md` complet ou `ARGON2_GUIDE.md`
