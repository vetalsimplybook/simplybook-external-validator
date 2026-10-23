import hmac
import os

from fastapi import FastAPI, Request
from fastapi.responses import JSONResponse
from external_validator import ExternalValidator

# SimplyBook.me cannot sign requests or send custom headers, so the only
# protection is a secret token in the URL (?token=...).
# Refuse to start without a secret of at least 32 characters.
SECRET = os.environ.get('VALIDATOR_TOKEN', '')
if len(SECRET) < 32:
    raise RuntimeError('VALIDATOR_TOKEN must be set to a secret of at least 32 characters')


def token_matches(token) -> bool:
    # Constant-time comparison on bytes (compare_digest rejects non-ASCII str)
    return isinstance(token, str) and hmac.compare_digest(token.encode(), SECRET.encode())


app = FastAPI()

"""
# Uncomment for local testing (no HTTP request needed).
# Run with: VALIDATOR_TOKEN=<at least 32 chars> python3 main.py
if __name__ == '__main__':
    stub_token = os.environ.get('VALIDATOR_TOKEN')  # the value you would put in ?token=
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
    print(json.dumps(validator.validate(stub_data), indent=2, ensure_ascii=False) if token_matches(stub_token) else '403 {}')
    raise SystemExit
"""


# Only POST is accepted: FastAPI answers other methods with 405 (JSON) on its own.
@app.post('/')
async def validate(request: Request):
    if not token_matches(request.query_params.get('token')):
        return JSONResponse(status_code=403, content={})
    data = await request.json()
    if not data:
        return {}
    validator = ExternalValidator()
    return validator.validate(data)


if __name__ == '__main__':
    import uvicorn
    uvicorn.run(app, host='0.0.0.0', port=8000)
