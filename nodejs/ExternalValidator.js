import fs from 'fs';

class ExternalValidatorException extends Error {
    constructor(message, code) {
        super(message);
        this.code = code;
        this._fieldId = null;
        this._intakeFieldId = null;
    }
    setFieldId(id) { this._fieldId = id; }
    setIntakeFieldId(id) { this._intakeFieldId = id; }
    getFieldId() { return this._fieldId; }
    getIntakeFieldId() { return this._intakeFieldId; }
}

export class ExternalValidator {

    constructor() {
        this._errors = {
            1: 'Invalid service is selected. Please select another service to continue booking.',
            2: 'Intake Forms are missing for this service.',
            3: '"Check number" field is missing.',
            4: '"Check number" field is incorrect.',
            5: '"Date of birth" field is missing.',
            6: 'Incorrect date of birth.',
        };

        /**
         * Maps logical field keys to the Intake Forms field IDs.
         *
         * IMPORTANT: The 'id' sent by SimplyBook.me for each field in 'additional_fields'
         * is an MD5 hash generated when the field was created.
         * It never changes, so matching by 'id' is reliable.
         */
        this._fieldsNameMap = {
            checkNumber: 'ed8f5b7380f7111c592abf6f916fc2d0',
            checkString: '68700bfe1ba3d59441c9b14d4f94938b',
            dateOfBirth: 'ac4c3775f20dcfdea531346ee5bc8ea4',
        };

        /** Path to the log file. Set to null to disable logging. */
        this._logFile = '/tmp/external_validator.log';
    }

    validate(bookingData) {
        try {
            const timeStart = Date.now();
            this._log(bookingData);

            if (!bookingData.service_id || bookingData.service_id !== 9) {
                this._error(1, 'service_id');
            }

            if (!bookingData.additional_fields) {
                this._error(2);
            }

            const additionalFields = bookingData.additional_fields;

            const checkNumberField = this._findField('checkNumber', additionalFields, this._fieldsNameMap);
            if (!checkNumberField) {
                this._error(3);
            } else if (checkNumberField.value !== '112233445566') {
                this._error(4, null, checkNumberField.id);
            }

            const dateOfBirthField = this._findField('dateOfBirth', additionalFields, this._fieldsNameMap);
            if (!dateOfBirthField) {
                this._error(5);
            } else if (!this._isBirthdayValid(dateOfBirthField.value)) {
                this._error(6, null, dateOfBirthField.id);
            }

            const result = { checkString: 'replaced text' };
            this._log(result);
            const intakeFieldsResult = this._createFieldResult(result, additionalFields, this._fieldsNameMap);
            this._log(intakeFieldsResult);

            const executionTime = (Date.now() - timeStart) / 1000;
            this._log(`Total Execution Time: ${executionTime} sec`);

            if (intakeFieldsResult.length > 0) {
                return { additional_fields: intakeFieldsResult };
            }
            return {};

        } catch (e) {
            if (e instanceof ExternalValidatorException) {
                return this._sendError(e);
            }
            const result = { errors: [e.message] };
            this._log(result);
            return result;
        }
    }

    _isBirthdayValid(date) {
        if (!date) return false;
        const d = new Date(date);
        if (isNaN(d.getTime())) return false;
        if (d > new Date()) return false;
        if (new Date().getFullYear() - d.getFullYear() > 140) return false;
        return true;
    }

    _findField(fieldKey, addFields, map, mapType = 'id') {
        if (!map[fieldKey]) return null;
        const searchValue = map[fieldKey];
        for (const field of addFields) {
            if (mapType === 'id') {
                if (field.id === searchValue) return field;
            } else {
                if ((field.name || '').toLowerCase().trim() === searchValue.toLowerCase().trim()) return field;
            }
        }
        return null;
    }

    _sendError(e) {
        let result;
        if (e.getFieldId()) {
            result = [{ id: e.getFieldId(), errors: [e.message] }];
        } else if (e.getIntakeFieldId()) {
            result = { additional_fields: [{ id: e.getIntakeFieldId(), errors: [e.message] }] };
        } else {
            result = { errors: [e.message] };
        }
        this._log(result);
        return result;
    }

    _createFieldResult(resultArr, addFields, map) {
        const result = [];
        for (const [key, value] of Object.entries(resultArr)) {
            if (!value) continue;
            const strValue = Array.isArray(value) ? value.join('; ') : value;
            const field = this._findField(key, addFields, map);
            if (field) {
                result.push({ ...field, value: strValue });
            }
        }
        return result;
    }

    _error(code, fieldId = null, intakeFieldId = null) {
        const message = this._errors[code] || '';
        const error = new ExternalValidatorException(message, code);
        if (fieldId) error.setFieldId(fieldId);
        if (intakeFieldId) error.setIntakeFieldId(intakeFieldId);
        throw error;
    }

    _log(var_) {
        if (!this._logFile) return;
        const entry = '\n\n--------------------------------\n'
            + new Date().toLocaleString() + '\n\n'
            + (typeof var_ === 'string' ? var_ : JSON.stringify(var_, null, 2))
            + '\n--------------------------------\n';
        fs.appendFileSync(this._logFile, entry);
    }
}
