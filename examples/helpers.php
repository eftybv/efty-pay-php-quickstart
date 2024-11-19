<?php
include_once("../vendor/autoload.php");

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

function generateToken()
{
    $apiKey = getenv("EFTY_PAY_API_KEY");
    $apiSecret = getenv("EFTY_PAY_API_SECRET");
    $integratorId = getenv("EFTY_PAY_INTEGRATOR_ID");

    $payload = [
        "iat" => time(),
        "sub" => $apiKey,
        "exp" => time() + 600,
        "type" => 2,
        "iid" => $integratorId
    ];

    $token = JWT::encode($payload, $apiSecret, 'HS256');

    return $token;
}

function generateRandomString($length = 5) {
    $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'; // Uppercase letters
    $charactersLength = strlen($characters);
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, $charactersLength - 1)];
    }
    return $randomString;
}