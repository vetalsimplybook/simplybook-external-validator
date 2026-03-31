import json
from datetime import datetime


class ExternalValidatorException(Exception):
    def __init__(self, message, code):
        super().__init__(message)
        self.code = code
        self._field_id = None
        self._intake_field_id = None

    def set_field_id(self, id_): self._field_id = id_
    def set_intake_field_id(self, id_): self._intake_field_id = id_
    def get_field_id(self): return self._field_id
    def get_intake_field_id(self): return self._intake_field_id


class ExternalValidator:

    SERVICE_ERROR = 1
    INTAKE_FORM_UNKNOWN = 2
    INTAKE_FORM_UNKNOWN_CHECK_NUMBER = 3
    INTAKE_FORM_INCORRECT_CHECK_NUMBER = 4
    INTAKE_FORM_UNKNOWN_CHECK_DOB = 5
    INTAKE_FORM_INCORRECT_CHECK_DOB = 6

    def __init__(self):
        self._errors = {
            1: 'Invalid service is selected. Please select another service to continue booking.',
            2: 'Intake Forms are missing for this service.',
            3: '"Check number" field is missing.',
            4: '"Check number" field is incorrect.',
            5: '"Date of birth" field is missing.',
            6: 'Incorrect date of birth.',
        }
        # Maps logical field keys to the Intake Forms field IDs.
        # IMPORTANT: The 'id' sent by SimplyBook.me is an MD5 hash generated when
        # the field was created. It never changes, so matching by 'id' is reliable.
        self._fields_name_map = {
            'checkNumber': 'ed8f5b7380f7111c592abf6f916fc2d0',
            'checkString': '68700bfe1ba3d59441c9b14d4f94938b',
            'dateOfBirth': 'ac4c3775f20dcfdea531346ee5bc8ea4',
        }
        # Path to the log file. Set to None to disable logging.
        self._log_file = '/tmp/external_validator.log'

    def validate(self, booking_data: dict) -> dict:
        try:
            time_start = datetime.now()
            self._log(booking_data)

            if booking_data.get('service_id') != 9:
                self._error(self.SERVICE_ERROR, 'service_id')

            if 'additional_fields' not in booking_data:
                self._error(self.INTAKE_FORM_UNKNOWN)

            additional_fields = booking_data['additional_fields']

            check_number_field = self._find_field('checkNumber', additional_fields, self._fields_name_map)
            if not check_number_field:
                self._error(self.INTAKE_FORM_UNKNOWN_CHECK_NUMBER)
            elif check_number_field['value'] != '112233445566':
                self._error(self.INTAKE_FORM_INCORRECT_CHECK_NUMBER, None, check_number_field['id'])

            dob_field = self._find_field('dateOfBirth', additional_fields, self._fields_name_map)
            if not dob_field:
                self._error(self.INTAKE_FORM_UNKNOWN_CHECK_DOB)
            elif not self._is_birthday_valid(dob_field['value']):
                self._error(self.INTAKE_FORM_INCORRECT_CHECK_DOB, None, dob_field['id'])

            result = {'checkString': 'replaced text'}
            self._log(result)
            intake_fields_result = self._create_field_result(result, additional_fields, self._fields_name_map)
            self._log(intake_fields_result)

            execution_time = (datetime.now() - time_start).total_seconds()
            self._log(f'Total Execution Time: {execution_time} sec')

            if intake_fields_result:
                return {'additional_fields': intake_fields_result}
            return {}

        except ExternalValidatorException as e:
            return self._send_error(e)
        except Exception as e:
            result = {'errors': [str(e)]}
            self._log(result)
            return result

    def _is_birthday_valid(self, date_str: str) -> bool:
        if not date_str:
            return False
        try:
            d = datetime.fromisoformat(date_str).replace(tzinfo=None)
        except ValueError:
            return False
        if d > datetime.now():
            return False
        if datetime.now().year - d.year > 140:
            return False
        return True

    def _find_field(self, field_key: str, add_fields: list, map_: dict, map_type: str = 'id'):
        if field_key not in map_:
            return None
        search_value = map_[field_key]
        for field in add_fields:
            if map_type == 'id':
                if field.get('id') == search_value:
                    return field
            else:
                if field.get('name', '').lower().strip() == search_value.lower().strip():
                    return field
        return None

    def _send_error(self, e: ExternalValidatorException) -> dict:
        if e.get_field_id():
            result = [{'id': e.get_field_id(), 'errors': [str(e)]}]
        elif e.get_intake_field_id():
            result = {'additional_fields': [{'id': e.get_intake_field_id(), 'errors': [str(e)]}]}
        else:
            result = {'errors': [str(e)]}
        self._log(result)
        return result

    def _create_field_result(self, result_arr: dict, add_fields: list, map_: dict) -> list:
        result = []
        for key, value in result_arr.items():
            if not value:
                continue
            str_value = '; '.join(value) if isinstance(value, list) else value
            field = self._find_field(key, add_fields, map_)
            if field:
                result.append({**field, 'value': str_value})
        return result

    def _error(self, code: int, field_id: str = None, intake_field_id: str = None):
        message = self._errors.get(code, '')
        exc = ExternalValidatorException(message, code)
        if field_id:
            exc.set_field_id(field_id)
        if intake_field_id:
            exc.set_intake_field_id(intake_field_id)
        raise exc

    def _log(self, var_):
        if not self._log_file:
            return
        entry = (
            '\n\n--------------------------------\n'
            + datetime.now().strftime('%d.%m.%Y %H:%M:%S') + '\n\n'
            + (json.dumps(var_, indent=2, ensure_ascii=False) if not isinstance(var_, str) else var_)
            + '\n--------------------------------\n'
        )
        with open(self._log_file, 'a', encoding='utf-8') as f:
            f.write(entry)
