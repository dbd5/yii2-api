<?php

namespace tests\_support;

use yii\helpers\Json;
use Yii;

/**
 * Helper class to generate a HMAC signature
 */
final class HMAC
{
    const HKDF_ALGO = 'sha256';
    const AUTH_INFO = 'HMAC|AuthenticationKey';

    /**
     * Static method to generate the HMAC
     * @param string $uri
     * @param array $tokens
     * @param string $method
     * @param string $date
     * @param array $payload
     * @return string
     */
    public static function generate($uri, $tokens, $method, $date, $payload = [])
    {
        $accessToken = $tokens['access_token'];
        $ikm = \base64_decode($tokens['ikm']);

        if ($method === 'GET' || empty($payload)) {
            $payload = '';
        } else {
            $payload = JSON::encode($payload, JSON_NUMERIC_CHECK | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        }

        $salt = \random_bytes(32);
        $hkdf = Yii::$app->security->hkdf(
            self::HKDF_ALGO,
            $ikm,
            $salt,
            self::AUTH_INFO,
            0
        );

        $signature = hash('sha256', $payload) . "\n" .
                     $method . "+" . $uri . "\n" .
                     $date . "\n" .
                     \base64_encode($salt);
        
        Yii::info([
            'message' => sprintf('Signature String Derived By Test Suite: %s', $signature),
            'body' => $payload
        ], 'hmac-signature');
        return \base64_encode(\hash_hmac('sha256', $signature, \bin2hex($hkdf), true)) . ',' . \base64_encode($salt);
    }
}