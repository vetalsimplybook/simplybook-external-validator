package com.simplybook.validator;

import java.io.FileWriter;
import java.io.IOException;
import java.time.LocalDate;
import java.time.format.DateTimeParseException;
import java.util.*;

public class ExternalValidator {

    private static final int SERVICE_ERROR                      = 1;
    private static final int INTAKE_FORM_UNKNOWN                = 2;
    private static final int INTAKE_FORM_UNKNOWN_CHECK_NUMBER   = 3;
    private static final int INTAKE_FORM_INCORRECT_CHECK_NUMBER = 4;
    private static final int INTAKE_FORM_UNKNOWN_CHECK_DOB      = 5;
    private static final int INTAKE_FORM_INCORRECT_CHECK_DOB    = 6;

    private final Map<Integer, String> errors;

    /**
     * Maps logical field keys to the Intake Forms field IDs.
     *
     * IMPORTANT: The 'id' sent by SimplyBook.me for each field in 'additional_fields'
     * is an MD5 hash generated when the field was created.
     * It never changes, so matching by 'id' is reliable.
     */
    private final Map<String, String> fieldsNameMap;

    /** Path to the log file. Set to null to disable logging. */
    private String logFile = "/tmp/external_validator.log";

    public ExternalValidator() {
        errors = new HashMap<>();
        errors.put(SERVICE_ERROR,                      "Invalid service is selected. Please select another service to continue booking.");
        errors.put(INTAKE_FORM_UNKNOWN,                "Intake Forms are missing for this service.");
        errors.put(INTAKE_FORM_UNKNOWN_CHECK_NUMBER,   "\"Check number\" field is missing.");
        errors.put(INTAKE_FORM_INCORRECT_CHECK_NUMBER, "\"Check number\" field is incorrect.");
        errors.put(INTAKE_FORM_UNKNOWN_CHECK_DOB,      "\"Date of birth\" field is missing.");
        errors.put(INTAKE_FORM_INCORRECT_CHECK_DOB,    "Incorrect date of birth.");

        fieldsNameMap = new HashMap<>();
        fieldsNameMap.put("checkNumber", "ed8f5b7380f7111c592abf6f916fc2d0");
        fieldsNameMap.put("checkString", "68700bfe1ba3d59441c9b14d4f94938b");
        fieldsNameMap.put("dateOfBirth", "ac4c3775f20dcfdea531346ee5bc8ea4");
    }

    public Object validate(Map<String, Object> bookingData) {
        try {
            long timeStart = System.currentTimeMillis();
            log(bookingData);

            // Step 1: Check that the booking is for the right service.
            // SimplyBook.me sends the service_id of whatever the client booked.
            // Here we only handle service #9 — change this to your real service ID,
            // or remove the check entirely if you want to validate all services.
            int serviceId = bookingData.get("service_id") instanceof Number
                    ? ((Number) bookingData.get("service_id")).intValue() : -1;
            if (serviceId != 9) {
                throwError(SERVICE_ERROR, "service_id", null);
            }

            // Step 2: Make sure the booking includes intake form answers.
            // Intake forms are the extra fields clients fill in when booking
            // (e.g. "Check number", "Date of birth"). If they're missing entirely,
            // the booking can't be validated and we reject it immediately.
            if (!bookingData.containsKey("additional_fields")) {
                throwError(INTAKE_FORM_UNKNOWN, null, null);
            }

            @SuppressWarnings("unchecked")
            List<Map<String, Object>> additionalFields =
                    (List<Map<String, Object>>) bookingData.get("additional_fields");

            // Step 3: Find and validate the "Check number" field.
            // We look up the field by its ID (an MD5 hash that never changes, even
            // if you rename the field in SimplyBook.me). See fieldsNameMap above.
            // First we check the field exists, then that its value matches what we expect.
            // If the value is wrong, we return a field-level error — SimplyBook.me will
            // highlight that specific field in the booking form so the client can fix it.
            Map<String, Object> checkNumberField = findField("checkNumber", additionalFields, fieldsNameMap);
            if (checkNumberField == null) {
                throwError(INTAKE_FORM_UNKNOWN_CHECK_NUMBER, null, null);
            } else if (!"112233445566".equals(checkNumberField.get("value"))) {
                throwError(INTAKE_FORM_INCORRECT_CHECK_NUMBER, null, (String) checkNumberField.get("id"));
            }

            // Step 4: Find and validate the "Date of birth" field.
            // Same pattern as Step 3. We check the field exists, then validate
            // that the date is real (not in the future, not impossibly old).
            Map<String, Object> dobField = findField("dateOfBirth", additionalFields, fieldsNameMap);
            if (dobField == null) {
                throwError(INTAKE_FORM_UNKNOWN_CHECK_DOB, null, null);
            } else if (!isBirthdayValid((String) dobField.get("value"))) {
                throwError(INTAKE_FORM_INCORRECT_CHECK_DOB, null, (String) dobField.get("id"));
            }

            // Step 5: Optionally overwrite intake form values before saving.
            // Whatever you return here will REPLACE the client's original input
            // in SimplyBook.me. Useful for normalizing data (e.g. formatting a phone
            // number) or filling in fields automatically.
            // Return only the fields you want to change — the others stay as-is.
            Map<String, String> resultMap = new LinkedHashMap<>();
            resultMap.put("checkString", "replaced text");
            log(resultMap);

            List<Map<String, Object>> intakeFieldsResult =
                    createFieldResult(resultMap, additionalFields, fieldsNameMap);
            log(intakeFieldsResult);

            double executionTime = (System.currentTimeMillis() - timeStart) / 1000.0;
            log("Total Execution Time: " + executionTime + " sec");

            if (!intakeFieldsResult.isEmpty()) {
                Map<String, Object> response = new LinkedHashMap<>();
                response.put("additional_fields", intakeFieldsResult);
                return response;
            }
            return Collections.emptyMap();

        } catch (ExternalValidatorException e) {
            return sendError(e);
        } catch (Exception e) {
            Map<String, Object> errResult = new LinkedHashMap<>();
            errResult.put("errors", List.of(e.getMessage()));
            log(errResult);
            return errResult;
        }
    }

