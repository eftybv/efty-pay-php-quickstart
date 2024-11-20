# efty-pay-php-quickstart
The Efty Pay Quickstart guides &amp; examples for using the Efty Pay PHP SDK.

## Table of Contents

- [Efty Pay PHP Quickstart](#efty-pay-php-quickstart)
- [Requirements](#requirements)
- [Required Setup](#required-setup)
  - [Initial Setup & Token Creation](#initial-setup--token-creation)
  - [Token Creation](#token-creation)
  - [Set Your API Credentials in Environment Variables](#set-your-api-credentials-in-environment-variables)
  - [Important Notes](#important-notes)
- [Create & Onboard the Seller](#create--onboard-the-seller)
  - [Onboard a Seller](#onboard-a-seller)
  - [Full Examples](#full-examples)
- [Examples](#examples)
  - [Generate Magic Link](#generate-magic-link)
  - [Create Transaction (with Known Seller & Buyer)](#create-transaction-with-known-seller--buyer)
  - [Get a user](#get-a-user)
  - [List transactions](#list-transactions)
- [License](#license)

## Requirements
- PHP 7.0 or higher
- PECL
- Composer
- Efty Pay API access credentials; please contact [ask@efty.com](ask@efty.com) to obtain early access.

## Other resources
- [Efty Pay PHP SDK](https://github.com/eftybv/efty-pay-php-sdk)
- [Efty Pay API Resource Documentation](https://docs.eftypay.com)

## Required Setup

### Initial setup & token creation
Make sure gRPC is enabled for your local PHP setup. You can follow the [gRPC instructions in our Efty Pay PHP SDK repository](https://github.com/eftybv/efty-pay-php-sdk?tab=readme-ov-file#grpc) to enable this.

Next, checkout this repository, and install all dependencies by running the following in the repository root.
```
composer install
```

#### Token creation
In order to authenticate with Efty Pay, a JWT token needs to be passed into the authorization header when making SDK request. The sample code for this can be found in [examples/helpers.php](examples/helpers.php)

The code relies on the [firebase/php-jwt](https://github.com/firebase/php-jwt) package to do the JWT token generation in PHP. Please make sure to include this library into your `composer.json` if you want to use the [examples/helpers.php](examples/helpers.php) code:
```
"require": {
  "eftybv/efty-pay-php-sdk": "v1.0.5",
  "firebase/php-jwt": "dev-main"
}
```

#### Set your API credentials in your environment variables
You can run all the individual samples in the [examples](examples) folder, by simply adding your API credentials to your environment:

```
export EFTY_PAY_API_KEY={{YOUR_API_KEY}}
export EFTY_PAY_API_SECRET={{YOUR_API_SECRET}}
export EFTY_PAY_INTEGRATOR_ID={{YOUR_INTEGRATOR_ID}}
export EFTY_PAY_API_URL=api.eftypay.com:443
```

Now cd into the `examples` folder and execute the desired scripts:
```
cd examples
php create-legal-seller-user-and-intiate-onboarding-with-mangopay.php
php create-transaction-new-seller-and-buyer.php
...
```

#### Important notes
 - __It's strongly recommended that you store your API credentials server side only, and if possible encrypted. Never expose or store these credentials in your code repository or front-end code.__
 - __If you think your API credentials have been leaked or compromised, please contact us immediately to invalidate your credentials.__

### Create & onboard the seller
In order for Efty Pay to process payments for a seller, and for a seller to receive payouts through Efty Pay, the seller needs to onboard with Efty Pay and go through our KYC process. 
This whole process is managed through the Efty Pay web interface, but does need to be initiated by the integrator by providing Efty Pay with basic data for the seller:

  - Legal status: personal or company seller.
  - Name & address details.

Once you have completed this step, the seller will receive an email with a link to complete their onboarding.

If you want to redirect the seller to the onboarding wizard from your application, you can generate a magic link for them to redirect them to.

The magic link implements the Efty Pay OTP (One Time Password) process for secure access by the seller.

#### Onboard a seller 
The below sample onboards a seller as legal user (company).
```php
<?php
include_once("../vendor/autoload.php");
include_once("helpers.php");

use Google\Protobuf\Timestamp;

try {
    // Get the API URL.
    $apiUrl = getenv("EFTY_PAY_API_URL");

    // Random string to be used in the username & email.
    $randomString = generateRandomString(5);

    // Set the options to pass to the connection clients (SSL & admin / integrator token in the authorization header).
    $opts = [
        "credentials" => \Grpc\ChannelCredentials::createSsl(),
        "update_metadata" => function ($metaData) {
            $token = generateToken();
            $metaData['authorization'] = [$token];
            return $metaData;
        },
    ];

    // Define the clients for the users & payments API.
    $usersClient = new \Eftypay\Users\UsersClient($apiUrl, $opts);
    $paymentsClient = new \Eftypay\Payments\PaymentsClient($apiUrl, $opts);

    // Define the seller with nested objects.
    $seller = (new \Eftypay\Users\User())
        ->setFirstName("Patrick")
        ->setLastName("Kosterman")
        ->setUsername("patrick-kosterman-seller-" . $randomString)
        ->setEmail("patrick-kosterman-seller-" . $randomString. "@efty.com")
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
        ->setVatSettings(
            (new \Eftypay\Common\VatSettings())->setHasVat(true)
        )
        ->setUserType(\Eftypay\Users\UserType::BUYER_OR_SELLER)
        ->setStatus(\Eftypay\Users\UserStatus::ACTIVE)
        ->setPhoneNumber("+34615504467")
        ->setPaymentDetails(
            (new \Eftypay\Payments\PaymentDetails())->setMangopayDetails(
                (new \Eftypay\Payments\Mangopay\MangopayDetails())
                    ->setOnboardAsSellerWithMangopay(true)
                    ->setUserCategory(\Eftypay\Payments\Mangopay\UserCategory::OWNER)
            )
        );

    // Create the user request.
    $userRequest = (new \Eftypay\Users\UserRequest())->setUser($seller);

    // Create the user.
    list($userResponse, $status) = $usersClient->CreateUser($userRequest)->wait();
    if ($status->code !== 0) {
        // If we get an error code back, then log the error.
        throw new Exception(sprintf(
            'Error creating the user. Status Code: %s, Details: %s.',
            $status->code,
            $status->details
        ));
    }

    // No error, print the new user ID.
    echo "New seller user created: " . $userResponse->getId() . "\r\n";

    $dateOfBirth = new \DateTime('1982-03-21');
    $timestamp = new Timestamp();
    $protobufTimestamp = $timestamp->fromDateTime($dateOfBirth);

    // Create the onboarding object. In this case we want to onboard the seller as a legal user, as it's a company.
    $mangopayOnboardingRequest = (new \Eftypay\Payments\Mangopay\MangopayOnboarding())
        ->setSellerUserId(
            (new \Eftypay\Common\Id())
                ->setId($userResponse->getId()) // Assuming $seller has an ID set.
        )
        ->setLegalUser(
            (new \Eftypay\Payments\Mangopay\LegalUser())
                ->setRegisteredName($userResponse->getCompanyName())
                ->setCompanyNumber($userResponse->getCompanyRegistrationNumber())
                ->setEmail($userResponse->getEmail())
                ->setRegisteredAddress(
                    (new \Eftypay\Payments\Mangopay\Address())
                        ->setAddressLine1($userResponse->getCompanyAddress()->getAddressLine1())
                        ->setAddressLine2($userResponse->getCompanyAddress()->getAddressLine2())
                        ->setCity($userResponse->getCompanyAddress()->getCity())
                        ->setRegionOrCounty($userResponse->getCompanyAddress()->getStateOrCounty())
                        ->setPostalCode($userResponse->getCompanyAddress()->getPostalCode())
                        ->setCountry($userResponse->getCompanyAddress()->getCountry())
                )
                ->setLegalRepresentative(
                    (new \Eftypay\Payments\Mangopay\Person())
                        ->setFirstName($userResponse->getFirstName())
                        ->setLastName($userResponse->getLastName())
                        ->setEmail($userResponse->getEmail())
                        ->setDateOfBirth($protobufTimestamp)
                        ->setNationality(\Eftypay\Common\CountryCode::NL)
                        ->setCountryOfResidence(\Eftypay\Common\CountryCode::NL)
                )
                ->setLegalUserType(\Eftypay\Payments\Mangopay\LegalUserType::BUSINESS)
                ->setVatNumber("abc123")
        )
        ->setOnboardingType(\Eftypay\Payments\Mangopay\OnboardingType::LEGAL_USER);

        // Onboard the user; the user will receive an email with a link to complete their onboarding in the Efty Pay environment.
        list($onboardingResponse, $status) = $paymentsClient->CreateSellerOnboardingForMangopay($mangopayOnboardingRequest)->wait();
        if ($status->code !== 0) {
            // If we get an error code back, then log the error.
            throw new Exception(sprintf(
                'Error onboarding the user. Status Code: %s, Details: %s.',
                $status->code,
                $status->details
            ));
        }

        // No error, print the new user ID.
        echo "New seller onboarding successfully initiated for user: " . $onboardingResponse->getSellerUserId()->getId() . "\r\n";
} catch (Exception $e) {
    echo $e;
}
```

Full examples:
- Onboard natural-person seller (individual): [examples/create-individual-seller-user-and-intiate-onboarding-with-mangopay](./examples/create-individual-seller-user-and-intiate-onboarding-with-mangopay.php).
- Onboard legal-user seller (company): [examples/create-legal-seller-user-and-intiate-onboarding-with-mangopay.php](./examples/create-legal-seller-user-and-intiate-onboarding-with-mangopay.php).

## Other examples

### Generate magic link 
The `GenerateMagicLink` method can be used to generate a magic link for the user to their Efty Pay environment. Here they can see their onboarding & transaction information.

The magic links use the Efty Pay OTP process & technology for secure access.

```php
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
```

Full examples:
- Generate generic magic link: [examples/generate-generic-magic-link.php](./examples/generate-generic-magic-link.php).
- Generate magic link for specific transaction: [examples/generate-transaction-magic-link.php](./examples/generate-transaction-magic-link.php).

### Create transaction (with known seller & buyer)
This is the most straightforward and easy way to create a transaction (including the buyer user).

Notes: 
- When creating a transaction, Efty Pay will check if the user already exists by email and/or username; these values are unique within the integrator data-space in Efty Pay. 
- If a user already exists, creating a transaction will throw an error, instead you should pass in the user ID of the existing user. 
- To get the user details (including the ID) for an email or username, you can use the [Get User method](examples/get-user.php).

```php
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
```

Full examples:
- Create transaction (with known buyer): [examples/create-transaction-known-buyer.php](./examples/create-transaction-known-buyer.php).
- Create transaction (with no buyer): [examples/create-transaction-no-buyer.php](./examples/create-transaction-no-buyer.php).

### Get a user
You can get a user by ID, email, or username. Below sample shows getting the user by ID and email.

```php
<?php
include_once("../vendor/autoload.php");
include_once("helpers.php");

try {
    // Get the API URL.
    $apiUrl = getenv("EFTY_PAY_API_URL");

    // Set userId to the user ID you got from one of the create user samples.
    $userId = "7fsVVlCswVgFNG7miKaOjZ";

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
    $usersClient = new \Eftypay\Users\UsersClient($apiUrl, $opts);

    // Define the user ID.
    $userIdRequest = (new \Eftypay\Common\Id())
        ->setId($userId);

    // Get the user by ID.
    list($user, $status) = $usersClient->GetUserById($userIdRequest)->wait();
    if ($status->code !== 0) {
        // If we get an error code back, then log the error.
        throw new Exception(sprintf('Error getting the user. Status Code: %s, Details: %s.', $status->code, $status->details));
    }

    // No error, print the user details.
    echo "User details: \r\n";
    echo "Id: " . $user->getId() . "\r\n";
    echo "Username: " . $user->getUsername() . "\r\n";
    echo "Email: " . $user->getEmail() . "\r\n";

    // Get the user by email address.
    $userEmailRequest = (new \Eftypay\Common\GetObjectRequest())
        ->setFieldValue($user->getEmail());

    // Get the user by email.
    list($user, $status) = $usersClient->GetUserByEmail($userEmailRequest)->wait();
    if ($status->code !== 0) {
        // If we get an error code back, then log the error.
        throw new Exception(sprintf('Error getting the user. Status Code: %s, Details: %s.', $status->code, $status->details));
    }

    // No error, print the user details.
    echo "User details: \r\n";
    echo "Id: " . $user->getId() . "\r\n";
    echo "Username: " . $user->getUsername() . "\r\n";
    echo "Email: " . $user->getEmail();
} catch (Exception $e) {
    echo $e;
}
```

Full examples:
- Get user: [examples/get-user.php](./examples/get-user.php).

### List transactions
You can list all transactions in your account, or you can list transactions with filter criteria. The below sample lists all transactions for a seller.

```php
<?php
include_once("../vendor/autoload.php");
include_once("helpers.php");

try {
    // Get the API URL.
    $apiUrl = getenv("EFTY_PAY_API_URL");

    // Set userId to the user ID you got from one of the create user samples.
    $userId = "7fsVVlCswVgFNG7miKaOjZ";

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
    $usersClient = new \Eftypay\Users\UsersClient($apiUrl, $opts);

    // Define the user ID.
    $userIdRequest = (new \Eftypay\Common\Id())
        ->setId($userId);

    // Get the user by ID.
    list($user, $status) = $usersClient->GetUserById($userIdRequest)->wait();
    if ($status->code !== 0) {
        // If we get an error code back, then log the error.
        throw new Exception(sprintf('Error getting the user. Status Code: %s, Details: %s.', $status->code, $status->details));
    }

    // No error, print the user details.
    echo "User details: \r\n";
    echo "Id: " . $user->getId() . "\r\n";
    echo "Username: " . $user->getUsername() . "\r\n";
    echo "Email: " . $user->getEmail() . "\r\n";

    // Get the user by email address.
    $userEmailRequest = (new \Eftypay\Common\GetObjectRequest())
        ->setFieldValue($user->getEmail());

    // Get the user by email.
    list($user, $status) = $usersClient->GetUserByEmail($userEmailRequest)->wait();
    if ($status->code !== 0) {
        // If we get an error code back, then log the error.
        throw new Exception(sprintf('Error getting the user. Status Code: %s, Details: %s.', $status->code, $status->details));
    }

    // No error, print the user details.
    echo "User details: \r\n";
    echo "Id: " . $user->getId() . "\r\n";
    echo "Username: " . $user->getUsername() . "\r\n";
    echo "Email: " . $user->getEmail();
} catch (Exception $e) {
    echo $e;
}
```

Full examples:
- List transactions for seller: [examples/list-transactions-for-seller.php](./examples/list-transactions-for-seller.php).
- List transactions for buyer: [examples/list-transactions-for-buyer.php](./examples/list-transactions-for-buyer.php).

## License
This project is licensed under the MIT License. See the LICENSE file for details.