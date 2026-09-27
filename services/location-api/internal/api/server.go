package api

import (
	"crypto/subtle"
	"encoding/json"
	"errors"
	"fmt"
	"log/slog"
	"net/http"
	"strconv"
	"strings"
	"time"

	"github.com/indigiti/udaan/services/location-api/internal/digipin"
	"github.com/indigiti/udaan/services/location-api/internal/postal"
)

type Config struct {
	APIKey  string
	Version string
}

type Server struct {
	cfg    Config
	postal postal.Resolver
	log    *slog.Logger
	mux    *http.ServeMux
}

func New(cfg Config, resolver postal.Resolver, logger *slog.Logger) *Server {
	if logger == nil {
		logger = slog.Default()
	}
	if resolver == nil {
		resolver = postal.UnavailableResolver{}
	}
	s := &Server{cfg: cfg, postal: resolver, log: logger, mux: http.NewServeMux()}
	s.routes()
	return s
}

func (s *Server) Handler() http.Handler {
	var h http.Handler = s.mux
	h = s.auth(h)
	h = s.recover(h)
	h = s.requestLog(h)
	return h
}

func (s *Server) routes() {
	s.mux.HandleFunc("GET /health", s.health)
	s.mux.HandleFunc("GET /ready", s.ready)
	s.mux.HandleFunc("GET /version", s.version)
	s.mux.HandleFunc("GET /v1/digipin/encode", s.encode)
	s.mux.HandleFunc("GET /v1/digipin/decode", s.decode)
	s.mux.HandleFunc("POST /v1/location/resolve", s.resolve)
}

func (s *Server) health(w http.ResponseWriter, _ *http.Request) {
	writeJSON(w, http.StatusOK, map[string]any{"ok": true, "service": "location-api"})
}

func (s *Server) ready(w http.ResponseWriter, r *http.Request) {
	postalReady := s.postal.Ready(r.Context()) == nil
	writeJSON(w, http.StatusOK, map[string]any{
		"ok":      true,
		"digipin": true,
		"postal":  map[string]any{"ready": postalReady, "resolver": s.postal.Name()},
	})
}

func (s *Server) version(w http.ResponseWriter, _ *http.Request) {
	writeJSON(w, http.StatusOK, map[string]any{
		"ok":                  true,
		"service_version":     s.cfg.Version,
		"digipin_algorithm":   digipin.AlgorithmVersion,
		"digipin_source":      digipin.OfficialSource,
		"digipin_source_blob": digipin.OfficialSourceBlob,
	})
}

func (s *Server) encode(w http.ResponseWriter, r *http.Request) {
	lat, err := parseFloatQuery(r, "latitude")
	if err != nil {
		problem(w, http.StatusBadRequest, "INVALID_LATITUDE", err.Error())
		return
	}
	lon, err := parseFloatQuery(r, "longitude")
	if err != nil {
		problem(w, http.StatusBadRequest, "INVALID_LONGITUDE", err.Error())
		return
	}
	pin, err := digipin.Encode(lat, lon)
	if err != nil {
		problem(w, http.StatusUnprocessableEntity, "COORDINATE_OUT_OF_RANGE", err.Error())
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{"ok": true, "digipin": pin, "algorithm_version": digipin.AlgorithmVersion})
}

func (s *Server) decode(w http.ResponseWriter, r *http.Request) {
	pin := r.URL.Query().Get("digipin")
	decoded, err := digipin.Decode(pin)
	if err != nil {
		problem(w, http.StatusBadRequest, "INVALID_DIGIPIN", err.Error())
		return
	}
	writeJSON(w, http.StatusOK, map[string]any{
		"ok":      true,
		"digipin": strings.ToUpper(strings.TrimSpace(pin)),
		"location": map[string]any{
			"latitude":  round6(decoded.Latitude),
			"longitude": round6(decoded.Longitude),
		},
		"cell": map[string]any{
			"min_latitude":  decoded.MinLat,
			"max_latitude":  decoded.MaxLat,
			"min_longitude": decoded.MinLon,
			"max_longitude": decoded.MaxLon,
		},
		"algorithm_version": digipin.AlgorithmVersion,
	})
}

type resolveRequest struct {
	Latitude  *float64 `json:"latitude"`
	Longitude *float64 `json:"longitude"`
	AccuracyM *float64 `json:"accuracy_m,omitempty"`
}

