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