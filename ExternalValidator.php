<?php

class ExternalValidator {

    const SERVICE_ERROR = 1;
    const INTAKE_FORM_UNKNOWN = 2;
    const INTAKE_FORM_UNKNOWN_CHECK_NUMBER = 3;
    const INTAKE_FORM_INCORRECT_CHECK_NUMBER = 4;
    const INTAKE_FORM_UNKNOWN_CHECK_DOB = 5;
    const INTAKE_FORM_INCORRECT_CHECK_DOB = 6;

    protected $_errors = array(
        self::SERVICE_ERROR                    => 'Invalid service is selected. Please select another service to continue booking.',
        self::INTAKE_FORM_UNKNOWN              => 'Intake Forms are missing for this service.',
        self::INTAKE_FORM_UNKNOWN_CHECK_NUMBER => '"Check number" field is missing.',
        self::INTAKE_FORM_INCORRECT_CHECK_NUMBER => '"Check number" field is incorrect.',
        self::INTAKE_FORM_UNKNOWN_CHECK_DOB    => '"Date of birth" field is missing.',
        self::INTAKE_FORM_INCORRECT_CHECK_DOB  => 'Incorrect date of birth.',
    );

    /**
     * Maps logical field keys to the intake form field system names (slugs).
     *
     * IMPORTANT: The 'id' sent by SimplyBook.me for each field in 'additional_fields'
     * is the field's system name (slug), e.g. "check_number" — NOT a UUID or hash.
     * Use these slugs here and when returning field updates/errors in the response.
     *
     * You can find the slug for each field in the SimplyBook.me admin panel under
     * Intake Forms settings, or by logging the raw incoming request on first use.
     */
    protected $_fieldsNameMap = array(
        'checkNumber' => 'check_number',
        'checkString' => 'some_string',
        'dateOfBirth' => 'date_of_birth',
    );

    /**
     * Path to the log file. Set to null to disable logging.
     * IMPORTANT: keep this path outside the web root to prevent public access.
     */
    protected $_logFile = '/tmp/external_validator.log';

    /**
     * Validate incoming booking data from SimplyBook.me.
     *
     * Returns an empty array on success (no field changes), an array with
     * 'additional_fields' to update intake form values, or an error structure.
     *
     * @param array $bookingData
     * @return array
     */
    public function validate($bookingData){
        try {
            $timeStart = microtime(true);
            $this->_log($bookingData);

            // Example: validate service. You can similarly check provider_id, client_id, count, etc.
            if (!isset($bookingData['service_id']) || $bookingData['service_id'] != 9) {
                $this->_error(self::SERVICE_ERROR, 'service_id');
            }

            // Example: require intake form fields to be present
            if (!isset($bookingData['additional_fields'])) {
                $this->_error(self::INTAKE_FORM_UNKNOWN);
            }

            $additionalFields = $bookingData['additional_fields'];

            // Find 'Check number' field by its configured name (or ID — see _findField docs)
            $checkNumberField = $this->_findField('checkNumber', $additionalFields, $this->_fieldsNameMap);

            if (!$checkNumberField) {
                $this->_error(self::INTAKE_FORM_UNKNOWN_CHECK_NUMBER);
            } elseif ($checkNumberField['value'] != '112233445566') {
                $this->_error(self::INTAKE_FORM_INCORRECT_CHECK_NUMBER, null, $checkNumberField['id']);
            }

            // Find 'Date of birth' field
            $dateOfBirthField = $this->_findField('dateOfBirth', $additionalFields, $this->_fieldsNameMap);

            if (!$dateOfBirthField) {
                $this->_error(self::INTAKE_FORM_UNKNOWN_CHECK_DOB);
            } elseif (!$this->_isBirthdayValid($dateOfBirthField['value'])) {
                // Bug fix: was incorrectly using $checkNumberField['id'] here
                $this->_error(self::INTAKE_FORM_INCORRECT_CHECK_DOB, null, $dateOfBirthField['id']);
            }

            // Example: overwrite an intake form value before it is saved on the SimplyBook.me side.
            // Only intake form fields can be changed here — provider/service cannot.
            $result = array(
                'checkString' => 'replaced text',
            );

            $this->_log($result);
            $intakeFieldsResult = $this->_createFieldResult($result, $additionalFields, $this->_fieldsNameMap);
            $this->_log($intakeFieldsResult);

            $executionTime = microtime(true) - $timeStart;
            $this->_log('Total Execution Time: ' . $executionTime . ' sec');

            if ($intakeFieldsResult) {
                return array('additional_fields' => $intakeFieldsResult);
            }
            return array();

        } catch (ExternalValidatorException $e) {
            return $this->_sendError($e);
        } catch (Exception $e) {
            $result = array('errors' => array($e->getMessage()));
            $this->_log($result);
            return $result;
        }
    }

    /**
     * Check that a date string represents a plausible date of birth:
     * - not empty
     * - parseable as a date
     * - not in the future
     * - not more than 140 years ago
     *
     * @param string $date  Date string, e.g. "1973-03-02"
     * @return bool
     */
    protected function _isBirthdayValid($date){
        if (!$date || empty($date)) {
            return false;
        }
        $tDate = strtotime($date);
        if ($tDate === false) {
            return false;
        }
        // Must not be a future date
        if ($tDate > time()) {
            return false;
        }
        // Must be within the last 140 years
        $age = (int) date('Y') - (int) date('Y', $tDate);
        if ($age > 140) {
            return false;
        }
        return true;
    }

