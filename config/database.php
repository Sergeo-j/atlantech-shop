<?php
/**
 * Configuration de la base de données - AtlanTech-Shop
 * Fichier: config/database.php
 * Encodage: UTF-8
 * 
 * Ce fichier gère la connexion à la base de données pour tout le site
 */

// Empêcher l'accès direct au fichier
if (!defined('ATLANTECH_ACCESS')) {
    die('Accès direct non autorisé');
}

// Charger le loader .env (même répertoire que ce fichier)
require_once __DIR__ . '/env.php';

/**
 * Classe de gestion de la base de données
 *
 * Les valeurs de connexion sont lues depuis les variables d'environnement
 * (.env ou variables système). Voir .env.example pour la liste.
 */
class Database {

    // Configuration de la base de données — initialisée via init() depuis l'env
    private static $host = null;
    private static $db_name = null;
    private static $username = null;
    private static $password = null;
    private static $charset = null;
    private static $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT => false
    ];
    
    // Instance de connexion (singleton)
    private static $instance = null;
    private static $connection = null;
    
    /**
     * Constructeur privé (pattern singleton)
     */
    private function __construct() {}
    
    /**
     * Empêcher le clonage
     */
    private function __clone() {}
    
    /**
     * Obtenir l'instance unique de la classe
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Obtenir la connexion à la base de données
     * 
     * @return PDO Instance de connexion PDO
     * @throws Exception En cas d'erreur de connexion
     */
    /**
     * Initialise les paramètres de connexion depuis les variables d'environnement.
     * Appelé automatiquement par getConnection() au premier appel.
     */
    private static function initConfig() {
        if (self::$host !== null) return;
        self::$host     = env('DB_HOST', 'localhost');
        self::$db_name  = env('DB_NAME', 'atlantech_shop');
        self::$username = env('DB_USER', 'root');
        self::$password = env('DB_PASS', '');
        self::$charset  = env('DB_CHARSET', 'utf8mb4');
    }

    public static function getConnection() {
        self::initConfig();
        if (self::$connection === null) {
            try {
                $dsn = sprintf(
                    'mysql:host=%s;dbname=%s;charset=%s',
                    self::$host,
                    self::$db_name,
                    self::$charset
                );
                
                self::$connection = new PDO($dsn, self::$username, self::$password, self::$options);
                
                // Log de connexion réussie (optionnel)
                self::logConnection('success');
                
            } catch (PDOException $e) {
                // Log de l'erreur
                self::logConnection('error', $e->getMessage());
                
                // Message d'erreur utilisateur (sans détails sensibles)
                throw new Exception('Erreur de connexion à la base de données. Veuillez réessayer plus tard.');
            }
        }
        
        return self::$connection;
    }
    
    /**
     * Fermer la connexion
     */
    public static function closeConnection() {
        self::$connection = null;
    }
    
    /**
     * Tester la connexion à la base de données
     * 
     * @return bool True si la connexion réussit, false sinon
     */
    public static function testConnection() {
        try {
            $connection = self::getConnection();
            $stmt = $connection->query('SELECT 1');
            return $stmt !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    /**
     * Obtenir les informations de la base de données (sans mots de passe)
     * 
     * @return array Informations de configuration
     */
    public static function getInfo() {
        self::initConfig();
        return [
            'host' => self::$host,
            'database' => self::$db_name,
            'charset' => self::$charset,
            'connected' => self::$connection !== null
        ];
    }
    
    /**
     * Exécuter une requête préparée simple
     * 
     * @param string $query Requête SQL
     * @param array $params Paramètres de la requête
     * @return PDOStatement Résultat de la requête
     */
    public static function query($query, $params = []) {
        try {
            $connection = self::getConnection();
            $stmt = $connection->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            // Log de l'erreur
            error_log('Erreur SQL: ' . $e->getMessage() . ' | Requête: ' . $query);
            throw new Exception('Erreur lors de l\'exécution de la requête.');
        }
    }
    
    /**
     * Obtenir le dernier ID inséré
     * 
     * @return string Dernier ID inséré
     */
    public static function lastInsertId() {
        return self::getConnection()->lastInsertId();
    }
    
    /**
     * Commencer une transaction
     */
    public static function beginTransaction() {
        return self::getConnection()->beginTransaction();
    }
    
    /**
     * Valider une transaction
     */
    public static function commit() {
        return self::getConnection()->commit();
    }
    
    /**
     * Annuler une transaction
     */
    public static function rollback() {
        return self::getConnection()->rollback();
    }
    
    /**
     * Échapper une chaîne pour éviter les injections SQL
     * (Utilisez plutôt les requêtes préparées)
     * 
     * @param string $string Chaîne à échapper
     * @return string Chaîne échappée
     */
    public static function escape($string) {
        return self::getConnection()->quote($string);
    }
    
    /**
     * Logger les événements de connexion
     * 
     * @param string $type Type d'événement (success, error)
     * @param string $message Message détaillé (optionnel)
     */
    private static function logConnection($type, $message = '') {
        $logFile = __DIR__ . '/../logs/database.log';
        $logDir = dirname($logFile);
        
        // Créer le dossier de logs s'il n'existe pas
        if (!is_dir($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        
        $logEntry = sprintf(
            "[%s] %s - IP: %s - %s\n",
            $timestamp,
            strtoupper($type),
            $ip,
            $message
        );
        
        file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
}

/**
 * Fonctions utilitaires globales pour la base de données
 */

/**
 * Obtenir une connexion rapide à la base de données
 * 
 * @return PDO Instance de connexion
 */
function getDB() {
    return Database::getConnection();
}

/**
 * Exécuter une requête SQL simple
 * 
 * @param string $query Requête SQL
 * @param array $params Paramètres
 * @return PDOStatement Résultat
 */
function executeQuery($query, $params = []) {
    return Database::query($query, $params);
}

/**
 * Récupérer une seule ligne
 * 
 * @param string $query Requête SQL
 * @param array $params Paramètres
 * @return array|false Ligne de résultat ou false
 */
function fetchOne($query, $params = []) {
    $stmt = Database::query($query, $params);
    return $stmt->fetch();
}

/**
 * Récupérer toutes les lignes
 * 
 * @param string $query Requête SQL
 * @param array $params Paramètres
 * @return array Tableau de résultats
 */
function fetchAll($query, $params = []) {
    $stmt = Database::query($query, $params);
    return $stmt->fetchAll();
}

/**
 * Insérer des données
 * 
 * @param string $table Nom de la table
 * @param array $data Données à insérer (clé => valeur)
 * @return int ID du dernier enregistrement inséré
 */
function insertData($table, $data) {
    $columns = implode(', ', array_keys($data));
    $placeholders = ':' . implode(', :', array_keys($data));
    
    $query = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
    
    Database::query($query, $data);
    return Database::lastInsertId();
}

/**
 * Mettre à jour des données
 * 
 * @param string $table Nom de la table
 * @param array $data Données à mettre à jour
 * @param array $where Conditions WHERE
 * @return int Nombre de lignes affectées
 */
function updateData($table, $data, $where) {
    $setParts = [];
    foreach ($data as $key => $value) {
        $setParts[] = "{$key} = :{$key}";
    }
    $setClause = implode(', ', $setParts);
    
    $whereParts = [];
    foreach ($where as $key => $value) {
        $whereParts[] = "{$key} = :where_{$key}";
    }
    $whereClause = implode(' AND ', $whereParts);
    
    $query = "UPDATE {$table} SET {$setClause} WHERE {$whereClause}";
    
    // Combiner les paramètres
    $params = $data;
    foreach ($where as $key => $value) {
        $params["where_{$key}"] = $value;
    }
    
    $stmt = Database::query($query, $params);
    return $stmt->rowCount();
}

/**
 * Supprimer des données
 * 
 * @param string $table Nom de la table
 * @param array $where Conditions WHERE
 * @return int Nombre de lignes supprimées
 */
function deleteData($table, $where) {
    $whereParts = [];
    foreach ($where as $key => $value) {
        $whereParts[] = "{$key} = :{$key}";
    }
    $whereClause = implode(' AND ', $whereParts);
    
    $query = "DELETE FROM {$table} WHERE {$whereClause}";
    
    $stmt = Database::query($query, $where);
    return $stmt->rowCount();
}

// Auto-test de la connexion lors de l'inclusion (optionnel)
if (defined('DB_AUTO_TEST') && DB_AUTO_TEST === true) {
    try {
        if (Database::testConnection()) {
            echo "✅ Connexion à la base de données réussie\n";
        } else {
            echo "❌ Échec de la connexion à la base de données\n";
        }
    } catch (Exception $e) {
        echo "❌ Erreur: " . $e->getMessage() . "\n";
    }
}
?>