<?php
// For shared hosting without environment variables.
// Copy to validator.config.php (one level above the web root, never commit it)
// and set a long random secret (32+ characters), e.g. from `openssl rand -hex 32`.
return array(
    'token' => 'replace-with-a-random-secret-of-at-least-32-characters',
);
