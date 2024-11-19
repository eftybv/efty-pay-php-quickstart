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
            ->setBuyer(
                (new \Eftypay\Users\User())
                    ->setFirstName("Patrick")
                    ->setLastName("Kosterman")
                    ->setUsername("patrick-kosterman-buyer-" . $randomString)
                    ->setEmail("patrick-kosterman-buyer-" . $randomString . "@efty.com")
                    ->setPassword("wasdfp-0wsdfe-mafdfrw")
                    ->setCompanyRegistrationNumber("87654321")
                    ->setCompanyName("Efty Pay B.V.")
                    ->setCompanyAddress(
                        (new \Eftypay\Common\Address())
                            ->setAddressLine1("Zuiderpark 17")
                            ->setPostalCode("9724 AG")
                            ->setCity("Groningen")
                            ->setCountry(\Eftypay\Common\CountryCode::NL)
                    )
                    ->setVatSettings((new \Eftypay\Common\VatSettings())->setHasVat(true))
                    ->setUserType(\Eftypay\Users\UserType::BUYER_OR_SELLER)
                    ->setStatus(\Eftypay\Users\UserStatus::ACTIVE)
                    ->setPhoneNumber("+34615504467")
                    ->setPaymentDetails(
                        (new \Eftypay\Payments\PaymentDetails())
                            ->setMangopayDetails(
                                (new \Eftypay\Payments\Mangopay\MangopayDetails())
                                    ->setOnboardAsSellerWithMangopay(false)
                                    ->setUserCategory(\Eftypay\Payments\Mangopay\UserCategory::PAYER)
                            )
                    )
            )
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

    // If the transaction is initiated by the Seller (for example, through an admin interface), set this flag to true, in this case the buyer will receive an email right away that a transaction was created for them.utmParameters
    // If the transaction is initiated by a Buy-It-Now type of button, then omit this.
    $transaction->setInitiatedBy(\Eftypay\Transactions\Activity\TransactionParty::SELLER);

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
    echo "Transaction checkout URL (forward the buyer to this): " . $newTransaction->getCheckoutDetails()->getCheckoutUrl() . "\r\n";
} catch (Exception $e) {
    echo $e;
}