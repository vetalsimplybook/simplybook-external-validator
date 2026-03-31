package main

import (
	"encoding/json"
	"fmt"
	"os"
	"strings"
	"time"
)

// ExternalValidatorError carries a validation error with optional field context.
type ExternalValidatorError struct {
	message       string
	code          int
	fieldID       string
	intakeFieldID string
}

func (e *ExternalValidatorError) Error() string              { return e.message }
func (e *ExternalValidatorError) SetFieldID(id string)       { e.fieldID = id }
func (e *ExternalValidatorError) SetIntakeFieldID(id string) { e.intakeFieldID = id }
func (e *ExternalValidatorError) GetFieldID() string         { return e.fieldID }
func (e *ExternalValidatorError) GetIntakeFieldID() string   { return e.intakeFieldID }

const (
	errServiceError                   = 1
	errIntakeFormUnknown              = 2
	errIntakeFormUnknownCheckNumber   = 3
	errIntakeFormIncorrectCheckNumber = 4
	errIntakeFormUnknownCheckDOB      = 5
	errIntakeFormIncorrectCheckDOB    = 6
)

// ExternalValidator validates booking data from SimplyBook.me.
type ExternalValidator struct {
	errors        map[int]string
	fieldsNameMap map[string]string
	logFile       string
}

// NewExternalValidator returns a validator with default configuration.
func NewExternalValidator() *ExternalValidator {
	return &ExternalValidator{
		errors: map[int]string{
			errServiceError:                   "Invalid service is selected. Please select another service to continue booking.",
			errIntakeFormUnknown:              "Intake Forms are missing for this service.",
			errIntakeFormUnknownCheckNumber:   `"Check number" field is missing.`,
			errIntakeFormIncorrectCheckNumber: `"Check number" field is incorrect.`,
			errIntakeFormUnknownCheckDOB:      `"Date of birth" field is missing.`,
			errIntakeFormIncorrectCheckDOB:    "Incorrect date of birth.",
		},
		// IMPORTANT: The 'id' sent by SimplyBook.me is an MD5 hash generated when
		// the field was created. It never changes, so matching by 'id' is reliable.
		fieldsNameMap: map[string]string{
			"checkNumber": "ed8f5b7380f7111c592abf6f916fc2d0",
			"checkString": "68700bfe1ba3d59441c9b14d4f94938b",
			"dateOfBirth": "ac4c3775f20dcfdea531346ee5bc8ea4",
		},
		// Path to the log file. Set to "" to disable logging.
		logFile: "/tmp/external_validator.log",
	}
}

// Validate validates booking data and returns the response.
func (v *ExternalValidator) Validate(bookingData map[string]interface{}) interface{} {
	result, err := v.validate(bookingData)
	if err != nil {
		if ve, ok := err.(*ExternalValidatorError); ok {
			return v.sendError(ve)
		}
		res := map[string]interface{}{"errors": []string{err.Error()}}
		v.log(res)
		return res
	}
	return result
}

func (v *ExternalValidator) validate(bookingData map[string]interface{}) (map[string]interface{}, error) {
	timeStart := time.Now()
	v.log(bookingData)

	serviceID, _ := bookingData["service_id"].(float64)
	if serviceID != 9 {
		return nil, v.newError(errServiceError, "service_id", "")
	}

	rawFields, ok := bookingData["additional_fields"]
	if !ok {
		return nil, v.newError(errIntakeFormUnknown, "", "")
	}

	addFields := toFieldSlice(rawFields)

	checkNumberField := v.findField("checkNumber", addFields, v.fieldsNameMap, "id")
	if checkNumberField == nil {
		return nil, v.newError(errIntakeFormUnknownCheckNumber, "", "")
	}
	if fmt.Sprintf("%v", checkNumberField["value"]) != "112233445566" {
		return nil, v.newError(errIntakeFormIncorrectCheckNumber, "", fmt.Sprintf("%v", checkNumberField["id"]))
	}

	dobField := v.findField("dateOfBirth", addFields, v.fieldsNameMap, "id")
	if dobField == nil {
		return nil, v.newError(errIntakeFormUnknownCheckDOB, "", "")
	}
	if !v.isBirthdayValid(fmt.Sprintf("%v", dobField["value"])) {
		return nil, v.newError(errIntakeFormIncorrectCheckDOB, "", fmt.Sprintf("%v", dobField["id"]))
	}

	resultMap := map[string]string{"checkString": "replaced text"}
	v.log(resultMap)
	intakeFieldsResult := v.createFieldResult(resultMap, addFields, v.fieldsNameMap)
	v.log(intakeFieldsResult)

	v.log(fmt.Sprintf("Total Execution Time: %s", time.Since(timeStart)))

	if len(intakeFieldsResult) > 0 {
		return map[string]interface{}{"additional_fields": intakeFieldsResult}, nil
	}
	return map[string]interface{}{}, nil
}

