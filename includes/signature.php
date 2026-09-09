<?php
/**
 * Kasera-Signature-V1 verification. Pure function, no WordPress — see
 * tests/signature-test.php.
 *
 * Header format: "t=<unix>,v1=<hex>[,v1=<hex>]" — each v1 is lowercase hex
 * HMAC-SHA256 over "<unix>.<raw body>" keyed with the endpoint's signing
 * secret. Two v1 entries appear during the 24h after a secret rotation;
 * any match accepts. Deliveries older than five minutes are rejected.
 */

defined('ABSPATH') || defined('KASERA_PAY_TEST') || exit;

function kasera_pay_verify_signature(string $header, string $body, string $secret, ?int $now = null): bool
{
    $now = $now ?? time();
    $t = null;
    $sigs = [];
    foreach (explode(',', $header) as $part) {
        $kv = explode('=', $part, 2);
        if (count($kv) !== 2) {
            continue;
        }
        if ($kv[0] === 't') {
            $t = $kv[1];
        } elseif ($kv[0] === 'v1') {
            $sigs[] = strtolower($kv[1]);
        }
    }
    if ($t === null || !ctype_digit($t) || $sigs === []) {
        return false;
    }
    if (abs($now - (int) $t) > 300) {
        return false;
    }
    $expected = hash_hmac('sha256', $t . '.' . $body, $secret);
    foreach ($sigs as $sig) {
        if (hash_equals($expected, $sig)) {
            return true;
        }
    }
    return false;
}
