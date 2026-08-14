# 🛒 ORDER ADMIN - Gestion des Commandes

Module de gestion des commandes pour Atlantech Shop.

## 📦 Installation RAPIDE

### 1. Copier le dossier
```
Extraire dans : C:\wamp64\www\atlantech-shop\admins\order-admin\
```

### 2. Créer un compte admin (via Superadmin)
1. Connectez-vous au **Superadmin**
2. Allez dans **"Order Admins"** 
3. Créez un admin :
   - Nom : Order Manager
   - Email : order@atlantech.com
   - Mot de passe : Order2024!

### 3. Tester le système
```
Ouvrir : http://localhost/atlantech-shop/admins/order-admin/test_login.php
```
Ce script vérifie :
- ✅ Connexion DB
- ✅ Liste des admins
- ✅ Type de hash
- ✅ **Teste si "Order2024!" fonctionne**

### 4. Se connecter
```
URL : http://localhost/atlantech-shop/admins/order-admin/login.php
Email : order@atlantech.com
Password : Order2024!
```

## 🔐 Système de Login

**ULTRA SIMPLE :**
```php
// Vérifier le mot de passe
if (!password_verify($password, $admin['password'])) {
    // Échec
}
// Succès - Fonctionne avec Argon2id ET bcrypt !
```

## 🐛 Problème de connexion ?

### Solution 1 : Diagnostic
```
http://localhost/atlantech-shop/admins/order-admin/test_login.php
```
Le script teste automatiquement les mots de passe communs.

### Solution 2 : Réinitialiser
```
http://localhost/atlantech-shop/superadmin/reset_order_admin_password.php
```
Choisissez l'admin et réinitialisez avec : Order2024!

### Solution 3 : Vérifier dans la BD
```sql
SELECT id, email, is_active, password 
FROM admins 
WHERE role = 'order_admin';
```
Vérifiez que :
- ✅ L'admin existe
- ✅ is_active = 1
- ✅ Le hash commence par `$argon2id$` ou `$2y$`

## ✨ Fonctionnalités

### Dashboard (index.php)
- 📊 Statistiques temps réel
- 📈 Graphiques (Chart.js)
- 📦 Dernières commandes

### Login (login.php)
- 🔐 Authentification sécurisée
- ✅ Support Argon2id + bcrypt
- 🔒 Sessions sécurisées

### Test (test_login.php)
- 🧪 Diagnostic complet
- ✅ Test automatique du mot de passe
- 📋 Affichage des logs

## 📁 Structure

```
order-admin/
├── login.php              # Connexion
├── logout.php             # Déconnexion
├── index.php              # Dashboard
├── test_login.php         # 🆕 Diagnostic
├── actions/
│   └── login_process.php  # Traitement login (SIMPLIFIÉ)
├── includes/
│   ├── auth_check.php     # Protection pages
│   ├── db.php             # Connexion DB
│   └── functions.php      # Utilitaires
└── assets/
    └── css/
        └── style.css      # Design cyberpunk

```

## 🔒 Sécurité

✅ Hash Argon2id (ou bcrypt en fallback)
✅ password_verify() - Compatible les 2
✅ Sessions sécurisées
✅ Validation des entrées
✅ Protection SQL injection (PDO)
✅ Logging optionnel

## 💡 Important

1. **Le login est SIMPLE** - pas de fonctions compliquées
2. **password_verify()** gère Argon2id ET bcrypt automatiquement
3. **test_login.php** vous dit EXACTEMENT ce qui ne va pas
4. **Le hash est créé par le superadmin** avec Argon2id

## 📞 Support

Si le login ne marche pas :
1. Lancez **test_login.php** 
2. Vérifiez que l'admin existe et est actif
3. Utilisez **reset_order_admin_password.php** (superadmin)
4. Regardez les logs dans `admin_logs`

---

**Version** : 2.0.0 (Login simplifié)  
**Date** : Décembre 2024  
**Développeur** : Serge - Atlantech Shop