func (v *ExternalValidator) isBirthdayValid(dateStr string) bool {
	if dateStr == "" {
		return false
	}
	d, err := time.Parse("2006-01-02", dateStr)
	if err != nil {
		return false
	}
	if d.After(time.Now()) {
		return false
	}
	if time.Now().Year()-d.Year() > 140 {
		return false
	}
	return true
}

func (v *ExternalValidator) findField(fieldKey string, addFields []map[string]interface{}, map_ map[string]string, mapType string) map[string]interface{} {
	searchValue, ok := map_[fieldKey]
	if !ok {
		return nil
	}
	for _, field := range addFields {
		if mapType == "id" {
			if fmt.Sprintf("%v", field["id"]) == searchValue {
				return field
			}
		} else {
			if strings.EqualFold(strings.TrimSpace(fmt.Sprintf("%v", field["name"])), strings.TrimSpace(searchValue)) {
				return field
			}
		}
	}
	return nil
}

func (v *ExternalValidator) sendError(e *ExternalValidatorError) interface{} {
	var result interface{}
	if e.GetFieldID() != "" {
		result = []map[string]interface{}{
			{"id": e.GetFieldID(), "errors": []string{e.Error()}},
		}
	} else if e.GetIntakeFieldID() != "" {
		result = map[string]interface{}{
			"additional_fields": []map[string]interface{}{
				{"id": e.GetIntakeFieldID(), "errors": []string{e.Error()}},
			},
		}
	} else {
		result = map[string]interface{}{"errors": []string{e.Error()}}
	}
	v.log(result)
	return result
}

func (v *ExternalValidator) createFieldResult(resultArr map[string]string, addFields []map[string]interface{}, map_ map[string]string) []map[string]interface{} {
	var result []map[string]interface{}
	for key, value := range resultArr {
		if value == "" {
			continue
		}
		field := v.findField(key, addFields, map_, "id")
		if field != nil {
			updated := make(map[string]interface{})
			for k, v := range field {
				updated[k] = v
			}
			updated["value"] = value
			result = append(result, updated)
		}
	}
	return result
}

func (v *ExternalValidator) newError(code int, fieldID, intakeFieldID string) *ExternalValidatorError {
	msg := v.errors[code]
	e := &ExternalValidatorError{message: msg, code: code}
	if fieldID != "" {
		e.SetFieldID(fieldID)
	}
	if intakeFieldID != "" {
		e.SetIntakeFieldID(intakeFieldID)
	}
	return e
}

func (v *ExternalValidator) log(var_ interface{}) {
	if v.logFile == "" {
		return
	}
	var content string
	if s, ok := var_.(string); ok {
		content = s
	} else {
		b, _ := json.MarshalIndent(var_, "", "  ")
		content = string(b)
	}
	entry := "\n\n--------------------------------\n" +
		time.Now().Format("02.01.2006 15:04:05") + "\n\n" +
		content + "\n--------------------------------\n"
	f, err := os.OpenFile(v.logFile, os.O_APPEND|os.O_CREATE|os.O_WRONLY, 0644)
	if err != nil {
		return
	}
	defer f.Close()
	f.WriteString(entry)
}

// toFieldSlice converts the interface{} from JSON decode to []map[string]interface{}.
func toFieldSlice(raw interface{}) []map[string]interface{} {
	slice, ok := raw.([]interface{})
	if !ok {
		return nil
	}
	result := make([]map[string]interface{}, 0, len(slice))
	for _, item := range slice {
		if m, ok := item.(map[string]interface{}); ok {
			result = append(result, m)
		}
	}
	return result
}
