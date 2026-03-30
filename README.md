# SimplyBook.me External Booking Validator — Example

This repository shows how to build an **External Booking Validator** endpoint
for the [SimplyBook.me](https://simplybook.me) booking platform.

When the feature is enabled, SimplyBook.me calls your endpoint with the booking
data before the booking is confirmed. Your script can:

- **Accept or reject** the booking based on any condition (service, provider,
  intake form answers, external database lookup, …)
- **Rewrite intake form field values** — the overwritten values are stored as if
  the client had entered them.

> **Note:** Development skills are required. To validate intake form fields,
> enable the *Intake Forms* custom feature separately.

---

## How It Works

```
Client fills booking form
        │
        ▼
SimplyBook.me sends POST request to your URL
        │
        ▼
Your script validates the data
        │
   ┌────┴────┐
   │         │
 Pass       Fail
   │         │
   ▼         ▼
Booking   Error shown
saved     to client
```

SimplyBook.me sends a JSON `POST` request to the URL you configure and expects
a JSON response back. The whole exchange must complete within the platform's
timeout (a few seconds), so keep your validation logic fast.

---

## Incoming Request

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
        },
        {
            "id": "68700bfe1ba3d59441c9b14d4f94938b",
            "name": "Some string",
            "value": "simplybook"
        },
        {
            "id": "ac4c3775f20dcfdea531346ee5bc8ea4",
            "name": "Date of birth",
            "value": "1973-03-02"
        }
    ]
}
```

### Always-present fields

| Field | Type | Description |
|---|---|---|
| `service_id` | int | ID of the selected service |
| `provider_id` | int | ID of the selected provider |
| `client_id` | int | ID of the client (0 if not logged in) |
| `start_datetime` | string | Booking start — `YYYY-MM-DD HH:MM:SS` |
| `end_datetime` | string | Booking end — `YYYY-MM-DD HH:MM:SS` |
| `count` | int | Number of slots / group size |
| `company_login` | string | Company login/slug identifier |
| `sheduler_id` | int\|null | Existing booking ID when rescheduling; `null` for new bookings |
| `additional_fields` | array | Intake Forms answers (empty array if none) |

### Optional fields (sent only when the corresponding custom feature is active)

| Field | Type | Custom Feature | Description |
|---|---|---|---|
| `location_id` | int | Multiple Locations | Selected location ID |
| `category_id` | int | Service Categories | Selected category ID |
| `paid_attributes` | array | Service Add-ons | Selected add-on items — each has `id` (int) and optional `qty` |
| `products` | array | Products for Sale | Selected product items — each has `id` (int) and optional `qty` |

### Each element of `additional_fields`

| Field | Type | Description |
|---|---|---|
| `id` | string | MD5 hash generated at field creation time (e.g. `"ed8f5b7380f7111c592abf6f916fc2d0"`). Stable — never changes, use this for matching. |
| `name` | string | Human-readable display name (e.g. `"Check number"`) — can be renamed by admin |
| `value` | string | Value entered by the client |

> **Important:** The `id` of each intake form field is its **system slug** (not a UUID).
> Always match and reference fields by `id`/slug, not by `name`, since admins can rename
> fields without changing the slug.

---

## Response Formats

### Success — no field changes

```json
{}
```

### Success — overwrite one or more intake form values

The returned fields are saved on the SimplyBook.me side as if entered by the
client. Reference fields by their system slug in `id`.

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

### Error — general (shown as a booking-level message)

```json
{
    "errors": ["Your error message here"]
}
```

### Error — attached to a specific intake form field

The field is highlighted on the booking form next to the relevant input.
Use the field's system slug as `id`.

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

---

## Files

| File | Purpose |
|---|---|
| `index.php` | Entry point — reads the POST body and returns JSON |
| `ExternalValidator.php` | Core validation logic — **copy and adapt this** |
| `ExternalValidatorException.php` | Exception class used to carry field-level errors |

---

## Quick Start

1. Copy all three PHP files to a publicly accessible HTTPS endpoint.
2. Open `ExternalValidator.php` and update:
   - `$_fieldsNameMap` — map your logical keys to the actual intake form field
     **slugs** (the `id` values from the incoming request).
   - `$_logFile` — set a path **outside the web root**, or set to `null` to
     disable logging.
   - The validation logic inside `validate()` — replace the example checks with
     your own rules.
3. In SimplyBook.me → Custom Features → External Booking Validator, paste the
   URL of your `index.php` and save.
4. Test with the local stub in `index.php` (uncomment `$incomingData = …`).

---

## Security Tips

- **Always use HTTPS** — the request from SimplyBook.me contains client data.
- **Keep log files outside the web root** — the default log path in this example
  is `/tmp/external_validator.log`. Never log to a directory served by your
  web server.
- **Disable logging in production** once you have verified the integration
  works: set `$this->_logFile = null;` in `ExternalValidator.php`.
- Consider adding a **shared secret** (e.g. a query-string token in the URL you
  configure) and verifying it at the top of `index.php` to prevent unauthorized
  calls.

---

## Matching Fields by ID vs. Display Name

By default `_findField()` matches intake form fields by their `id` (an MD5 hash
generated when the field was created, e.g. `"ed8f5b7380f7111c592abf6f916fc2d0"`).
This is the recommended approach — the hash never changes even if the field is renamed.

To discover the IDs for your fields, log the raw incoming request on first use
and copy the `id` values from `additional_fields`.

**Match by ID (recommended):** use the MD5 hash as the value — default behaviour, no extra argument needed:

```php
protected $_fieldsNameMap = array(
    'checkNumber' => 'ed8f5b7380f7111c592abf6f916fc2d0',
    'checkString' => '68700bfe1ba3d59441c9b14d4f94938b',
    'dateOfBirth' => 'ac4c3775f20dcfdea531346ee5bc8ea4',
);

// Inside validate():
$checkNumberField = $this->_findField('checkNumber', $additionalFields, $this->_fieldsNameMap);
$dateOfBirthField = $this->_findField('dateOfBirth', $additionalFields, $this->_fieldsNameMap);
```

**Match by display name** (e.g. during quick prototyping before you know the IDs): use the display name as the value and pass `'name'` as the fourth argument to `_findField()`:

```php
protected $_fieldsNameMap = array(
    'checkNumber' => 'Check number',
    'checkString' => 'Some string',
    'dateOfBirth' => 'Date of birth',
);

// Inside validate():
$checkNumberField = $this->_findField('checkNumber', $additionalFields, $this->_fieldsNameMap, 'name');
$dateOfBirthField = $this->_findField('dateOfBirth', $additionalFields, $this->_fieldsNameMap, 'name');
```

> Choose one approach for the whole class — don't mix ID and name matching in the same `$_fieldsNameMap`.

---

## Requirements

- PHP 5.6+
- A publicly reachable HTTPS URL
