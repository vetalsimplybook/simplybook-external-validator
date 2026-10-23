# SimplyBook.me External Booking Validator — Examples

Reference implementations of the SimplyBook.me [External Booking Validator](https://simplybook.me/en/booking-software/features/external-booking-validator) webhook in five languages.

SimplyBook.me calls your HTTPS endpoint synchronously before saving a booking. Your validator can **accept**, **reject** (with error messages shown to the client), or **modify** (rewrite intake form field values) the booking.

## Implementations

| Language | Framework | Folder |
|----------|-----------|--------|
| PHP 5.6+ | none | [`php/`](php/) |
| Node.js 18+ | Express | [`nodejs/`](nodejs/) |
| Python 3.9+ | FastAPI | [`python/`](python/) |
| Go 1.21+ | Gin | [`go/`](go/) |
| Java 17+ | Spring Boot | [`java/`](java/) |

Each implementation contains identical example validation logic and URL-token authentication (see [Security](#security)). All except Java also have a commented-out stub for local testing (no HTTP request required).

---

## Getting started

<details>
<summary><strong>PHP</strong></summary>

### Requirements
- PHP 5.6+

### Run the HTTP server

```bash
cd php
export VALIDATOR_TOKEN=$(openssl rand -hex 32)   # see Security
php -S localhost:8080 index.php
```

Without the environment variable you can put the secret in a config file outside the web root instead — see [Security](#security).

### Local test (no HTTP request needed)

Uncomment the stub block in `php/index.php` (remove the `/*` and `*/` around the stub), then:

```bash
VALIDATOR_TOKEN=$(openssl rand -hex 32) php index.php
```

Expected output:
```json
{"additional_fields":[{"id":"68700bfe1ba3d59441c9b14d4f94938b","name":"Some string","value":"replaced text"}]}
```

Re-comment the stub block when done.

### Test with curl

```bash
curl -X POST "http://localhost:8080/?token=$VALIDATOR_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{
    "service_id": 9,
    "additional_fields": [
      {"id": "ed8f5b7380f7111c592abf6f916fc2d0", "name": "Check number", "value": "112233445566"},
      {"id": "68700bfe1ba3d59441c9b14d4f94938b", "name": "Some string", "value": "simplybook"},
      {"id": "ac4c3775f20dcfdea531346ee5bc8ea4", "name": "Date of birth", "value": "1973-03-02"}
    ]
  }'
```

### Production deployment

Deploy the `php/` directory to any PHP-capable hosting (shared hosting, VPS with Apache or Nginx + PHP-FPM).

**Nginx config example** — point the root to the `php/` folder and pass requests to PHP-FPM:

```nginx
server {
    listen 443 ssl;
    server_name yourdomain.com;
    root /var/www/validator/php;

    location / {
        try_files $uri /index.php;
        fastcgi_pass unix:/run/php/php8-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        include fastcgi_params;
    }
}
```

**Set the secret** for PHP-FPM with `env[VALIDATOR_TOKEN] = ...` in the pool config (or `fastcgi_param VALIDATOR_TOKEN ...;` in Nginx, or `SetEnv VALIDATOR_TOKEN ...` in Apache), or — on shared hosting — in `validator.config.php` outside the web root (see [Security](#security)).

**Before going live**, disable logging in `php/ExternalValidator.php`:

```php
protected $_logFile = null;
```

</details>

<details>
<summary><strong>Node.js</strong></summary>

### Requirements
- Node.js 18+

### Install dependencies

```bash
cd nodejs
npm install
```

### Run the HTTP server

```bash
export VALIDATOR_TOKEN=$(openssl rand -hex 32)   # see Security
node index.js
# Listening on http://localhost:3000
```

### Local test (no HTTP request needed)

Uncomment the stub block in `nodejs/index.js` (remove the `/*` and `*/` around the stub), then:

```bash
VALIDATOR_TOKEN=$(openssl rand -hex 32) node index.js
```

Expected output:
```json
{"additional_fields":[{"id":"68700bfe1ba3d59441c9b14d4f94938b","name":"Some string","value":"replaced text"}]}
```

Re-comment the stub block when done.

### Test with curl

```bash
curl -X POST "http://localhost:3000/?token=$VALIDATOR_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{
    "service_id": 9,
    "additional_fields": [
      {"id": "ed8f5b7380f7111c592abf6f916fc2d0", "name": "Check number", "value": "112233445566"},
      {"id": "68700bfe1ba3d59441c9b14d4f94938b", "name": "Some string", "value": "simplybook"},
      {"id": "ac4c3775f20dcfdea531346ee5bc8ea4", "name": "Date of birth", "value": "1973-03-02"}
    ]
  }'
```

### Production deployment

Run with [PM2](https://pm2.keymetrics.io/) so the process restarts automatically on crash or reboot:

```bash
npm install -g pm2
cd nodejs
VALIDATOR_TOKEN=<your secret> pm2 start index.js --name validator --update-env
pm2 save && pm2 startup
```

Put it behind a reverse proxy (Nginx or Caddy) for HTTPS termination. Example Caddy config:

```
yourdomain.com {
    reverse_proxy localhost:3000
}
```

**Before going live**, disable logging in `nodejs/ExternalValidator.js`:

```js
this._logFile = null;
```

</details>

<details>
<summary><strong>Python</strong></summary>

### Requirements
- Python 3.9+

### Install dependencies

```bash
cd python
pip install -r requirements.txt
```

### Run the HTTP server

```bash
export VALIDATOR_TOKEN=$(openssl rand -hex 32)   # see Security
uvicorn main:app --port 8000 --no-access-log   # the access log would record ?token=
# Listening on http://localhost:8000
```

### Local test (no HTTP request needed)

Run directly from the `python/` directory (this calls the validator directly, so no token is involved):

```bash
python3 -c "
import json, sys; sys.path.insert(0, '.')
from external_validator import ExternalValidator
stub = {
    'service_id': 9,
    'additional_fields': [
        {'id': 'ed8f5b7380f7111c592abf6f916fc2d0', 'name': 'Check number', 'value': '112233445566'},
        {'id': '68700bfe1ba3d59441c9b14d4f94938b', 'name': 'Some string', 'value': 'simplybook'},
        {'id': 'ac4c3775f20dcfdea531346ee5bc8ea4', 'name': 'Date of birth', 'value': '1973-03-02'},
    ]
}
print(json.dumps(ExternalValidator().validate(stub), indent=2))
"
```

Expected output:
```json
{"additional_fields":[{"id":"68700bfe1ba3d59441c9b14d4f94938b","name":"Some string","value":"replaced text"}]}
```

### Test with curl

```bash
curl -X POST "http://localhost:8000/?token=$VALIDATOR_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{
    "service_id": 9,
    "additional_fields": [
      {"id": "ed8f5b7380f7111c592abf6f916fc2d0", "name": "Check number", "value": "112233445566"},
      {"id": "68700bfe1ba3d59441c9b14d4f94938b", "name": "Some string", "value": "simplybook"},
      {"id": "ac4c3775f20dcfdea531346ee5bc8ea4", "name": "Date of birth", "value": "1973-03-02"}
    ]
  }'
```

### Production deployment

Run uvicorn with [Gunicorn](https://gunicorn.org/) as the process manager for multiple workers:

```bash
cd python
pip install gunicorn
gunicorn main:app -w 4 -k uvicorn.workers.UvicornWorker --bind 0.0.0.0:8000
```

Pass the secret with `VALIDATOR_TOKEN=<your secret> gunicorn ...` (do not enable `--access-logfile`: it would record `?token=`). Or use [systemd](https://systemd.io/) / [supervisor](http://supervisord.org/) (with an `Environment=` / `environment=` entry) to keep the process alive. Put it behind a reverse proxy (Nginx or Caddy) for HTTPS termination. Example Caddy config:

```
yourdomain.com {
    reverse_proxy localhost:8000
}
```

**Before going live**, disable logging in `python/external_validator.py`:

```python
self._log_file = None
```

</details>

<details>
<summary><strong>Go</strong></summary>

### Requirements
- Go 1.21+

### Install dependencies

```bash
cd go
go mod download
```

### Run the HTTP server

```bash
export VALIDATOR_TOKEN=$(openssl rand -hex 32)   # see Security
go run .
# Listening on http://localhost:8080
```

### Local test (no HTTP request needed)

Uncomment the stub block in `go/main.go` (remove the `/*` and `*/` around the stub), then:

```bash
VALIDATOR_TOKEN=$(openssl rand -hex 32) go run .
```

Expected output:
```json
{"additional_fields":[{"id":"68700bfe1ba3d59441c9b14d4f94938b","name":"Some string","value":"replaced text"}]}
```

Re-comment the stub block when done.

### Test with curl

```bash
curl -X POST "http://localhost:8080/?token=$VALIDATOR_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{
    "service_id": 9,
    "additional_fields": [
      {"id": "ed8f5b7380f7111c592abf6f916fc2d0", "name": "Check number", "value": "112233445566"},
      {"id": "68700bfe1ba3d59441c9b14d4f94938b", "name": "Some string", "value": "simplybook"},
      {"id": "ac4c3775f20dcfdea531346ee5bc8ea4", "name": "Date of birth", "value": "1973-03-02"}
    ]
  }'
```

### Production deployment

1. **Build the binary**

```bash
cd go
go build -o validator .
```

2. **Run the binary** (e.g. as a systemd service or via a process manager):

```bash
VALIDATOR_TOKEN=<your secret> ./validator
```

3. **Put it behind a reverse proxy** (Nginx or Caddy) to handle HTTPS termination. Example Caddy config:

```
yourdomain.com {
    reverse_proxy localhost:8080
}
```

4. **Before going live**, disable logging in `go/external_validator.go`:

```go
logFile: "",
```

</details>

<details>
<summary><strong>Java</strong></summary>

### Requirements
- JDK 17+ ([Oracle](https://www.oracle.com/java/technologies/downloads/), [Adoptium](https://adoptium.net/))
- Gradle is **not** required — the project includes a Gradle wrapper (`gradlew`)

### Build the JAR

```bash
cd java
./gradlew bootJar
```

This produces `java/build/libs/validator.jar` (~20 MB fat JAR with all dependencies).

### Run the HTTP server

```bash
export VALIDATOR_TOKEN=$(openssl rand -hex 32)   # see Security
java -jar java/build/libs/validator.jar
# Listening on http://localhost:8080
```

### Test with curl

```bash
curl -X POST "http://localhost:8080/?token=$VALIDATOR_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{
    "service_id": 9,
    "additional_fields": [
      {"id": "ed8f5b7380f7111c592abf6f916fc2d0", "name": "Check number", "value": "112233445566"},
      {"id": "68700bfe1ba3d59441c9b14d4f94938b", "name": "Some string", "value": "simplybook"},
      {"id": "ac4c3775f20dcfdea531346ee5bc8ea4", "name": "Date of birth", "value": "1973-03-02"}
    ]
  }'
```

### Production deployment

1. **Build the JAR** (see above)

2. **Run as a systemd service** — create `/etc/systemd/system/validator.service`:

```ini
[Unit]
Description=SimplyBook External Validator
After=network.target

[Service]
ExecStart=/usr/bin/java -jar /var/www/validator/java/build/libs/validator.jar
Restart=always
User=www-data
Environment=VALIDATOR_TOKEN=<your secret>

[Install]
WantedBy=multi-user.target
```

Then enable and start:

```bash
sudo systemctl enable validator
sudo systemctl start validator
```

3. **Put it behind a reverse proxy** (Nginx or Caddy) for HTTPS termination. Example Caddy config:

```
yourdomain.com {
    reverse_proxy localhost:8080
}
```

**Before going live**, disable logging in `java/src/main/java/com/simplybook/validator/ExternalValidator.java`:

```java
private String logFile = null;
```

</details>

---

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

Every response from the validator has `Content-Type: application/json` (unparseable bodies, unknown paths and other methods answer with `{}` too, except Java's default 404 body for unknown paths, and requests that the web server in front or Java's embedded Tomcat rejects as malformed before they reach the validator). Only `POST` is accepted.

| Situation | HTTP status | Body |
|-----------|-------------|------|
| Accept, no changes | 200 | `{}` |
| Accept, change intake form values | 200 | `{"additional_fields": [{"id": "...", "name": "...", "value": "..."}]}` |
| Reject, general error | 200 | `{"errors": ["..."]}` |
| Reject, field-level error | 200 | `{"additional_fields": [{"id": "...", "errors": ["..."]}]}` |
| Missing or wrong `?token` (or no secret configured, PHP) | 403 | `{}` |
| Method other than `POST` | 405 | `{}` (checked before the token) |

> **Never answer `{"errors": []}` to accept a booking** — an empty `errors` list also rejects it. Accept with `{}` (or with the changed `additional_fields`). Likewise do not return a bare JSON array (`[]` or `[{...}]`).

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
3. Enter the URL **including the secret token** in **External Booking Validator** settings, e.g. `https://validator.example.com/?token=<your secret>`

## Security

### URL token

SimplyBook.me does not sign its requests and cannot send custom headers, so the only protection for the endpoint is a **secret token in the validator URL**:

```
https://validator.example.com/?token=<secret>
```

Every implementation compares the `token` query parameter with a secret read from the environment variable `VALIDATOR_TOKEN`, using a constant-time comparison. A missing or wrong token gets `403` with body `{}`.

- Generate a secret of at least 32 characters, e.g. `openssl rand -hex 32` (64 hex characters).
- **If the secret is missing or shorter than 32 characters the validator does not run:** the Node.js, Python, Go and Java servers refuse to start; the PHP script answers every `POST` with `403` (an empty secret never matches an empty token).
- Never commit the secret. Rotate it by changing the environment variable and the URL in SimplyBook.me together.

How to set `VALIDATOR_TOKEN`:

| Language | How |
|----------|-----|
| PHP | Web-server/PHP-FPM environment (`env[VALIDATOR_TOKEN]`, `fastcgi_param`, `SetEnv`), read with `getenv`. Without environment variables (shared hosting): copy [`validator.config.example.php`](validator.config.example.php) to `validator.config.php` **one level above the `php/` web root** (or point `VALIDATOR_CONFIG_FILE` at another path outside the web root). It is git-ignored. |
| Node.js | `export VALIDATOR_TOKEN=...` before `node index.js`, or `VALIDATOR_TOKEN=... pm2 start ...` |
| Python | `export VALIDATOR_TOKEN=...` before `uvicorn` / `gunicorn` |
| Go | `export VALIDATOR_TOKEN=...` before `go run .` / `./validator` |
| Java | `export VALIDATOR_TOKEN=...` before `java -jar ...`, or `Environment=` / `EnvironmentFile=` in the systemd unit |

For systemd prefer `EnvironmentFile=/etc/validator.env` (mode `600`) over `Environment=` in the unit file, which is world-readable.

Test with curl (`$VALIDATOR_TOKEN` must hold the same secret as the server; the examples use port 8080 — Node.js listens on 3000, Python on 8000, and PHP on the port you started it with):

```bash
# no token -> 403 {}
curl -i -X POST http://localhost:8080/ -H 'Content-Type: application/json' -d '{}'
# valid token -> 200
curl -i -X POST "http://localhost:8080/?token=$VALIDATOR_TOKEN" -H 'Content-Type: application/json' -d '{"service_id": 9}'
```

### Other recommendations

- **Always use HTTPS** — the request contains sensitive client data, and the token travels in the URL. HTTPS encrypts the URL in transit, but web-server access logs and proxies may record it, so restrict who can read them or strip the query string from logged URLs. The example apps do not log it themselves: Go logs only the path, and Python runs uvicorn with the access log off
- **Keep log files outside the web root** — default path is `/tmp/external_validator.log`; set to `null`/`None`/`""` to disable in production
- **Respond within a few seconds** — SimplyBook.me has a short timeout
