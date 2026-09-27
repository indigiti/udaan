package postal

import (
	"context"
	"errors"
)

var ErrUnavailable = errors.New("postal resolver unavailable")
var ErrNotFound = errors.New("postal location not found")

type Location struct {
	PINCode    string  `json:"pincode,omitempty"`
	OfficeName string  `json:"post_office,omitempty"`
	District   string  `json:"district,omitempty"`
	State      string  `json:"state,omitempty"`
	Country    string  `json:"country,omitempty"`
	Source     string  `json:"source,omitempty"`
	Confidence float64 `json:"confidence,omitempty"`
}

type Resolver interface {
	Resolve(ctx context.Context, latitude, longitude float64) (Location, error)
	Ready(ctx context.Context) error
	Name() string
}

type UnavailableResolver struct{}

func (UnavailableResolver) Resolve(context.Context, float64, float64) (Location, error) {
	return Location{}, ErrUnavailable
}
func (UnavailableResolver) Ready(context.Context) error { return ErrUnavailable }
func (UnavailableResolver) Name() string                 { return "disabled" }
