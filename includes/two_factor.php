<?php

/**
 * TOTP (RFC 6238) helpers using only PHP built-ins.
 * No passwords or TOTP codes are logged.
 */

function base32Encode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = 0;
    $value = 0;
    $output = '';

    for ($i = 0, $length = strlen($data); $i < $length; $i++) {
        $value = ($value << 8) | ord($data[$i]);
        $bits += 8;

        while ($bits >= 5) {
            $bits -= 5;
            $output .= $alphabet[($value >> $bits) & 31];
        }
    }

    if ($bits > 0) {
        $output .= $alphabet[($value << (5 - $bits)) & 31];
    }

    return $output;
}

function base32Decode(string $data): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $data = strtoupper($data);
    $data = preg_replace('/[^A-Z2-7]/', '', $data) ?? '';

    $bits = 0;
    $value = 0;
    $output = '';

    for ($i = 0, $length = strlen($data); $i < $length; $i++) {
        $position = strpos($alphabet, $data[$i]);
        if ($position === false) {
            continue;
        }

        $value = ($value << 5) | $position;
        $bits += 5;

        if ($bits >= 8) {
            $bits -= 8;
            $output .= chr(($value >> $bits) & 255);
        }
    }

    return $output;
}

function generateTotpSecret(): string
{
    return base32Encode(random_bytes(20));
}

function getTotpCode(string $secret, ?int $timestamp = null): string
{
    $timestamp = $timestamp ?? time();
    $counter = intdiv($timestamp, 30);
    $binaryCounter = pack('N2', ($counter >> 32) & 0xFFFFFFFF, $counter & 0xFFFFFFFF);
    $hash = hash_hmac('sha1', $binaryCounter, base32Decode($secret), true);
    $offset = ord($hash[19]) & 0x0F;

    $binaryCode = ((ord($hash[$offset]) & 0x7F) << 24)
        | ((ord($hash[$offset + 1]) & 0xFF) << 16)
        | ((ord($hash[$offset + 2]) & 0xFF) << 8)
        | (ord($hash[$offset + 3]) & 0xFF);

    return str_pad((string) ($binaryCode % 1000000), 6, '0', STR_PAD_LEFT);
}

function verifyTotpCode(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';
    if (strlen($code) !== 6) {
        return false;
    }

    $now = $timestamp ?? time();

    for ($offset = -$window; $offset <= $window; $offset++) {
        $expected = getTotpCode($secret, $now + ($offset * 30));
        if (hash_equals($expected, $code)) {
            return true;
        }
    }

    return false;
}

function buildOtpAuthUri(string $secret, string $username, string $issuer = 'Credential Manager'): string
{
    $label = rawurlencode($issuer . ':' . $username);

    return 'otpauth://totp/' . $label
        . '?secret=' . rawurlencode($secret)
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}
