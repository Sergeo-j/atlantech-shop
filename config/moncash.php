<?php
/**
 * AtlanTech — Intégration MonCash (Digicel) via REST API
 *
 * Helper autonome (cURL) — ne dépend pas de Composer ni du SDK.
 * Credentials lus depuis .env :
 *   MONCASH_MODE          sandbox | live
 *   MONCASH_CLIENT_ID
 *   MONCASH_CLIENT_SECRET
 *
 * Flux :
 *   1. moncash_get_token()                 → jeton OAuth (Bearer)
 *   2. moncash_create_payment($amt,$oid)   → token de paiement (à rediriger)
 *   3. moncash_gateway_url($paymentToken)  → URL de la passerelle
 *   4. (client paie, MonCash redirige vers la page retour)
 *   5. moncash_retrieve_by_order($oid)     → détails du paiement (transaction_id, status…)
 *      ou moncash_retrieve_by_transaction($txId)
 *
 * Toutes les fonctions renvoient un tableau ['ok'=>bool, ...] et ne lèvent
 * jamais d'exception bruyante (erreurs loggées via error_log).
 */

require_once __DIR__ . '/env.php';

// ── Endpoints selon le mode ─────────────────────────────────────────
function moncash_config(): array {
    $mode = env('MONCASH_MODE', 'sandbox') === 'live' ? 'live' : 'sandbox';
    if ($mode === 'live') {
        return [
            'mode'         => 'live',
            'rest'         => 'https://moncashbutton.digicelgroup.com/Api',
            'redirect'     => 'https://moncashbutton.digicelgroup.com/Moncash-middleware',
            'client_id'    => env('MONCASH_CLIENT_ID', ''),
            'client_secret'=> env('MONCASH_CLIENT_SECRET', ''),
            'cainfo'       => env('MONCASH_CAINFO', ''),
        ];
    }
    return [
        'mode'         => 'sandbox',
        'rest'         => 'https://sandbox.moncashbutton.digicelgroup.com/Api',
        'redirect'     => 'https://sandbox.moncashbutton.digicelgroup.com/Moncash-middleware',
        'client_id'    => env('MONCASH_CLIENT_ID', ''),
        'client_secret'=> env('MONCASH_CLIENT_SECRET', ''),
        'cainfo'       => env('MONCASH_CAINFO', ''),
    ];
}

// ── Requête HTTP cURL générique ─────────────────────────────────────
function _moncash_http(string $method, string $url, array $opts = []): array {
    $cfg = moncash_config();
    $ch = curl_init($url);
    $headers = $opts['headers'] ?? [];
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    if (!empty($cfg['cainfo']) && is_file($cfg['cainfo'])) {
        curl_setopt($ch, CURLOPT_CAINFO, $cfg['cainfo']);
    }
    if (isset($opts['userpwd']))  curl_setopt($ch, CURLOPT_USERPWD, $opts['userpwd']);
    if (isset($opts['body']))     curl_setopt($ch, CURLOPT_POSTFIELDS, $opts['body']);

    $raw  = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    if ($raw === false) {
        error_log("MonCash HTTP error ($url): $err");
        return ['ok' => false, 'http' => 0, 'error' => $err, 'data' => null];
    }
    $data = json_decode($raw, true);
    $ok   = ($code >= 200 && $code < 300);
    if (!$ok) error_log("MonCash HTTP $code ($url): $raw");
    return ['ok' => $ok, 'http' => $code, 'data' => $data, 'raw' => $raw];
}

/**
 * 1) Obtenir un jeton OAuth (client_credentials).
 * @return array ['ok'=>bool, 'token'=>string|null]
 */
function moncash_get_token(): array {
    $cfg = moncash_config();
    if (empty($cfg['client_id']) || empty($cfg['client_secret'])) {
        return ['ok' => false, 'token' => null, 'error' => 'Credentials MonCash manquants (.env)'];
    }
    $res = _moncash_http('POST', $cfg['rest'] . '/oauth/token', [
        'userpwd' => $cfg['client_id'] . ':' . $cfg['client_secret'],
        'headers' => ['Accept: application/json'],
        'body'    => http_build_query([
            'scope'      => 'read,write',
            'grant_type' => 'client_credentials',
        ]),
    ]);
    $token = $res['data']['access_token'] ?? null;
    return ['ok' => ($res['ok'] && $token), 'token' => $token, 'raw' => $res['raw'] ?? null];
}

/**
 * 2) Créer un paiement → renvoie le token de paiement à rediriger.
 * @param float  $amount   montant en HTG
 * @param string $orderId  identifiant de commande (notre order_number)
 * @return array ['ok'=>bool, 'payment_token'=>string|null]
 */
function moncash_create_payment(float $amount, string $orderId): array {
    $tok = moncash_get_token();
    if (!$tok['ok']) return ['ok' => false, 'payment_token' => null, 'error' => 'Auth MonCash échouée'];

    $cfg = moncash_config();
    $res = _moncash_http('POST', $cfg['rest'] . '/v1/CreatePayment', [
        'headers' => [
            'Authorization: Bearer ' . $tok['token'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        'body' => json_encode([
            'amount'  => round($amount, 2),
            'orderId' => $orderId,
        ]),
    ]);
    // La réponse contient payment_token.token
    $ptoken = $res['data']['payment_token']['token'] ?? null;
    return ['ok' => ($res['ok'] && $ptoken), 'payment_token' => $ptoken, 'raw' => $res['raw'] ?? null];
}

/**
 * 3) URL de la passerelle où rediriger le client pour payer.
 */
function moncash_gateway_url(string $paymentToken): string {
    $cfg = moncash_config();
    return $cfg['redirect'] . '/Payment/Redirect?token=' . urlencode($paymentToken);
}

/**
 * 5a) Récupérer le paiement par orderId (notre order_number).
 * @return array ['ok'=>bool, 'payment'=>array|null]
 *   payment contient : transaction_id, cost, message, payer, reference…
 */
function moncash_retrieve_by_order(string $orderId): array {
    $tok = moncash_get_token();
    if (!$tok['ok']) return ['ok' => false, 'payment' => null, 'error' => 'Auth MonCash échouée'];

    $cfg = moncash_config();
    $res = _moncash_http('POST', $cfg['rest'] . '/v1/RetrieveOrderPayment', [
        'headers' => [
            'Authorization: Bearer ' . $tok['token'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        'body' => json_encode(['orderId' => $orderId]),
    ]);
    $payment = $res['data']['payment'] ?? null;
    return ['ok' => ($res['ok'] && $payment), 'payment' => $payment, 'raw' => $res['raw'] ?? null];
}

/**
 * 5b) Récupérer le paiement par transactionId MonCash.
 * @return array ['ok'=>bool, 'payment'=>array|null]
 */
function moncash_retrieve_by_transaction(string $transactionId): array {
    $tok = moncash_get_token();
    if (!$tok['ok']) return ['ok' => false, 'payment' => null, 'error' => 'Auth MonCash échouée'];

    $cfg = moncash_config();
    $res = _moncash_http('POST', $cfg['rest'] . '/v1/RetrieveTransactionPayment', [
        'headers' => [
            'Authorization: Bearer ' . $tok['token'],
            'Content-Type: application/json',
            'Accept: application/json',
        ],
        'body' => json_encode(['transactionId' => $transactionId]),
    ]);
    $payment = $res['data']['payment'] ?? null;
    return ['ok' => ($res['ok'] && $payment), 'payment' => $payment, 'raw' => $res['raw'] ?? null];
}
