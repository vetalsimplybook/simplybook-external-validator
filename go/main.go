package main

import (
	"crypto/subtle"
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"os"
	"strings"

	"github.com/gin-gonic/gin"
)

func main() {
	// SimplyBook.me cannot sign requests or send custom headers, so the only
	// protection is a secret token in the URL (?token=...).
	// Refuse to start without a secret of at least 32 characters.
	secret := os.Getenv("VALIDATOR_TOKEN")
	if len(secret) < 32 {
		log.Fatal("VALIDATOR_TOKEN must be set to a secret of at least 32 characters")
	}

	// Constant-time comparison (ConstantTimeCompare returns 0 on a length mismatch).
	tokenMatches := func(token string) bool {
		return subtle.ConstantTimeCompare([]byte(token), []byte(secret)) == 1
	}

	/*
	   // Uncomment for local testing (no HTTP request needed).
	   // Run with: VALIDATOR_TOKEN=<at least 32 chars> go run .
	   stubToken := os.Getenv("VALIDATOR_TOKEN") // the value you would put in ?token=
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
	   if !tokenMatches(stubToken) {
	       fmt.Println("403 {}")
	       return
	   }
	   validator := NewExternalValidator()
	   result := validator.Validate(stubData)
	   b, _ := json.MarshalIndent(result, "", "  ")
	   fmt.Println(string(b))
	   return
	*/

	// gin.Default() would log the full URL including ?token=..., so use a logger
	// that drops the query string.
	r := gin.New()
	r.Use(gin.Recovery())
	r.Use(gin.LoggerWithFormatter(func(p gin.LogFormatterParams) string {
		path, _, _ := strings.Cut(p.Path, "?")
		return fmt.Sprintf("[GIN] %s | %3d | %13v | %15s | %-7s %q\n",
			p.TimeStamp.Format("2006/01/02 - 15:04:05"), p.StatusCode, p.Latency, p.ClientIP, p.Method, path)
	}))

	// Only POST is accepted; other methods get 405 with a JSON body
	r.HandleMethodNotAllowed = true
	r.NoMethod(func(c *gin.Context) {
		c.Header("Allow", "POST")
		c.JSON(http.StatusMethodNotAllowed, gin.H{})
	})

	// JSON body for unknown paths too
	r.NoRoute(func(c *gin.Context) {
		c.JSON(http.StatusNotFound, gin.H{})
	})

	r.POST("/", func(c *gin.Context) {
		if !tokenMatches(c.Query("token")) {
			c.JSON(http.StatusForbidden, gin.H{})
			return
		}
		var bookingData map[string]interface{}
		if err := c.ShouldBindJSON(&bookingData); err != nil || len(bookingData) == 0 {
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
