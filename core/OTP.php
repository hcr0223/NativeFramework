<?php

namespace Core;

class OTP {
    /**
     * Genera una clave secreta aleatoria en Base32 para asociar al usuario.
     * Esta clave es la única que debe guardarse de forma segura en la base de datos del usuario.
     */
    public static function generateSecret(int $length = 16): string {
        $allowedChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';
        for ($i = $length; $i > 0; $i--) {
            $secret .= $allowedChars[random_int(0, strlen($allowedChars) - 1)];
        }
        return $secret;
    }

    /**
     * Genera un código OTP basado en el tiempo actual permitiendo elegir el algoritmo de hash.
     * 
     * @param string $secret Clave secreta en Base32
     * @param int $timeStep Caducidad en segundos (ej. 60)
     * @param string $algorithm Algoritmo de hash ('sha1', 'sha256', 'sha512')
     * @param int|null $timestamp Marca de tiempo opcional
     */
    public static function generate(string $secret, int $timeStep = 60, string $algorithm = 'sha1', ?int $timestamp = null): string {
        $timestamp = $timestamp ?? time();
        $timeSlice = floor($timestamp / $timeStep);
        
        $binaryTime = pack('N*', 0) . pack('N*', $timeSlice);
        $binarySecret = self::base32Decode($secret);
        
        // Validar y usar el algoritmo de hash especificado (sha1, sha256, sha512)
        $algorithm = strtolower($algorithm);
        if (!in_array($algorithm, ['sha1', 'sha256', 'sha512'])) {
            $algorithm = 'sha1';
        }

        $hash = hash_hmac($algorithm, $binaryTime, $binarySecret, true);
        $offset = ord(substr($hash, -1)) & 0x0F;
        
        $code = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        ) % 1000000;

        return str_pad((string)$code, 6, '0', STR_PAD_LEFT);
    }

    /**
     * Verifica si un código OTP proporcionado es válido.
     */
    public static function verify(string $secret, string $code, int $timeStep = 60, string $algorithm = 'sha1', int $window = 1): bool {
        if (strlen($code) !== 6 || !is_numeric($code)) {
            return false;
        }

        $currentTime = time();

        for ($i = -$window; $i <= $window; $i++) {
            $calculatedCode = self::generate($secret, $timeStep, $algorithm, $currentTime + ($i * $timeStep));
            
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Decodifica una cadena Base32 a binario para el cálculo del HMAC.
     */
    private static function base32Decode(string $secret): string {
        $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = strtoupper($secret);
        $buffer = 0;
        $bitsLeft = 0;
        $output = '';

        for ($i = 0, $length = strlen($secret); $i < $length; $i++) {
            $char = $secret[$i];
            if ($char === '=') {
                break;
            }
            $val = strpos($base32Chars, $char);
            if ($val === false) {
                continue;
            }
            $buffer = ($buffer << 5) | $val;
            $bitsLeft += 5;
            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $output .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }
        return $output;
    }

    /**
     * Genera la URL de aprovisionamiento (URI otpauth://) para sincronizar con Google Authenticator.
     * 
     * @param string $name Nombre de la cuenta o correo del usuario (ej. usuario@domain.com)
     * @param string $secret Clave secreta en Base32
     * @param string $issuer Nombre de tu aplicación o empresa
     * @param string $algorithm Algoritmo a utilizar ('sha1', 'sha256', 'sha512')
     * @param int $timeStep Intervalo de tiempo en segundos (por defecto 60)
     */
    public static function getProvisioningUri(string $name, string $secret, string $issuer = 'App', string $algorithm = 'sha1', int $timeStep = 60): string {
        $params = [
            'secret' => $secret,
            'issuer' => $issuer,
            'algorithm' => strtoupper($algorithm),
            'period' => $timeStep,
            'digits' => 6
        ];

        return sprintf(
            'otpauth://totp/%s:%s?%s',
            rawurlencode($issuer),
            rawurlencode($name),
            http_build_query($params, '', '&', PHP_QUERY_RFC3986)
        );
    }
}