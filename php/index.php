<?php

include_once __DIR__ . '/ExternalValidatorException.php';
include_once __DIR__ . '/ExternalValidator.php';

header('Content-Type: application/json');

/*
// Uncomment for local testing (no HTTP request needed).
// Run with: VALIDATOR_TOKEN=<at least 32 chars> php index.php
$_SERVER['REQUEST_METHOD'] = 'POST';
$_GET['token'] = getenv('VALIDATOR_TOKEN'); // the value you would put in ?token=
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

/**
 * Load the shared secret: VALIDATOR_TOKEN env var first, then a config file
 * outside the web root (for shared hosting without env vars). The file lives
 * one level above this directory by default (override with VALIDATOR_CONFIG_FILE)
 * and must return array('token' => '...'), see validator.config.example.php.
 *
 * @return string  The secret, or '' if it is missing or shorter than 32 chars
 */
function loadValidatorToken() {
    $secret = getenv('VALIDATOR_TOKEN');
    if ($secret === false || $secret === '') {
        $configFile = getenv('VALIDATOR_CONFIG_FILE');
        if ($configFile === false || $configFile === '') {
            $configFile = dirname(__DIR__) . '/validator.config.php';
        }
        $secret = '';
        if (is_file($configFile)) {
            $config = include $configFile;
            if (is_array($config) && isset($config['token']) && is_string($config['token'])) {
                $secret = $config['token'];
            }
        }
    }
    return strlen($secret) >= 32 ? $secret : '';
}

function respond($status, $body) {
    http_response_code($status);
    echo $body;
    exit;
}

// Only POST is accepted
if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    respond(405, '{}');
}

// SimplyBook.me cannot sign requests or send custom headers, so the only
// protection is a secret token in the URL (?token=...), compared in constant time.
// A missing/short secret rejects every request; an empty secret never matches.
$secret = loadValidatorToken();
$token = isset($_GET['token']) && is_string($_GET['token']) ? $_GET['token'] : '';
if ($secret === '' || $token === '' || !hash_equals($secret, $token)) {
    respond(403, '{}');
}

if (!isset($incomingData)) {
    // Read and decode the request body sent by SimplyBook.me
    $rawInput = file_get_contents('php://input');
    $incomingData = $rawInput ? json_decode($rawInput, true) : null;
}

if (!$incomingData) {
    respond(200, '{}');
}

$validator = new ExternalValidator();
$result = $validator->validate($incomingData);
// An empty PHP array would encode as [] — accept must be {}
echo json_encode($result ? $result : new stdClass());
