package com.simplybook.validator;

import org.springframework.web.bind.annotation.*;
import java.util.*;

@RestController
public class ValidatorController {

    @PostMapping("/")
    public Object validate(@RequestBody(required = false) Map<String, Object> bookingData) {
        if (bookingData == null || bookingData.isEmpty()) {
            return Collections.emptyMap();
        }
        return new ExternalValidator().validate(bookingData);
    }
}
