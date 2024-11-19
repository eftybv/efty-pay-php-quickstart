<?php
include_once("../vendor/autoload.php");
include_once("helpers.php");

try {
    // Get the API URL.
    $apiUrl = getenv("EFTY_PAY_API_URL");

    // Set sellerId to the transaction ID you got from one of the create transaction samples.
    $sellerId = "7fsVVlCswVgFNG7miKaOjZ";

    // Set the options to pass to the connection clients (SSL & admin / integrator token in the authorization header).
    $opts = [
        "credentials" => \Grpc\ChannelCredentials::createSsl(),
        "update_metadata" => function($metaData){
           $token = generateToken();
           $metaData['authorization'] = [$token];
           return $metaData;
        },
    ];

    $sellerId = (new \Eftypay\Common\Id())
        ->setId($sellerId);

    // Define the client for the payments API, and pass in the options.
    $client = new \Eftypay\Payments\PaymentsClient($apiUrl, $opts);
    list($response, $status) = $client->GetGenericMagicLink($sellerId)->wait();
    if ($status->code !== 0) {
        // If we get an error code back, then log the error.
        throw new Exception(sprintf('Error getting the magic link. Status Code: %s, Details: %s.', $status->code, $status->details));
    }

    echo "Magic link: " . $response->getValue() . "\r\n\r\n";
} catch (Exception $e) {
    echo $e;
}
