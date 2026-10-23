package com.simplybook.validator;

import jakarta.servlet.http.HttpServletRequest;
import tools.jackson.core.type.TypeReference;
import tools.jackson.databind.json.JsonMapper;
import org.springframework.http.HttpHeaders;
import org.springframework.http.HttpStatus;
import org.springframework.http.MediaType;
import org.springframework.http.ResponseEntity;
import org.springframework.web.bind.annotation.*;
import java.util.*;

@RestController
public class ValidatorController {

    private final String secret = TokenAuth.loadSecret();
    private final JsonMapper mapper = JsonMapper.builder().build();

    // Every method is listed (OPTIONS included) so that 405 can be answered with a
    // JSON body. The body is read as a string and parsed only after the token check.
    @RequestMapping(value = "/", method = {RequestMethod.GET, RequestMethod.HEAD, RequestMethod.POST,
            RequestMethod.PUT, RequestMethod.PATCH, RequestMethod.DELETE, RequestMethod.OPTIONS})
    public ResponseEntity<Object> validate(
            HttpServletRequest request,
            @RequestParam(name = "token", required = false) String token,
            @RequestBody(required = false) String rawBody) {

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
        Map<String, Object> bookingData = parse(rawBody);
        if (bookingData == null || bookingData.isEmpty()) {
            return json(HttpStatus.OK, Collections.emptyMap());
        }
        return json(HttpStatus.OK, new ExternalValidator().validate(bookingData));
    }

    private Map<String, Object> parse(String rawBody) {
        if (rawBody == null || rawBody.isBlank()) return null;
        try {
            return mapper.readValue(rawBody, new TypeReference<Map<String, Object>>() {});
        } catch (RuntimeException e) {
            return null;
        }
    }

    private static ResponseEntity<Object> json(HttpStatus status, Object body) {
        return ResponseEntity.status(status).contentType(MediaType.APPLICATION_JSON).body(body);
    }
}
