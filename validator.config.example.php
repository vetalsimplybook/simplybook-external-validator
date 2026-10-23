<?php
// For shared hosting without environment variables.
// Copy to validator.config.php (one level above the web root, never commit it)
// and set a long random secret (32+ characters), e.g. from `openssl rand -hex 32`.
// The placeholder below is deliberately shorter than 32 characters, so the
// validator rejects every request until you replace it.
return array(
    'token' => 'CHANGE-ME',
);
