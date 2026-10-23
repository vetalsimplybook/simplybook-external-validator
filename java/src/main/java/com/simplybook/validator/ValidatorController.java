package com.simplybook.validator;

import jakarta.servlet.http.HttpServletRequest;
import org.springframework.http.HttpHeaders;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import java.util.*;

@RestController
public class ValidatorController {

    private final String secret = TokenAuth.loadSecret();

    // Mapped to every method so that 405 can be answered with a JSON body
    @RequestMapping("/")
    public ResponseEntity<Object> validate(
            HttpServletRequest request,
            @RequestParam(name = "token", required = false) String token,
            @RequestBody(required = false) Map<String, Object> bookingData) {

        // Only POST is accepted
        if (!"POST".equals(request.getMethod())) {
            return ResponseEntity.status(HttpStatus.METHOD_NOT_ALLOWED)
                    .header(HttpHeaders.ALLOW, "POST")
                    .contentType(MediaType.APPLICATION_JSON)
                    .body(Collections.emptyMap());
        }
        if (!TokenAuth.matches(secret, token)) {
            return json(HttpStatus.FORBIDDEN, Collections.emptyMap());
        }
        if (bookingData == null || bookingData.isEmpty()) {
            return json(HttpStatus.OK, Collections.emptyMap());
        }
        return json(HttpStatus.OK, new ExternalValidator().validate(bookingData));
    }

    private static ResponseEntity<Object> json(HttpStatus status, Object body) {
        return ResponseEntity.status(status).contentType(MediaType.APPLICATION_JSON).body(body);
    }
}
