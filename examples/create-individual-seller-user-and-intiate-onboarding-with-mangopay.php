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
        ->setCompanyAddress(
            (new \Eftypay\Common\Address())
                ->setAddressLine1("Zuiderpark 17")
                ->setPostalCode("9724 AG")
                ->setCity("Groningen")
                ->setCountry(\Eftypay\Common\CountryCode::NL)
        )
        ->setVatSettings(
            (new \Eftypay\Common\VatSettings())->setHasVat(false)
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

    $dateOfBirth = new \DateTime('1982-04-12');
    $timestamp = new Timestamp();
    $protobufTimestamp = $timestamp->fromDateTime($dateOfBirth);

    // Create the onboarding object. In this case we want to onboard the seller as a natural user, as it's a company.
    $mangopayOnboardingRequest = (new \Eftypay\Payments\Mangopay\MangopayOnboarding())
        ->setSellerUserId(
            (new \Eftypay\Common\Id())
                ->setId($userResponse->getId()) // Assuming $seller has an ID set.
        )
        ->setNaturalUser(
            (new \Eftypay\Payments\Mangopay\Person())
                ->setFirstName($userResponse->getFirstName())
                ->setLastName($userResponse->getLastName())
                ->setEmail($userResponse->getEmail())
                ->setDateOfBirth($protobufTimestamp)
                ->setNationality(\Eftypay\Common\CountryCode::NL)
                ->setCountryOfResidence(\Eftypay\Common\CountryCode::NL)
        )
        ->setOnboardingType(\Eftypay\Payments\Mangopay\OnboardingType::NATURAL_USER);

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

    // If you want to redirect the user from your application to the Efty Pay onboarding environment. You can generate a magic link for the user.
    $sellerId = (new \Eftypay\Common\Id())
        ->setId($userResponse->getId());

    list($response, $status) = $paymentsClient->GetGenericMagicLink($sellerId)->wait();
    if ($status->code !== 0) {
        // If we get an error code back, then log the error.
        throw new Exception(sprintf('Error getting the magic link. Status Code: %s, Details: %s.', $status->code, $status->details));
    }

    echo "Magic link: " . $response->getValue() . "\r\n\r\n";
} catch (Exception $e) {
    echo $e;
}