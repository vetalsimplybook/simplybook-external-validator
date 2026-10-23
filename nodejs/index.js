import crypto from 'node:crypto';
import express from 'express';
import { ExternalValidator } from './ExternalValidator.js';

// SimplyBook.me cannot sign requests or send custom headers, so the only
// protection is a secret token in the URL (?token=...).
// Refuse to start without a secret of at least 32 characters.
const secret = process.env.VALIDATOR_TOKEN || '';
if (secret.length < 32) {
    console.error('VALIDATOR_TOKEN must be set to a secret of at least 32 characters');
    process.exit(1);
}

// Constant-time comparison; timingSafeEqual needs equal-length buffers.
function tokenMatches(token) {
    if (typeof token !== 'string') return false;
    const given = Buffer.from(token);
    const expected = Buffer.from(secret);
    return given.length === expected.length && crypto.timingSafeEqual(given, expected);
}

const app = express();

/*
// Uncomment for local testing (no HTTP request needed).
// Run with: VALIDATOR_TOKEN=<at least 32 chars> node index.js
const stubToken = process.env.VALIDATOR_TOKEN; // the value you would put in ?token=
const stubData = {
    service_id: 9,
    provider_id: 45,
    client_id: 8123,
    start_datetime: '2021-01-11 11:40:00',
    end_datetime: '2021-01-11 11:45:00',
    count: 1,
    company_login: 'mycompany',
    sheduler_id: null,
    additional_fields: [
        { id: 'ed8f5b7380f7111c592abf6f916fc2d0', name: 'Check number', value: '112233445566' },
        { id: '68700bfe1ba3d59441c9b14d4f94938b', name: 'Some string', value: 'simplybook' },
        { id: 'ac4c3775f20dcfdea531346ee5bc8ea4', name: 'Date of birth', value: '1973-03-02' },
    ],
};
const validator = new ExternalValidator();
console.log(tokenMatches(stubToken) ? JSON.stringify(validator.validate(stubData), null, 2) : '403 {}');
process.exit(0);
*/

// Only POST is accepted
app.all('/', (req, res, next) => {
    if (req.method !== 'POST') {
        return res.status(405).set('Allow', 'POST').json({});
    }
    next();
});

// Reject before parsing the body when the token is wrong
app.use((req, res, next) => {
    if (!tokenMatches(req.query.token)) {
        return res.status(403).json({});
    }
    next();
});

app.use(express.json());

app.post('/', (req, res) => {
    if (!req.body || Object.keys(req.body).length === 0) {
        return res.json({});
    }
    const validator = new ExternalValidator();
    res.json(validator.validate(req.body));
});

// JSON bodies for unparseable requests and unknown paths
app.use((req, res) => res.status(404).json({}));
app.use((err, req, res, next) => res.status(err.status || 400).json({}));

app.listen(3000, () => console.log('Validator listening on http://localhost:3000'));
