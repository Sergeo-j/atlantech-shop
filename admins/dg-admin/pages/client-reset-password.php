<?php
/**
 * Action DG : réinitialiser l'accès d'un client (cas extrême — VIP injoignable
 * ou perte d'accès urgente). Réservé au DG et au PDG (le superadmin a son
 * propre équivalent dans superadmin/pages/edit_user.php).
 *
 * Deux modes, jamais de mot de passe EXISTANT affiché ou récupéré (impossible :
 * les mots de passe sont hachés en Argon2id, à sens unique) :
 *   - action=link : envoie un lien de réinitialisation par email (même
 *     mécanisme que forgot-password.php — token haché en base, valable 1h)
 *   - action=temp : génère un NOUVEAU mot de passe temporaire aléatoire,
 *     l'affiche UNE SEULE FOIS à l'écran pour que le DG le transmette de vive
 *     voix au client, et force le changement à la prochaine connexion.
 */
require_once __DIR__ . '/../includes/auth.php';
require_dg_auth();

$user_id = (int)($_POST['user_id'] ?? $_GET['user_id'] ?? 0);
if (!$user_id) { header('Location: clients-list.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: client-view.php?id=' . $user_id);
    exit;
}
require_csrf();

$action = $_POST['action'] ?? '';

try {
    $st = $pdo->prepare("SELECT id, name, email, phone, is_active FROM users WHERE id = ? LIMIT 1");
    $st->execute([$user_id]);
    $client = $st->fetch();
} catch (PDOException $e) {
    error_log('client-reset-password lookup: ' . $e->getMessage());
    $client = null;
}

if (!$client) {
    header('Location: clients-list.php?error=not_found');
    exit;
}

if ($action === 'link') {
    try {
        $token      = bin2hex(random_bytes(32));
        $token_hash = hash('sha256', $token);
        $expires    = date('Y-m-d H:i:s', time() + 3600);

        $upd = $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?");
        $upd->execute([$token_hash, $expires, $user_id]);

        require_once __DIR__ . '/../../../config/mailer.php';
        if (!defined('SITE_URL')) {
            define('SITE_URL', env('SITE_URL', 'https://atlantech.shop'));
        }
        $reset_link = SITE_URL . '/reset-password.php?token=' . $token;
        $prenom     = explode(' ', (string)$client['name'])[0];

        $email_body = '
        <!DOCTYPE html><html lang="fr"><head><meta charset="UTF-8"></head>
        <body style="margin:0;padding:0;background:#f4f4f4;font-family:Arial,sans-serif;">
          <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:40px 0;">
            <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.1);">
              <tr><td style="background:#ff8717;padding:30px 40px;text-align:center;">
                <h1 style="color:#fff;margin:0;font-size:28px;letter-spacing:1px;">ATL&#9881;NTECH</h1>
              </td></tr>
              <tr><td style="padding:40px;">
                <h2 style="color:#111;margin:0 0 16px;">Bonjour ' . htmlspecialchars($prenom) . ',</h2>
                <p style="color:#555;line-height:1.7;margin:0 0 20px;">
                  Notre équipe a initié une réinitialisation de votre mot de passe AtlanTech à votre demande.<br>
                  Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe.<br>
                  <strong>Ce lien est valable pendant 1 heure.</strong>
                </p>
                <div style="text-align:center;margin:30px 0;">
                  <a href="' . $reset_link . '" style="background:#ff8717;color:#fff;padding:14px 36px;border-radius:4px;text-decoration:none;font-weight:bold;font-size:16px;display:inline-block;">Réinitialiser mon mot de passe</a>
                </div>
                <p style="color:#888;font-size:13px;margin:20px 0 0;">Si vous n\'êtes pas à l\'origine de cette demande, contactez immédiatement notre support.</p>
              </td></tr>
            </table>
          </td></tr></table>
        </body></html>';

        $sent = sendMailSMTP($client['email'], 'Réinitialisation de votre mot de passe AtlanTech', $email_body);

        log_dg_action(
            (int)$_SESSION['dg_id'],
            'client_reset_link_sent',
            "Lien de réinitialisation envoyé au client #$user_id (" . ($sent ? 'email envoyé' : 'échec envoi email, voir logs serveur') . ")"
        );

        $_SESSION['dg_reset_flash'] = [
            'type'    => $sent ? 'success' : 'warning',
            'message' => $sent
                ? 'Lien de réinitialisation envoyé à ' . htmlspecialchars($client['email']) . '.'
                : "Le lien a été généré mais l'envoi de l'email a échoué (config SMTP à vérifier). Le client peut aussi utiliser « Mot de passe oublié » directement sur le site.",
        ];
    } catch (\Throwable $e) {
        error_log('client-reset-password link: ' . $e->getMessage());
        $_SESSION['dg_reset_flash'] = ['type' => 'danger', 'message' => 'Erreur technique lors de la génération du lien.'];
    }

} elseif ($action === 'temp') {
    try {
        $temp_password = generate_temp_password(12);
        $hash = hash_password($temp_password);

        $upd = $pdo->prepare(
            "UPDATE users SET password = ?, force_password_change = 1, reset_token = NULL, reset_token_expires = NULL WHERE id = ?"
        );
        $upd->execute([$hash, $user_id]);

        // Le log ne contient JAMAIS le mot de passe en clair.
        log_dg_action(
            (int)$_SESSION['dg_id'],
            'client_temp_password_generated',
            "Mot de passe temporaire généré pour le client #$user_id — changement forcé à la prochaine connexion"
        );

        // Affiché UNE SEULE FOIS via la session, effacé dès sa lecture par client-view.php.
        $_SESSION['dg_reset_flash'] = [
            'type'          => 'success',
            'message'       => 'Mot de passe temporaire généré pour ' . htmlspecialchars($client['name']) . '. Communiquez-le au client de vive voix — il ne sera plus jamais affiché après cette page, et le client devra le changer dès sa prochaine connexion.',
            'temp_password' => $temp_password,
        ];
    } catch (\Throwable $e) {
        error_log('client-reset-password temp: ' . $e->getMessage());
        $_SESSION['dg_reset_flash'] = ['type' => 'danger', 'message' => 'Erreur technique lors de la génération du mot de passe temporaire.'];
    }

} else {
    $_SESSION['dg_reset_flash'] = ['type' => 'danger', 'message' => 'Action invalide.'];
}

header('Location: client-view.php?id=' . $user_id);
exit;
