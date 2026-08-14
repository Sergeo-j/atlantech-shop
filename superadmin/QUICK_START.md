# 🚀 Super Admin Dashboard - Guide Rapide

## ✨ Caractéristiques

- 👑 **Dashboard Super Admin ultra-futuriste**
- 🔐 **Argon2id automatique** pour tous les mots de passe
- 🎨 **Design violet/or** (différent du client-admin)
- ⚡ **Création d'admins avec attribution de rôles**
- 📊 **Statistiques complètes du système**

## 📦 Installation

1. **Extraire** dans votre projet
2. **Importer** `database.sql` dans phpMyAdmin
3. **Configurer** `includes/config.php`
4. **Accéder** : `http://localhost/atlantech-shop/superadmin/login.php`

## 🔑 Compte Super Admin par Défaut

- **Email**: `superadmin@atlantech.com`
- **Mot de passe**: `SuperAdmin123!`

⚠️ **IMPORTANT** : Changez ce mot de passe immédiatement !

## 🎯 Fonctionnalités Principales

### Créer un Admin
1. Connectez-vous en Super Admin
2. Allez dans "Gestion des Admins"
3. Cliquez "Créer un Admin"
4. Remplissez le formulaire
5. Le mot de passe sera **automatiquement hashé en Argon2id**

### Rôles Disponibles
- Client Admin (gestion clients)
- Product Admin (gestion produits)
- Stock Admin (gestion stock)  
- Delivery Admin (gestion livraisons)

## 🔐 Sécurité Argon2id

Tous les mots de passe sont automatiquement hashés avec :
- **Argon2id** (algorithme le plus sécurisé 2024)
- **64 MB** de mémoire
- **4 itérations**
- **3 threads parallèles**

## 📁 Structure

```
superadmin/
├── login.php              ← Connexion violet/or
├── pages/
│   ├── dashboard.php      ← Vue d'ensemble
│   ├── admin-create.php   ← Créer admin (Argon2id auto)
│   └── admins-list.php    ← Liste tous les admins
└── includes/
    ├── auth.php           ← Argon2id intégré
    └── functions.php      ← Création auto Argon2id
```

Tout est prêt ! 🎉
