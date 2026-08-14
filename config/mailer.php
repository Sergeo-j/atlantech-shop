<?php
/**
 * Configuration PHPMailer — AtlanTech
 * Envoi d'emails via Gmail SMTP
 *
 * Les credentials sont lus depuis .env (jamais hardcodés ici).
 * Pour créer un mot de passe d'application Google :
 *   → https://myaccount.google.com/apppasswords
 *   (Activer d'abord la validation en 2 étapes sur ton compte Gmail)
 */

// ── Charger PHPMailer si Composer est installé ────────────────────────
$_atl_autoload = __DIR__ . '/../vendor/autoload.php';
if (is_file($_atl_autoload)) {
    require_once $_atl_autoload;
    define('ATL_MAILER_AVAILABLE', class_exists('\\PHPMailer\\PHPMailer\\PHPMailer'));
} else {
    define('ATL_MAILER_AVAILABLE', false);
    error_log('mailer.php: vendor/autoload.php manquant — emails désactivés. Lancer "composer install" pour les réactiver.');
}

// ── Charger env.php si la fonction env() n'est pas encore disponible ─
if (!function_exists('env')) {
    require_once __DIR__ . '/env.php';
}

// ── Identifiants Gmail — lus depuis .env uniquement ──────────────────
if (!defined('GMAIL_USER')) {
    define('GMAIL_USER',         env('GMAIL_USER',         ''));
}
if (!defined('GMAIL_APP_PASSWORD')) {
    define('GMAIL_APP_PASSWORD', env('GMAIL_APP_PASSWORD', ''));
}
if (!defined('MAIL_FROM_NAME')) {
    define('MAIL_FROM_NAME',     env('MAIL_FROM_NAME',     'AtlanTech'));
}

/**
 * Envoie un email HTML via Gmail SMTP.
 * Si PHPMailer n'est pas installé (vendor/ absent), renvoie false
 * silencieusement sans rien casser.
 *
 * @param  string $to       Adresse du destinataire
 * @param  string $subject  Objet du mail
 * @param  string $body     Corps HTML du mail
 * @return bool  true si envoyé, false sinon
 */
function sendMailSMTP(string $to, string $subject, string $body): bool
{
    if (!defined('ATL_MAILER_AVAILABLE') || !ATL_MAILER_AVAILABLE) {
        error_log("sendMailSMTP: PHPMailer indisponible, email non envoyé à $to (sujet: $subject)");
        return false;
    }

    if (GMAIL_USER === '' || GMAIL_APP_PASSWORD === '') {
        error_log("sendMailSMTP: GMAIL_USER ou GMAIL_APP_PASSWORD manquant dans .env");
        return false;
    }

    $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Serveur SMTP
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = GMAIL_USER;
        $mail->Password   = GMAIL_APP_PASSWORD;
        $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        // Expéditeur / destinataire
        $mail->setFrom(GMAIL_USER, MAIL_FROM_NAME);
        $mail->addAddress($to);

        // Contenu
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        $mail->AltBody = strip_tags($body);

        $mail->send();
        return true;

    } catch (\Throwable $e) {
        $info = isset($mail) && is_object($mail) ? ($mail->ErrorInfo ?? '') : '';
        error_log('PHPMailer Error: ' . ($info ?: $e->getMessage()));
        return false;
    }
}
