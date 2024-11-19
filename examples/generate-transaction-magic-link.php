<?php
include_once("../vendor/autoload.php");
include_once("helpers.php");

try {
    // Get the API URL.
    $apiUrl = getenv("EFTY_PAY_API_URL");

    // Set transactionId to the transaction ID you got from one of the create transaction samples.
    $transactionId = "5RNsr3i37SzvwOrrKCIRTo";

    // Set the options to pass to the connection clients (SSL & admin / integrator token in the authorization header).
    $opts = [
        "credentials" => \Grpc\ChannelCredentials::createSsl(),
        "update_metadata" => function($metaData){
           $token = generateToken();
           $metaData['authorization'] = [$token];
           return $metaData;
        },
    ];

    // Define transaction magic link request.
    $transactionMagicLinkRequest = (new \Eftypay\Transactions\TransactionMagicLinkPayload())
        ->setTransactionId($transactionId)
        ->setSendToSeller(false) // Set to true if you want the link to be emailed to the seller.
        ->setSendToBuyer(false); // Set to true if you want the link to be emailed to the buyer.

    // Define the client for the transactions API, and pass in the options.
    $client = new \Eftypay\Transactions\TransactionsClient($apiUrl, $opts);
    list($response, $status) = $client->SendTransactionMagicLinkEmail($transactionMagicLinkRequest)->wait();
    if ($status->code !== 0) {
        // If we get an error code back, then log the error.
        throw new Exception(sprintf('Error getting the magic link. Status Code: %s, Details: %s.', $status->code, $status->details));
    }

    echo "Seller link: " . $response->getSellerLink() . "\r\n";
    echo "Buyer link: " . $response->getBuyerLink();
} catch (Exception $e) {
    echo $e;
}
