from fastapi import FastAPI, Request
from external_validator import ExternalValidator

app = FastAPI()

"""
# Uncomment for local testing (no HTTP request needed):
if __name__ == '__main__':
    stub_data = {
        'service_id': 9,
        'provider_id': 45,
        'client_id': 8123,
        'start_datetime': '2021-01-11 11:40:00',
        'end_datetime': '2021-01-11 11:45:00',
        'count': 1,
        'company_login': 'mycompany',
        'sheduler_id': None,
        'additional_fields': [
            {'id': 'ed8f5b7380f7111c592abf6f916fc2d0', 'name': 'Check number', 'value': '112233445566'},
            {'id': '68700bfe1ba3d59441c9b14d4f94938b', 'name': 'Some string', 'value': 'simplybook'},
            {'id': 'ac4c3775f20dcfdea531346ee5bc8ea4', 'name': 'Date of birth', 'value': '1973-03-02'},
        ],
    }
    import json
    validator = ExternalValidator()
    print(json.dumps(validator.validate(stub_data), indent=2, ensure_ascii=False))
"""


@app.post('/')
async def validate(request: Request):
    data = await request.json()
    if not data:
        return {}
    validator = ExternalValidator()
    return validator.validate(data)


if __name__ == '__main__':
    import uvicorn
    uvicorn.run(app, host='0.0.0.0', port=8000)
