# 🔐 Guide Complet : Argon2id pour Atlantech Shop

## 📋 Qu'est-ce qu'Argon2id ?

**Argon2** est l'algorithme de hashage de mot de passe gagnant du **Password Hashing Competition (2015)**. C'est actuellement le standard le plus sécurisé recommandé par l'OWASP.

### Variantes d'Argon2 :
- **Argon2d** : Optimisé contre les attaques GPU (moins de protection contre les attaques par canaux auxiliaires)
- **Argon2i** : Optimisé contre les attaques par canaux auxiliaires
- **Argon2id** : ✅ **MEILLEUR CHOIX** - Combine les avantages des deux

---

## 🆚 Comparaison : Argon2id vs BCrypt

| Critère | BCrypt | Argon2id |
|---------|--------|----------|
| **Sécurité Globale** | ⭐⭐⭐⭐ | ⭐⭐⭐⭐⭐ |
| **Résistance GPU** | Moyen | Excellent |
| **Résistance ASIC** | Faible | Excellent |
| **Utilisation Mémoire** | ~4 KB | **64 MB** (configurable) |
| **Résistance aux attaques parallèles** | Moyen | Excellent |
| **Standard OWASP** | ✅ Oui | ✅ Oui (recommandé) |
| **Année de création** | 1999 | 2015 |
| **PHP requis** | ≥ 5.5 | ≥ 7.2 |

**Verdict** : Argon2id est **plus sécurisé** que BCrypt pour 2024+

---

## ⚙️ Configuration Argon2id

### Paramètres Utilisés dans Atlantech Shop :

```php
password_hash($password, PASSWORD_ARGON2ID, [
    'memory_cost' => 65536,  // 64 MB de RAM
    'time_cost' => 4,        // 4 itérations
    'threads' => 3           // 3 threads parallèles
]);
```

### Explication des Paramètres :

1. **memory_cost** (65536 = 64 MB)
   - Quantité de mémoire RAM utilisée pour le hashage
   - Plus élevé = plus sécurisé, mais plus lent
   - Recommandation : 64 MB pour serveur web

2. **time_cost** (4 itérations)
   - Nombre d'itérations de l'algorithme
   - Plus élevé = plus sécurisé, mais plus lent
   - Recommandation : 3-4 pour web, 6-10 pour haute sécurité

3. **threads** (3 threads)
   - Nombre de threads parallèles
   - Recommandation : 2-4 threads

---

## 📊 Performance

### Temps de Hashage (serveur moyen) :

| Configuration | Temps | Utilisation |
|---------------|-------|-------------|
| 32 MB, t=3, p=2 | ~50ms | ⚠️ Rapide (moins sécurisé) |
| **64 MB, t=4, p=3** | ~**150ms** | ✅ **Recommandé (Atlantech)** |
| 128 MB, t=5, p=4 | ~400ms | ✅ Haute sécurité |
| 256 MB, t=6, p=4 | ~1s | ⚠️ Trop lent pour web |

**Notre choix** : 64 MB offre un bon équilibre sécurité/performance.

---

## 🛠️ Utilisation

### 1️⃣ Générer un Hash

Utilisez le script fourni :

```bash
cd /chemin/vers/client-admin
php generate_hash.php
```

**Exemple** :
```
Entrez le mot de passe : MonMotDePasseSecurise123!
Confirmer le mot de passe : MonMotDePasseSecurise123!

✅ Hash généré avec succès !

Hash : $argon2id$v=19$m=65536,t=4,p=3$BASE64SALT$BASE64HASH
```

### 2️⃣ Insérer dans la Base de Données

```sql
INSERT INTO admins (name, email, password, role)
VALUES (
    'Nouveau Admin',
    'admin@example.com',
    '$argon2id$v=19$m=65536,t=4,p=3$...',  -- Hash généré
    'client'
);
```

### 3️⃣ Vérifier un Mot de Passe

Le système vérifie automatiquement (dans `auth.php`) :

```php
if (password_verify($password, $hash)) {
    // ✅ Mot de passe correct
}
```

---

## 🔄 Migration Automatique

Le système migre **automatiquement** les anciens hash vers Argon2id lors de la connexion !

### Comment ça marche ?

1. **Utilisateur se connecte** avec son mot de passe
2. **Système vérifie** le mot de passe (fonctionne même avec BCrypt/MD5/SHA-1)
3. **Si connexion réussie**, le système détecte que le hash n'est pas Argon2id
4. **Système rehash** automatiquement avec Argon2id
5. **Base de données mise à jour** avec le nouveau hash

### Code (déjà dans auth.php) :

