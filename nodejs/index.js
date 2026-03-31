import express from 'express';
import { ExternalValidator } from './ExternalValidator.js';

const app = express();
app.use(express.json());

/*
// Uncomment for local testing (no HTTP request needed):
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
console.log(JSON.stringify(validator.validate(stubData), null, 2));
process.exit(0);
*/

app.post('/', (req, res) => {
    if (!req.body || Object.keys(req.body).length === 0) {
        return res.json({});
    }
    const validator = new ExternalValidator();
    res.json(validator.validate(req.body));
});

app.listen(3000, () => console.log('Validator listening on http://localhost:3000'));
