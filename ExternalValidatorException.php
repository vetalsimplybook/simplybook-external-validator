<?php


class ExternalValidatorException extends Exception {

    protected $_intakeFieldId = null;
    protected $_fieldId = null;
    protected $_data = array();

    /**
     * @return array
     */
    public function getData() {
        return $this->_data;
    }

    public function setData($data) {
        $this->_data = $data;
        return $this;
    }
    /**
     * @return null|string
     * @return self
     */
    public function getIntakeFieldId(){
        return $this->_intakeFieldId;
    }

    /**
     * @param null|string $fieldId
     * @return self
     */
    public function setIntakeFieldId($fieldId){
        $this->_intakeFieldId = $fieldId;
        return $this;
    }

    /**
     * @return null
     */
    public function getFieldId(){
        return $this->_fieldId;
    }

    /**
     * @param null $fieldId
     * @return self
     */
    public function setFieldId($fieldId){
        $this->_fieldId = $fieldId;
        return $this;
    }


}