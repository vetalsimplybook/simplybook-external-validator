package com.simplybook.validator;

import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;

// SimplyBook.me cannot sign requests or send custom headers, so the only
// protection is a secret token in the URL (?token=...).
public final class TokenAuth {

    private static final int MIN_LENGTH = 32;

    private TokenAuth() {}

    /** Read VALIDATOR_TOKEN; refuse to start if it is missing or shorter than 32 characters. */
    public static String loadSecret() {
        String secret = System.getenv("VALIDATOR_TOKEN");
        if (secret == null || secret.length() < MIN_LENGTH) {
            throw new IllegalStateException(
                    "VALIDATOR_TOKEN must be set to a secret of at least 32 characters");
        }
        return secret;
    }

    /** Constant-time comparison (isEqual returns false on a length mismatch). */
    public static boolean matches(String secret, String token) {
        if (token == null) return false;
        return MessageDigest.isEqual(
                token.getBytes(StandardCharsets.UTF_8), secret.getBytes(StandardCharsets.UTF_8));
    }
}
