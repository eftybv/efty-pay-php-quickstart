<?php
include_once("../vendor/autoload.php");
include_once("helpers.php");

try {
    // Get the API URL.
    $apiUrl = getenv("EFTY_PAY_API_URL");

    // Random string to be used in the username & email.
    $randomString = generateRandomString(5);

    // Set sellerUserId to the user ID you got from one of the create user samples.
    $sellerUserId = "7fsVVlCswVgFNG7miKaOjZ";

    // Set the options to pass to the connection clients (SSL & admin / integrator token in the authorization header).
    $opts = [
        "credentials" => \Grpc\ChannelCredentials::createSsl(),
        "update_metadata" => function($metaData){
           $token = generateToken();
           $metaData['authorization'] = [$token];
           return $metaData;
        },
    ];

    // Define the client for the users API, and pass in the options.
    $client = new \Eftypay\Transactions\TransactionsClient($apiUrl, $opts);

    // Define the transaction object.
    $transaction = (new \Eftypay\Transactions\Transaction())
            ->setSeller((new \Eftypay\Users\User())->setId($sellerUserId))
            ->setAssetType(\Eftypay\Transactions\AssetType::DOMAIN_NAME)
            ->setDigitalAsset(
                (new \Eftypay\Transactions\DigitalAsset())
                    ->setDomain((new \Eftypay\Domains\Domain())->setDomainName("efty" . $randomString . ".com"))
            )
            ->setUtmParameters(
                (new \Eftypay\Transactions\UtmParameters())
                    ->setUtmSource("utm-source")
                    ->setUtmCampaign("utm-campaign")
                    ->setUtmTerm("utm-term")
                    ->setUtmContent("utm-content")
                    ->setUtmMedium("utm-medium")
            )
            ->setCurrency(\Eftypay\Common\Currency::USD)
            ->setAssetAmountExcVat(100000); // Asset amount in cents excluding VAT. Efty Pay automatically calculates the VAT amounts based on the seller & buyer countries.

    // Create the transaction.
    $transactionRequest = new \Eftypay\Transactions\TransactionRequest();
    $transactionRequest->setTransaction($transaction);

    list($newTransaction, $status) = $client->CreateTransaction($transactionRequest)->wait();
    if ($status->code !== 0) {
        // If we get an error code back, then log the error.
        throw new Exception(sprintf('Error creating the transaction. Status Code: %s, Details: %s.', $status->code, $status->details));
    }

    // No error, print the new transaction ID & checkout URL.
    echo "New transaction created: " . $newTransaction->getId() . "\r\n";
    echo "Transaction checkout URL: " . $newTransaction->getCheckoutDetails()->getCheckoutUrl() . "\r\n";
} catch (Exception $e) {
    echo $e;
}