    /**
     * Find an intake form field by its system slug (id) or display name.
     *
     * SimplyBook.me sends each field with both an 'id' (the system slug, e.g.
     * "check_number") and a 'name' (the display title, e.g. "Check number").
     * Matching by 'id'/slug is recommended — it is stable and unaffected by
     * admin renames. Matching by 'name' is also supported as a fallback.
     *
     * @param string $fieldKey   Key in $map, e.g. 'checkNumber'
     * @param array  $addFields  The 'additional_fields' array from the incoming request
     * @param array  $map        Map of fieldKey => field slug (id) or display name
     * @param string $mapType    'id' (default, matches by slug) or 'name' (matches by display name)
     * @return array|null        The matching field array, or null if not found
     */
    protected function _findField($fieldKey, $addFields, $map, $mapType = 'id'){
        if (!isset($map[$fieldKey])) {
            return null;
        }
        $searchValue = $map[$fieldKey];

        foreach ($addFields as $additionalField) {
            if ($mapType === 'id') {
                if (isset($additionalField['id']) && $additionalField['id'] === $searchValue) {
                    return $additionalField;
                }
            } else {
                if (isset($additionalField['name'])
                    && strtolower(trim($additionalField['name'])) === strtolower(trim($searchValue))
                ) {
                    return $additionalField;
                }
            }
        }
        return null;
    }

    /**
     * Build the error response for SimplyBook.me.
     *
     * If the exception has an intake field ID, the error is attached to that
     * specific field so the booking form highlights it. Otherwise a general
     * error is returned.
     *
     * @param ExternalValidatorException $e
     * @return array
     */
    protected function _sendError(ExternalValidatorException $e){
        if ($e->getFieldId()) {
            $result = array(
                array(
                    'id'     => $e->getFieldId(),
                    'errors' => array($e->getMessage()),
                )
            );
        } elseif ($e->getIntakeFieldId()) {
            $result = array(
                'additional_fields' => array(
                    array(
                        'id'     => $e->getIntakeFieldId(),
                        'errors' => array($e->getMessage()),
                    )
                )
            );
        } else {
            $result = array(
                'errors' => array($e->getMessage())
            );
        }
        $this->_log($result);
        return $result;
    }

    /**
     * Build the 'additional_fields' array that will overwrite intake form
     * values on the SimplyBook.me side.
     *
     * @param array $resultArr  Map of fieldKey => new value
     * @param array $addFields  Original 'additional_fields' from the request
     * @param array $map        Field name map (same as used in _findField)
     * @return array
     */
    protected function _createFieldResult($resultArr, $addFields, $map){
        $result = array();

        foreach ($resultArr as $key => $value) {
            if (!$value) {
                continue;
            }
            if (is_array($value)) {
                $value = implode('; ', $value);
            }
            $field = $this->_findField($key, $addFields, $map);
            if ($field) {
                $field['value'] = $value;
                $result[] = $field;
            }
        }
        return $result;
    }

    /**
     * Throw a validator exception using a pre-defined error code.
     *
     * @param int         $code
     * @param null|string $fieldId        Top-level field ID (rarely used)
     * @param null|string $intakeFieldId  Intake form field ID to highlight
     * @param array       $data           Optional extra context
     * @throws ExternalValidatorException
     */
    protected function _error($code, $fieldId = null, $intakeFieldId = null, $data = array()) {
        $message = isset($this->_errors[$code]) ? $this->_errors[$code] : '';
        $this->_throwError($message, $code, $fieldId, $intakeFieldId, $data);
    }

    /**
     * @param string      $message
     * @param int         $code
     * @param null|string $fieldId
     * @param null|string $intakeFieldId
     * @param array       $data
     * @throws ExternalValidatorException
     */
    protected function _throwError($message, $code = -1, $fieldId = null, $intakeFieldId = null, $data = array()) {
        $error = new ExternalValidatorException($message, $code);
        if ($fieldId) {
            $error->setFieldId($fieldId);
        }
        if ($intakeFieldId) {
            $error->setIntakeFieldId($intakeFieldId);
        }
        if ($data && count($data)) {
            $error->setData($data);
        }
        throw $error;
    }

    /**
     * Append a debug entry to the log file.
     *
     * Set $this->_logFile = null to disable logging entirely.
     *
     * @param mixed  $var
     * @param string $name  Unused — kept for backwards compatibility
     */
    protected function _log($var, $name = ''){
        if (!$this->_logFile) {
            return;
        }
        $caller = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];

        ob_start();
        var_dump($var);
        $data = ob_get_clean();

        $entry = "\n\n"
            . "--------------------------------\n"
            . date('d.m.Y H:i:s') . "\n"
            . $caller['file'] . ' : ' . $caller['line'] . "\n\n"
            . $data . "\n"
            . "--------------------------------\n";

        file_put_contents($this->_logFile, $entry, FILE_APPEND | LOCK_EX);
    }
}
