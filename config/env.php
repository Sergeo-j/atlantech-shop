<?php
/**
 * Loader .env minimaliste — AtlanTech Shop
 *
 * Lit un fichier .env à la racine du projet et expose une fonction env()
 * pour récupérer les variables avec valeur par défaut.
 *
 * Avantages :
 *   - Aucune dépendance Composer requise
 *   - Lecture fichier une seule fois (cache statique)
 *   - Fallback automatique sur valeur par défaut si la variable n'existe pas
 *   - Compatible CLI et HTTP
 *
 * Usage :
 *   require_once __DIR__ . '/env.php';
 *   $host = env('DB_HOST', 'localhost');
 *   $user = env('DB_USER', 'root');
 *   $pass = env('DB_PASS', '');
 *
 * Format du fichier .env :
 *   # Commentaire
 *   DB_HOST=localhost
 *   DB_USER=root
 *   DB_PASS="mot de passe avec espaces"
 */

if (!function_exists('env')) {

    /**
     * Charge le fichier .env (une seule fois) et le met en cache.
     */
    function load_env_file(): array
    {
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        $cache = [];

        // Cherche .env à la racine du projet (config/ est au niveau 1)
        $envPath = dirname(__DIR__) . '/.env';

        if (!is_file($envPath) || !is_readable($envPath)) {
            return $cache; // Pas de .env → on retombe sur les valeurs par défaut
        }

        $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return $cache;
        }

        foreach ($lines as $line) {
            $line = trim($line);

            // Ignorer commentaires et lignes vides
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            // Format clé=valeur
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }

            $key   = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));

            // Retirer les guillemets entourants ("..." ou '...')
            if (
                strlen($value) >= 2 &&
                (
                    ($value[0] === '"' && substr($value, -1) === '"') ||
                    ($value[0] === "'" && substr($value, -1) === "'")
                )
            ) {
                $value = substr($value, 1, -1);
            }

            $cache[$key] = $value;
        }

        return $cache;
    }

    /**
     * Récupère une variable d'environnement.
     *
     * Ordre de priorité :
     *   1. Variable PHP via getenv() (utile en prod : Apache SetEnv, FPM env, etc.)
     *   2. Fichier .env à la racine du projet
     *   3. Valeur par défaut fournie à l'appel
     *
     * @param string $key     Nom de la variable
     * @param mixed  $default Valeur par défaut si introuvable
     * @return mixed
     */
    function env(string $key, $default = null)
    {
        // 1) Variable d'environnement système / serveur
        $val = getenv($key);
        if ($val !== false && $val !== '') {
            return $val;
        }

        // 2) Fichier .env
        $env = load_env_file();
        if (array_key_exists($key, $env)) {
            return $env[$key];
        }

        // 3) Default
        return $default;
    }
}