```php
if ($admin && password_verify($password, $admin['password'])) {
    // Connexion réussie
    
    // Vérifier si migration nécessaire
    if (password_needs_rehash($admin['password'], PASSWORD_ARGON2ID)) {
        $new_hash = hash_password($password);
        $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?")
            ->execute([$new_hash, $admin['id']]);
    }
}
```

**Avantage** : Migration transparente, sans action de l'utilisateur !

---

## 📝 Scripts Fournis

### 1. `generate_hash.php`
Génère un hash Argon2id pour un nouveau mot de passe.

**Utilisation** :
```bash
php generate_hash.php
```

### 2. `migrate_to_argon2.php`
Analyse et aide à migrer les mots de passe existants.

**Utilisation** :
```bash
php migrate_to_argon2.php
```

**Options** :
- Migration automatique (recommandé)
- Migration manuelle
- Réinitialisation forcée

---

## ✅ Avantages d'Argon2id

### 🛡️ Sécurité Maximale

1. **Résistance GPU/ASIC**
   - Argon2id utilise **64 MB de RAM** par hash
   - Les attaques matérielles deviennent **extrêmement coûteuses**

2. **Résistance aux attaques parallèles**
   - L'utilisation de mémoire rend difficile le calcul de millions de hash simultanément

3. **Protection contre les attaques par canaux auxiliaires**
   - Argon2id protège contre les attaques temporelles

### 📈 Performance Optimale

- Temps de hashage : ~150ms (acceptable pour connexion web)
- Pas d'impact sur l'expérience utilisateur
- Scalabilité : les paramètres peuvent augmenter avec la puissance serveur

### 🔮 Pérennité

- **Standard moderne** (2015 - actuel)
- **Recommandé par l'OWASP** pour 2024+
- **Supporté nativement** par PHP 7.2+

---

## 🔧 Configuration Avancée

### Augmenter la Sécurité (Serveur Puissant)

```php
password_hash($password, PASSWORD_ARGON2ID, [
    'memory_cost' => 131072,  // 128 MB (au lieu de 64 MB)
    'time_cost' => 5,         // 5 itérations (au lieu de 4)
    'threads' => 4            // 4 threads (au lieu de 3)
]);
```

### Diminuer pour Serveur Faible

```php
password_hash($password, PASSWORD_ARGON2ID, [
    'memory_cost' => 32768,   // 32 MB
    'time_cost' => 3,         // 3 itérations
    'threads' => 2            // 2 threads
]);
```

### Tester la Performance

```php
$start = microtime(true);
$hash = hash_password('test_password');
$duration = (microtime(true) - $start) * 1000;

echo "Temps de hashage : {$duration}ms\n";
```

**Recommandation** : Le temps devrait être entre **100ms et 500ms**.

---

## ⚠️ Prérequis

### Version PHP

```bash
php -v
# Doit afficher PHP 7.2.0 ou supérieur
```

Si PHP < 7.2, le système utilisera automatiquement **BCrypt** comme fallback.

### Vérifier Argon2id

```php
<?php
if (defined('PASSWORD_ARGON2ID')) {
    echo "✅ Argon2id disponible\n";
} else {
    echo "❌ Argon2id non disponible\n";
    echo "Version PHP : " . PHP_VERSION . "\n";
}
?>
```

### Mettre à Jour PHP (si nécessaire)

**Ubuntu/Debian** :
```bash
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.2
```

**Windows (WAMP)** :
- Télécharger PHP 8.2+ depuis php.net
- Remplacer dans `wamp64/bin/php/`

---

## 🔐 Format du Hash Argon2id

```
$argon2id$v=19$m=65536,t=4,p=3$BASE64SALT$BASE64HASH
│         │     │       │   │   │           │
│         │     │       │   │   │           └─ Hash (Base64)
│         │     │       │   │   └─ Sel (Base64, 16 octets)
│         │     │       │   └─ Parallélisme (threads)
│         │     │       └─ Temps (itérations)
│         │     └─ Mémoire (en KB, 65536 = 64 MB)
│         └─ Version (19 = dernière version)
└─ Algorithme (argon2id)
```

**Longueur totale** : ~97 caractères

---

## 📚 Ressources

- [RFC 9106 - Argon2 Specification](https://www.rfc-editor.org/rfc/rfc9106.html)
- [OWASP Password Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html)
- [PHP password_hash Documentation](https://www.php.net/manual/en/function.password-hash.php)
- [Argon2 Official Site](https://github.com/P-H-C/phc-winner-argon2)

---

## ✅ Checklist de Sécurité

- [x] Argon2id activé
- [x] Migration automatique implémentée
- [x] Paramètres optimaux configurés (64 MB, t=4, p=3)
- [x] Scripts de génération fournis
- [x] Fallback sur BCrypt si Argon2 indisponible
- [x] Vérification de version PHP
- [x] Documentation complète

---

**© 2024 Atlantech Shop - Tous droits réservés**