    private boolean isBirthdayValid(String dateStr) {
        if (dateStr == null || dateStr.isEmpty()) return false;
        try {
            LocalDate d = LocalDate.parse(dateStr);
            if (d.isAfter(LocalDate.now())) return false;
            if (LocalDate.now().getYear() - d.getYear() > 140) return false;
            return true;
        } catch (DateTimeParseException e) {
            return false;
        }
    }

    private Map<String, Object> findField(String fieldKey,
                                           List<Map<String, Object>> addFields,
                                           Map<String, String> map) {
        String searchValue = map.get(fieldKey);
        if (searchValue == null) return null;
        for (Map<String, Object> field : addFields) {
            if (searchValue.equals(field.get("id"))) return field;
        }
        return null;
    }

    private Object sendError(ExternalValidatorException e) {
        Object result;
        if (e.getFieldId() != null && !e.getFieldId().isEmpty()) {
            Map<String, Object> entry = new LinkedHashMap<>();
            entry.put("id", e.getFieldId());
            entry.put("errors", List.of(e.getMessage()));
            result = List.of(entry);
        } else if (e.getIntakeFieldId() != null && !e.getIntakeFieldId().isEmpty()) {
            Map<String, Object> entry = new LinkedHashMap<>();
            entry.put("id", e.getIntakeFieldId());
            entry.put("errors", List.of(e.getMessage()));
            Map<String, Object> response = new LinkedHashMap<>();
            response.put("additional_fields", List.of(entry));
            result = response;
        } else {
            Map<String, Object> response = new LinkedHashMap<>();
            response.put("errors", List.of(e.getMessage()));
            result = response;
        }
        log(result);
        return result;
    }

    private List<Map<String, Object>> createFieldResult(Map<String, String> resultArr,
                                                         List<Map<String, Object>> addFields,
                                                         Map<String, String> map) {
        List<Map<String, Object>> result = new ArrayList<>();
        for (Map.Entry<String, String> entry : resultArr.entrySet()) {
            if (entry.getValue() == null || entry.getValue().isEmpty()) continue;
            Map<String, Object> field = findField(entry.getKey(), addFields, map);
            if (field != null) {
                Map<String, Object> updated = new LinkedHashMap<>(field);
                updated.put("value", entry.getValue());
                result.add(updated);
            }
        }
        return result;
    }

    private void throwError(int code, String fieldId, String intakeFieldId) {
        String message = errors.getOrDefault(code, "");
        ExternalValidatorException ex = new ExternalValidatorException(message);
        if (fieldId != null) ex.setFieldId(fieldId);
        if (intakeFieldId != null) ex.setIntakeFieldId(intakeFieldId);
        throw ex;
    }

    private void log(Object var) {
        if (logFile == null) return;
        String content = (var instanceof String) ? (String) var : var.toString();
        String entry = "\n\n--------------------------------\n"
                + new java.util.Date() + "\n\n"
                + content + "\n--------------------------------\n";
        try (FileWriter fw = new FileWriter(logFile, true)) {
            fw.write(entry);
        } catch (IOException ignored) {}
    }
}
