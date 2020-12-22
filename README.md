# Simplybook External Plugin Validator

You can validate bookings through the use of an external script. The script can check variables from the booking, and only if conditions are fulfilled, the booking is processed. Additionally the validation script can bring back the information that can be injected into intake form variables.

---
Incoming data example:
```
{
    "service_id":"9",
    "provider_id":"45",
    "client_id":"8123",
    "start_datetime":"2021-01-11 11:40:00",
    "end_datetime":"2021-01-11 11:45:00",
    "count":1,
    "additional_fields":[
        {
            "id":"ed8f5b7380f7111c592abf6f916fc2d0",
            "name":"Check number",
            "value":"112233445566"
        },
        {
            "id":"68700bfe1ba3d59441c9b14d4f94938b",
            "name":"Some string",
            "value":"simplybook"
        },
        {
            "id":"ac4c3775f20dcfdea531346ee5bc8ea4",
            "name":"Date of birth",
            "value":"1973-03-02"
        }
    ]
}
```
---


Output data example (in case of successful validation):
```
{}
```
---



Output data example (in case of successful validation and the information is brought back into intake form variables):
```
{
    "additional_fields":[
        {
            "id":"68700bfe1ba3d59441c9b14d4f94938b",
            "name":"Some string",
            "value":"replaced text"
        }
    ]
}
```
---

Output data example (in case of a general error):
```
{
    "errors":["Error text"]
}
```
---
Output data example (in case of Intake Form error):
```
{
    "additional_fields":[
        {
            "id":"ed8f5b7380f7111c592abf6f916fc2d0",
            "errors":["Incorrect date of birth"]
        }
    ]
}
```
---
