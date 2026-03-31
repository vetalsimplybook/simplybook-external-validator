# SimplyBook.me External Booking Validator — Examples

Reference implementations of the SimplyBook.me [External Booking Validator](https://simplybook.me/en/booking-software/features/external-booking-validator) webhook in four languages.

SimplyBook.me calls your HTTPS endpoint synchronously before saving a booking. Your validator can **accept**, **reject** (with error messages shown to the client), or **modify** (rewrite intake form field values) the booking.

## Implementations

| Language | Framework | Folder | Run |
|----------|-----------|--------|-----|
| PHP 5.6+ | none | [`php/`](php/) | `php index.php` |
| Node.js 18+ | Express | [`nodejs/`](nodejs/) | `node index.js` |
| Python 3.9+ | FastAPI | [`python/`](python/) | `uvicorn main:app` |
| Go 1.21+ | Gin | [`go/`](go/) | `go run .` |

Each implementation contains identical example validation logic and a commented-out stub for local testing (no HTTP request required).

## Local testing

Every entry point has a commented stub block at the top. Uncomment it, run the script directly, and you should see:

```json
{"additional_fields":[{"id":"68700bfe1ba3d59441c9b14d4f94938b","name":"Some string","value":"replaced text"}]}
```

## Protocol

### Request (POST, JSON body)

SimplyBook.me sends a JSON object to your endpoint:

```json
{
    "service_id": 9,
    "provider_id": 45,
    "client_id": 8123,
    "start_datetime": "2021-01-11 11:40:00",
    "end_datetime": "2021-01-11 11:45:00",
    "count": 1,
    "company_login": "mycompany",
    "sheduler_id": null,
    "additional_fields": [
        {
            "id": "ed8f5b7380f7111c592abf6f916fc2d0",
            "name": "Check number",
            "value": "112233445566"
        }
    ]
}
```

**Field IDs:** Each intake form field has a stable MD5 hash `id` generated when the field was created. Match fields by `id`, not by `name` — `id` never changes even if the field is renamed. To find your field IDs, log the raw incoming request on first use.

### Responses

**Accept (no changes):**
```json
{}
```

**Accept with modified intake form values:**
```json
{
    "additional_fields": [
        {
            "id": "68700bfe1ba3d59441c9b14d4f94938b",
            "name": "Some string",
            "value": "replaced text"
        }
    ]
}
```

**Reject — general error (shown as booking-level message):**
```json
{
    "errors": ["Your error message here"]
}
```

**Reject — field-level error (highlights a specific intake form field):**
```json
{
    "additional_fields": [
        {
            "id": "ed8f5b7380f7111c592abf6f916fc2d0",
            "errors": ["Incorrect check number"]
        }
    ]
}
```

## Setup in SimplyBook.me

1. Enable **Custom Features → Intake Forms** and **Custom Features → External Booking Validator**
2. Deploy your validator to an HTTPS endpoint
3. Enter the URL in **External Booking Validator** settings

## Security

- **Always use HTTPS** — the request contains sensitive client data
- **Keep log files outside the web root** — default path is `/tmp/external_validator.log`; set to `null`/`None`/`""` to disable in production
- **Add a shared secret** — verify a query-string token or header in the entry point to reject requests from unknown callers
- **Respond within a few seconds** — SimplyBook.me has a short timeout
