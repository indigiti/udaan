package api

import (
	"encoding/json"
	"net/http"
	"net/http/httptest"
	"strings"
	"testing"

	"github.com/indigiti/udaan/services/location-api/internal/postal"
)

func TestEncodeEndpoint(t *testing.T) {
	s := New(Config{Version: "test"}, postal.UnavailableResolver{}, nil)
	r := httptest.NewRequest(http.MethodGet, "/v1/digipin/encode?latitude=13.11179621&longitude=80.20264269", nil)
	w := httptest.NewRecorder()
	s.Handler().ServeHTTP(w, r)
	if w.Code != 200 {
		t.Fatalf("status=%d body=%s", w.Code, w.Body.String())
	}
	var body map[string]any
	if err := json.Unmarshal(w.Body.Bytes(), &body); err != nil {
		t.Fatal(err)
	}
	if body["digipin"] != "4T396F42L7" {
		t.Fatalf("unexpected body %s", w.Body.String())
	}
}

func TestResolveWorksWithoutPostalData(t *testing.T) {
	s := New(Config{Version: "test"}, postal.UnavailableResolver{}, nil)
	r := httptest.NewRequest(http.MethodPost, "/v1/location/resolve", strings.NewReader(`{"latitude":13.11179621,"longitude":80.20264269,"accuracy_m":5}`))
	r.Header.Set("Content-Type", "application/json")
	w := httptest.NewRecorder()
	s.Handler().ServeHTTP(w, r)
	if w.Code != 200 {
		t.Fatalf("status=%d body=%s", w.Code, w.Body.String())
	}
	if !strings.Contains(w.Body.String(), `"code":"4T396F42L7"`) {
		t.Fatal(w.Body.String())
	}
	if !strings.Contains(w.Body.String(), `POSTAL_DATA_NOT_CONFIGURED`) {
		t.Fatal(w.Body.String())
	}
}

func TestAPIKey(t *testing.T) {
	s := New(Config{Version: "test", APIKey: "secret"}, postal.UnavailableResolver{}, nil)
	r := httptest.NewRequest(http.MethodGet, "/v1/digipin/encode?latitude=13.11179621&longitude=80.20264269", nil)
	w := httptest.NewRecorder()
	s.Handler().ServeHTTP(w, r)
	if w.Code != http.StatusUnauthorized {
		t.Fatalf("got %d", w.Code)
	}
}
