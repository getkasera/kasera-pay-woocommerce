<?php
// Run: php tests/signature-test.php
// The reference signature was computed outside PHP (python hmac) so this
// checks the implementation, not itself.

define('KASERA_PAY_TEST', true);
require __DIR__ . '/../includes/signature.php';

$secret = 'whsec_testsecret';
$body   = '{"id":"evt_1","type":"payment.paid"}';
$t      = 1757400000;
$sig    = '5f9ae86b39e7d930ed2df6402275182ba092aa4385fea1dd29453b59f4f5e81a';

$ok = fn(string $header, ?int $now = null) => kasera_pay_verify_signature($header, $body, $secret, $now ?? $t);

assert($ok("t=$t,v1=$sig") === true);
assert($ok('t=' . $t . ',v1=' . strtoupper($sig)) === true);            // case-insensitive hex
assert($ok("t=$t,v1=deadbeef,v1=$sig") === true);                        // rotation grace: any entry matches
assert($ok("t=$t,v1=$sig", $t + 300) === true);                          // exactly at tolerance
assert($ok("t=$t,v1=$sig", $t + 301) === false);                         // too old
assert($ok("t=$t,v1=$sig", $t - 301) === false);                         // from the future
assert($ok('t=' . ($t + 1) . ",v1=$sig") === false);                     // timestamp not the signed one
assert($ok("t=$t,v1=deadbeef") === false);                               // wrong signature
assert($ok("v1=$sig") === false);                                        // no timestamp
assert($ok("t=$t") === false);                                           // no signature
assert($ok('') === false);
assert($ok("t=abc,v1=$sig") === false);                                  // non-numeric timestamp
assert(kasera_pay_verify_signature("t=$t,v1=$sig", $body . 'x', $secret, $t) === false); // body tampered
assert(kasera_pay_verify_signature("t=$t,v1=$sig", $body, 'whsec_other', $t) === false); // wrong secret

echo "signature-test: all assertions passed\n";
