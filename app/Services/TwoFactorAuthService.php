<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use InvalidArgumentException;

class TwoFactorAuthService
{
    private const BASE32_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a cryptographically secure random Base32 secret key.
     */
    public function generateSecretKey(int $length = 32): string
    {
        $secret = '';
        $charLength = strlen(self::BASE32_CHARS);
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::BASE32_CHARS[random_int(0, $charLength - 1)];
        }
        return $secret;
    }

    /**
     * Decode a Base32 string to a raw binary string.
     */
    public function base32Decode(string $base32): string
    {
        $base32 = strtoupper(trim($base32));
        $buffer = 0;
        $bitsLeft = 0;
        $binary = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            if ($char === '=' || $char === ' ' || $char === '-') {
                continue;
            }
            $val = strpos(self::BASE32_CHARS, $char);
            if ($val === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $binary;
    }

    /**
     * Calculate 6-digit TOTP code for a secret at a given timestamp (RFC 6238 / RFC 4226).
     */
    public function calculateCode(string $secret, ?int $timestamp = null, int $digits = 6, int $period = 30): string
    {
        $timestamp = $timestamp ?? time();
        $timeSlice = (int) floor($timestamp / $period);

        $binaryKey = $this->base32Decode($secret);

        // Pack timeSlice into 8-byte big-endian binary counter
        $binaryTime = pack('N*', 0) . pack('N*', $timeSlice);

        // HMAC-SHA1
        $hash = hash_hmac('sha1', $binaryTime, $binaryKey, true);

        // Dynamic truncation
        $offset = ord(substr($hash, -1)) & 0x0F;
        $unpacked = unpack('N', substr($hash, $offset, 4));
        $truncatedHash = $unpacked[1] & 0x7FFFFFFF;

        $pin = $truncatedHash % (10 ** $digits);

        return str_pad((string) $pin, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a 6-digit TOTP code against a secret with clock drift tolerance.
     */
    public function verifyCode(string $secret, string $code, int $window = 1, ?int $timestamp = null): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        $timestamp = $timestamp ?? time();
        $period = 30;

        for ($i = -$window; $i <= $window; $i++) {
            $testTime = $timestamp + ($i * $period);
            $validCode = $this->calculateCode($secret, $testTime);
            if (hash_equals($validCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Generate 8 secure, single-use recovery codes.
     */
    public function generateRecoveryCodes(int $count = 8): array
    {
        $codes = [];
        for ($i = 0; $i < $count; $i++) {
            $codes[] = sprintf('%04X-%04X', random_int(0, 0xFFFF), random_int(0, 0xFFFF));
        }
        return $codes;
    }

    /**
     * Generate standard otpauth:// URL for authenticator apps.
     */
    public function getOtpAuthUrl(string $issuer, string $accountName, string $secret): string
    {
        $issuerEncoded = rawurlencode($issuer);
        $accountEncoded = rawurlencode($accountName);
        return "otpauth://totp/{$issuerEncoded}:{$accountEncoded}?secret={$secret}&issuer={$issuerEncoded}&algorithm=SHA1&digits=6&period=30";
    }

    /**
     * Confirm enrollment and enable 2FA for user.
     * Returns array of recovery codes.
     */
    public function confirmTwoFactor(User $user, string $secret, string $code): array
    {
        if (!$this->verifyCode($secret, $code)) {
            throw new InvalidArgumentException('The provided authentication code is invalid or has expired.');
        }

        $recoveryCodes = $this->generateRecoveryCodes();

        $user->two_factor_secret = $secret;
        $user->two_factor_recovery_codes = $recoveryCodes;
        $user->two_factor_confirmed_at = now();
        $user->save();

        AuditLog::log('two_factor_enabled', $user, null, [
            'method' => 'totp_rfc6238',
            'confirmed_at' => now()->toIso8601String(),
        ]);

        return $recoveryCodes;
    }

    /**
     * Disable 2FA for a user.
     */
    public function disableTwoFactor(User $user): void
    {
        $user->two_factor_secret = null;
        $user->two_factor_recovery_codes = null;
        $user->two_factor_confirmed_at = null;
        $user->save();

        AuditLog::log('two_factor_disabled', $user, null, [
            'disabled_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * Regenerate new recovery codes for an already enrolled user.
     */
    public function regenerateRecoveryCodes(User $user): array
    {
        if (!$user->hasTwoFactorEnabled()) {
            throw new InvalidArgumentException('Two-Factor Authentication is not enabled for this user.');
        }

        $recoveryCodes = $this->generateRecoveryCodes();
        $user->two_factor_recovery_codes = $recoveryCodes;
        $user->save();

        AuditLog::log('two_factor_recovery_codes_regenerated', $user, null, [
            'count' => count($recoveryCodes),
        ]);

        return $recoveryCodes;
    }

    /**
     * Verify and single-use consume a recovery code for a user.
     */
    public function verifyAndConsumeRecoveryCode(User $user, string $code): bool
    {
        $code = strtoupper(trim($code));
        $recoveryCodes = $user->two_factor_recovery_codes ?? [];

        if (!is_array($recoveryCodes) || empty($recoveryCodes)) {
            return false;
        }

        foreach ($recoveryCodes as $index => $storedCode) {
            if (hash_equals(strtoupper(trim($storedCode)), $code)) {
                // Permanently remove the consumed code (single-use guarantee)
                unset($recoveryCodes[$index]);
                $user->two_factor_recovery_codes = array_values($recoveryCodes);
                $user->save();

                AuditLog::log('two_factor_recovery_code_used', $user, null, [
                    'remaining_codes' => count($recoveryCodes),
                ]);

                return true;
            }
        }

        return false;
    }
}

