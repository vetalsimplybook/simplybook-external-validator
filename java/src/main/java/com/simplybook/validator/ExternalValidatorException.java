package com.simplybook.validator;

public class ExternalValidatorException extends RuntimeException {

    private String fieldId;
    private String intakeFieldId;

    public ExternalValidatorException(String message) {
        super(message);
    }

    public String getFieldId() { return fieldId; }
    public void setFieldId(String fieldId) { this.fieldId = fieldId; }

    public String getIntakeFieldId() { return intakeFieldId; }
    public void setIntakeFieldId(String intakeFieldId) { this.intakeFieldId = intakeFieldId; }
}
