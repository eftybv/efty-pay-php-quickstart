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

    // Define the request object.
    $transactionListRequest = (new \Eftypay\Transactions\ListTransactionsRequest())
        ->setReturnBuyerData(true)
        ->setReturnSellerData(true)
        ->setListRequest(
            (new \Eftypay\Common\ListRequest())
                ->setLimit(25)
                ->setQueries([
                    (new \Eftypay\Common\Query())
                        ->setConditionOperator(\Eftypay\Common\ConditionOperator::CONDITION_OPERATOR_AND)
                        ->setFieldConditions([
                            (new \Eftypay\Common\Condition())
                                ->setField('SellerId')
                                ->setOperator(\Eftypay\Common\ComparisonOperator::COMPARISON_OPERATOR_EQUALS)
                                ->setStringValue($sellerId)
                        ])
                    ])
        );


    // Define the client for the transactions API, and pass in the options.
    $client = new \Eftypay\Transactions\TransactionsClient($apiUrl, $opts);
    $call = $client->ListTransactions($transactionListRequest);
    $transactions = $call->responses();
    foreach ($transactions as $transaction) {
        echo $transaction->getId() . "\n";
    }
} catch (Exception $e) {
    echo $e;
}