func (s *Server) resolve(w http.ResponseWriter, r *http.Request) {
	var req resolveRequest
	dec := json.NewDecoder(http.MaxBytesReader(w, r.Body, 64<<10))
	dec.DisallowUnknownFields()
	if err := dec.Decode(&req); err != nil {
		problem(w, http.StatusBadRequest, "INVALID_JSON", err.Error())
		return
	}
	if req.Latitude == nil || req.Longitude == nil {
		problem(w, http.StatusBadRequest, "MISSING_COORDINATES", "latitude and longitude are required")
		return
	}
	if req.AccuracyM != nil && *req.AccuracyM < 0 {
		problem(w, http.StatusBadRequest, "INVALID_ACCURACY", "accuracy_m cannot be negative")
		return
	}

	pin, err := digipin.Encode(*req.Latitude, *req.Longitude)
	if err != nil {
		problem(w, http.StatusUnprocessableEntity, "COORDINATE_OUT_OF_RANGE", err.Error())
		return
	}

	coords := map[string]any{"latitude": *req.Latitude, "longitude": *req.Longitude}
	resp := map[string]any{
		"ok":          true,
		"coordinates": coords,
		"digipin": map[string]any{
			"code":              pin,
			"source":            "INDIA_POST_ALGORITHM",
			"algorithm_version": digipin.AlgorithmVersion,
		},
	}
	if req.AccuracyM != nil {
		coords["accuracy_m"] = *req.AccuracyM
		coords["accuracy_class"] = accuracyClass(*req.AccuracyM)
		if *req.AccuracyM > 100 {
			resp["warning"] = "LOW_LOCATION_ACCURACY"
		}
	}

	postalLocation, pErr := s.postal.Resolve(r.Context(), *req.Latitude, *req.Longitude)
	switch {
	case pErr == nil:
		resp["postal"] = postalLocation
	case errors.Is(pErr, postal.ErrUnavailable):
		resp["postal"] = map[string]any{"available": false, "resolver": s.postal.Name(), "reason": "POSTAL_DATA_NOT_CONFIGURED"}
	case errors.Is(pErr, postal.ErrNotFound):
		resp["postal"] = map[string]any{"available": false, "resolver": s.postal.Name(), "reason": "PINCODE_NOT_FOUND"}
	default:
		s.log.Error("postal resolve failed", "error", pErr)
		resp["postal"] = map[string]any{"available": false, "resolver": s.postal.Name(), "reason": "POSTAL_LOOKUP_ERROR"}
	}
	writeJSON(w, http.StatusOK, resp)
}

func (s *Server) auth(next http.Handler) http.Handler {
	if s.cfg.APIKey == "" {
		return next
	}
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		if r.URL.Path == "/health" || r.URL.Path == "/ready" || r.URL.Path == "/version" {
			next.ServeHTTP(w, r)
			return
		}
		got := r.Header.Get("X-API-Key")
		if len(got) != len(s.cfg.APIKey) || subtle.ConstantTimeCompare([]byte(got), []byte(s.cfg.APIKey)) != 1 {
			problem(w, http.StatusUnauthorized, "UNAUTHORIZED", "valid X-API-Key required")
			return
		}
		next.ServeHTTP(w, r)
	})
}

func (s *Server) recover(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		defer func() {
			if v := recover(); v != nil {
				s.log.Error("panic recovered", "panic", v)
				problem(w, http.StatusInternalServerError, "INTERNAL_ERROR", "internal server error")
			}
		}()
		next.ServeHTTP(w, r)
	})
}

func (s *Server) requestLog(next http.Handler) http.Handler {
	return http.HandlerFunc(func(w http.ResponseWriter, r *http.Request) {
		start := time.Now()
		next.ServeHTTP(w, r)
		s.log.Info("request", "method", r.Method, "path", r.URL.Path, "duration_ms", time.Since(start).Milliseconds())
	})
}

func parseFloatQuery(r *http.Request, name string) (float64, error) {
	v := strings.TrimSpace(r.URL.Query().Get(name))
	if v == "" {
		return 0, fmt.Errorf("%s is required", name)
	}
	n, err := strconv.ParseFloat(v, 64)
	if err != nil {
		return 0, fmt.Errorf("%s must be a number", name)
	}
	return n, nil
}

func accuracyClass(v float64) string {
	switch {
	case v <= 10:
		return "excellent"
	case v <= 25:
		return "good"
	case v <= 100:
		return "usable"
	case v <= 500:
		return "low"
	default:
		return "unreliable"
	}
}

func round6(v float64) float64 {
	n, _ := strconv.ParseFloat(fmt.Sprintf("%.6f", v), 64)
	return n
}

func writeJSON(w http.ResponseWriter, status int, v any) {
	w.Header().Set("Content-Type", "application/json; charset=utf-8")
	w.Header().Set("Cache-Control", "no-store")
	w.WriteHeader(status)
	_ = json.NewEncoder(w).Encode(v)
}

func problem(w http.ResponseWriter, status int, code, message string) {
	writeJSON(w, status, map[string]any{"ok": false, "error": map[string]string{"code": code, "message": message}})
}
