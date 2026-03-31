package main

import (
	"encoding/json"
	"fmt"
	"net/http"

	"github.com/gin-gonic/gin"
)

func main() {
	/*
	   // Uncomment for local testing (no HTTP request needed):
	   stubJSON := `{
	       "service_id": 9,
	       "provider_id": 45,
	       "client_id": 8123,
	       "start_datetime": "2021-01-11 11:40:00",
	       "end_datetime": "2021-01-11 11:45:00",
	       "count": 1,
	       "company_login": "mycompany",
	       "sheduler_id": null,
	       "additional_fields": [
	           {"id": "ed8f5b7380f7111c592abf6f916fc2d0", "name": "Check number", "value": "112233445566"},
	           {"id": "68700bfe1ba3d59441c9b14d4f94938b", "name": "Some string", "value": "simplybook"},
	           {"id": "ac4c3775f20dcfdea531346ee5bc8ea4", "name": "Date of birth", "value": "1973-03-02"}
	       ]
	   }`
	   var stubData map[string]interface{}
	   json.Unmarshal([]byte(stubJSON), &stubData)
	   validator := NewExternalValidator()
	   result := validator.Validate(stubData)
	   b, _ := json.MarshalIndent(result, "", "  ")
	   fmt.Println(string(b))
	   return
	*/

	r := gin.Default()

	r.POST("/", func(c *gin.Context) {
		var bookingData map[string]interface{}
		if err := c.ShouldBindJSON(&bookingData); err != nil || bookingData == nil {
			c.JSON(http.StatusOK, gin.H{})
			return
		}
		validator := NewExternalValidator()
		result := validator.Validate(bookingData)
		c.JSON(http.StatusOK, result)
	})

	fmt.Println("Validator listening on http://localhost:8080")
	r.Run(":8080")
}

// keep json imported when stub is commented out
var _ = json.Marshal
