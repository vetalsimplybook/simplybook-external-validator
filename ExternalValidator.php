<?php


class ExternalValidator {

    const SERVICE_ERROR = 1;
    const INTAKE_FORM_UNKNOWN = 2;
    const INTAKE_FORM_UNKNOWN_CHECK_NUMBER = 3;
    const INTAKE_FORM_INCORRECT_CHECK_NUMBER = 4;
    const INTAKE_FORM_UNKNOWN_CHECK_DOB = 5;
    const INTAKE_FORM_INCORRECT_CHECK_DOB = 6;

    protected $_errors = array(
        self::SERVICE_ERROR => 'Invalid service selected. Please select another service to continue booking.',
        self::INTAKE_FORM_UNKNOWN => 'Intake Form are missing for this service',
        self::INTAKE_FORM_UNKNOWN_CHECK_NUMBER => '"Check number" field are missing.',
        self::INTAKE_FORM_INCORRECT_CHECK_NUMBER => '"Check number" field are incorrect.',
        self::INTAKE_FORM_UNKNOWN_CHECK_DOB => '"Date of birth" field are missing.',
        self::INTAKE_FORM_INCORRECT_CHECK_DOB => 'Incorrect date of birth',
    );

    protected $_fieldsNameMap = array(
        'checkNumber' => 'Check number',
        'checkString' => 'Some string',
        'dateOfBirth' => 'Date of birth',
    );

    public function validate($bookingData){
        try{
            $timeStart = microtime(true);
            $this->_log($bookingData);

            //Example of service validation. Similarly, you can check the provider, client or number of bookings
            if (!isset($bookingData['service_id']) || $bookingData['service_id'] != 9) {
                $this->_error(self::SERVICE_ERROR, 'service_id');
                return false;
            }

            //Example of Intake Form validation.
            if (!isset($bookingData['additional_fields'])) {
                $this->_error(self::INTAKE_FORM_UNKNOWN);
                return false;
            }

            //select Intake field by name 'Check number'. By analogy you can find Intake field by id (if you know the id in advance)
            $checkNumberField = $this->_findField('checkNumber', $bookingData['additional_fields'], $this->_fieldsNameMap);

            //Example of 'Check number' validation.
            if (!$checkNumberField) { //field with name 'Check number' are missing
                $this->_error(self::INTAKE_FORM_UNKNOWN_CHECK_NUMBER );
                return false;
            }else if ($checkNumberField['value'] != 112233445566) { //check value
                $this->_error(self::INTAKE_FORM_INCORRECT_CHECK_NUMBER, null, $checkNumberField['id'] );
                return false;
            }

            //select Intake field by name 'Date of birth'. By analogy you can find Intake field by id (if you know the id in advance)
            $dateOfBirthField = $this->_findField('dateOfBirth', $bookingData['additional_fields'], $this->_fieldsNameMap);

            //Example of 'Date of birth' validation.
            if (!$dateOfBirthField) { //field with name 'Date of birth' are missing
                $this->_error(self::INTAKE_FORM_UNKNOWN_CHECK_DOB );
                return false;
            }else if ( !$this->_isBirthdayValid($dateOfBirthField['value']) ) { //check if DOB valid
                $this->_error(self::INTAKE_FORM_INCORRECT_CHECK_DOB, null, $checkNumberField['id'] );
                return false;
            }

            //Example of changing the value of Intake Form.
            // This value will be saved on the Simplybook side.
            // Please note that only Intake Form can be changed (provider or service cannot be changed)
            $result = array(
                'checkString' => "replaced text", //Change the value of the name 'Some string' field. The value will be saved on the simplybook side, as if entered by the client
            );

            $this->_log($result);
            $intakeFieldsResult = $this->_createFieldResult($result, $bookingData['additional_fields'], $this->_fieldsNameMap);
            $this->_log($intakeFieldsResult);

            $timeEnd = microtime(true);
            $executionTime = $timeEnd - $timeStart;

            $this->_log('Total Execution Time: '.$executionTime.' sec');

            if($intakeFieldsResult){
                return array(
                    'additional_fields' => $intakeFieldsResult,
                );
            }
            return array();
        } catch(ExternalValidatorException $e){ //validator Error
            return $this->_sendError($e);
        } catch (Exception $e){ // other error
            $result = array(
                'errors' => array($e->getMessage())
            );
            $this->_log($result);
            return $result;
        }
    }

