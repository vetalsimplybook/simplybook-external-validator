<?php

include_once 'ExternalValidatorException.php';
include_once 'ExternalValidator.php';

header('Content-Type: application/json');

// Read and decode the request body sent by SimplyBook.me
$rawInput = file_get_contents('php://input');
$incomingData = $rawInput ? json_decode($rawInput, true) : null;

/*
// Uncomment for local testing (no HTTP request needed):
$incomingData = json_decode('{
    "service_id": 9,
    "provider_id": 45,
    "client_id": 8123,
    "start_datetime": "2021-01-11 11:40:00",
    "end_datetime": "2021-01-11 11:45:00",
    "count": 1,
    "company_login": "mycompany",
    "sheduler_id": null,
    "additional_fields": [
        {
            "id": "ed8f5b7380f7111c592abf6f916fc2d0",
            "name": "Check number",
            "value": "112233445566"
        },
        {
            "id": "68700bfe1ba3d59441c9b14d4f94938b",
            "name": "Some string",
            "value": "simplybook"
        },
        {
            "id": "ac4c3775f20dcfdea531346ee5bc8ea4",
            "name": "Date of birth",
            "value": "1973-03-02"
        }
    ]
}', true);
*/

if (!$incomingData) {
    echo json_encode(array());
    exit;
}

$validator = new ExternalValidator();
$result = $validator->validate($incomingData);
echo json_encode($result);