    protected function _isBirthdayValid($date){
        if(!$date || empty($date)){
            return false;
        }
        $tDate = strtotime($date);
        if($tDate === false){
            return false;
        }

        $age = date('Y') - date('Y', $tDate);
        if($age > 140){
            return false;
        }
        if (date('Ymd') < date('Ymd', $tDate)) {
            return false;
        }
        return true;
    }

    protected function _findField($fieldKey, $addFields, $map){
        $mapType = 'name';

        if(isset($map[$fieldKey])) {
            $fieldName = $map[$fieldKey];

            foreach ($addFields as $additionalField) {
                if (strtolower(trim($additionalField[$mapType])) == strtolower(trim($fieldName))) {
                    return $additionalField;
                }
            }
        }
        return null;
    }

    /**
     * Generation error for output on the Simplybook booking page
     *
     * @param ExternalValidatorException $e
     * @return array[]|array[][]
     */
    protected function _sendError(ExternalValidatorException $e){
        if($e->getFieldId()){
            $result = array(
                array(
                    'id' => $e->getFieldId(),
                    'errors' => array($e->getMessage())
                )
            );
            $this->_log($result);
            return $result;
        }else if($e->getIntakeFieldId()){
            $result = array(
                'additional_fields' => array(
                    array(
                        'id' => $e->getIntakeFieldId(),
                        'errors' => array($e->getMessage())
                    )
                )
            );
            $this->_log($result);
            return $result;
        } else {
            $result = array(
                'errors' => array($e->getMessage())
            );
            $this->_log($result);
            return $result;
        }
    }


    /**
     * @param array $resultArr
     * @param array $addFields
     * @param array $map
     * @param string{"name", "title"} $mapType
     * @return array
     */
    protected function _createFieldResult($resultArr, $addFields, $map){
        $result = array();

        foreach ($resultArr as $key => $value){
            if(!$value){
                continue;
            }
            if(is_array($value)){
                $value = implode('; ', $value);
            }
            $field = $this->_findField($key, $addFields, $map);

            if($field){
                $field['value'] = $value;
                $result[] = $field;
            }
        }
        return $result;
    }

    /**
     * @param int $code
     * @param null|array $fieldId
     * @param null|array $intakeFieldId
     * @param null|array $data
     * @throws ExternalValidatorException
     */
    protected function _error($code, $fieldId = null, $intakeFieldId = null, $data = NULL) {
        $message = '';
        if (isset($this->_errors[$code])) {
            $message = $this->_errors[$code];
        }
        $this->_throwError($message, $code, $fieldId, $intakeFieldId, $data);
    }
    /**
     * @param string $message
     * @param int $code
     * @param null|string $fieldId
     * @param array $data
     * @throws ExternalValidatorException
     */
    protected function _throwError($message, $code = -1, $fieldId = null, $intakeFieldId = null, $data = array()) {
        $error = new ExternalValidatorException($message, $code);
        if($fieldId){
            $error->setFieldId($fieldId);
        }
        if($intakeFieldId){
            $error->setIntakeFieldId($intakeFieldId);
        }
        if ($data && count($data)) {
            $error->setData($data);
        }
        throw $error;
    }

    /**
     * Log to file
     * @param $var
     * @param string $name
     */
    protected function _log($var, $name = 'log'){
        $bugtrace = debug_backtrace();
        $bugTraceIterator = 0;
        //dump var to string
        ob_start();
        var_dump( $var );
        $data = ob_get_clean();

        $logContent = "\n\n" .
            "--------------------------------\n" .
            date("d.m.Y H:i:s") . "\n" .
            "{$bugtrace[$bugTraceIterator]['file']} : {$bugtrace[$bugTraceIterator]['line']}\n\n" .
            $data . "\n" .
            "--------------------------------\n";

        $fh = fopen( $name . '.txt', 'a');
        fwrite($fh, $logContent);
        fclose($fh);
    }

}