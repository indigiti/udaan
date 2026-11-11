package digipin

import (
	"errors"
	"fmt"
	"math"
	"strings"
)

const (
	MinLat             = 2.5
	MaxLat             = 38.5
	MinLon             = 63.5
	MaxLon             = 99.5
	OfficialSource     = "India Post / INDIAPOST-gov/digipin"
	OfficialSourceBlob = "02a89afa066a30f5cf2a55dbc2a64e7964efbf30"
	AlgorithmVersion   = "india-post-2026-05-04"
)

var grid = [4][4]byte{
	{'F', 'C', '9', '8'},
	{'J', '3', '2', '7'},
	{'K', '4', '5', '6'},
	{'L', 'M', 'P', 'T'},
}

var ErrCoordinateOutOfRange = errors.New("coordinate outside DIGIPIN bounds")
var ErrInvalidDIGIPIN = errors.New("invalid DIGIPIN")

func Encode(lat, lon float64) (string, error) {
	if math.IsNaN(lat) || math.IsInf(lat, 0) || lat < MinLat || lat > MaxLat {
		return "", fmt.Errorf("%w: latitude must be between %.1f and %.1f", ErrCoordinateOutOfRange, MinLat, MaxLat)
	}
	if math.IsNaN(lon) || math.IsInf(lon, 0) || lon < MinLon || lon > MaxLon {
		return "", fmt.Errorf("%w: longitude must be between %.1f and %.1f", ErrCoordinateOutOfRange, MinLon, MaxLon)
	}

	minLat, maxLat := MinLat, MaxLat
	minLon, maxLon := MinLon, MaxLon
	out := make([]byte, 0, 10)

	for level := 0; level < 10; level++ {
		latDiv := (maxLat - minLat) / 4
		lonDiv := (maxLon - minLon) / 4

		row := 3 - int(math.Floor((lat-minLat)/latDiv))
		col := int(math.Floor((lon-minLon)/lonDiv))
		row = clamp(row, 0, 3)
		col = clamp(col, 0, 3)

		out = append(out, grid[row][col])

		oldMinLat := minLat
		minLat = oldMinLat + latDiv*float64(3-row)
		maxLat = oldMinLat + latDiv*float64(4-row)

		oldMinLon := minLon
		minLon = oldMinLon + lonDiv*float64(col)
		maxLon = minLon + lonDiv
	}

	return string(out), nil
}

type Decoded struct {
	Latitude  float64 `json:"latitude"`
	Longitude float64 `json:"longitude"`
	MinLat    float64 `json:"min_latitude"`
	MaxLat    float64 `json:"max_latitude"`
	MinLon    float64 `json:"min_longitude"`
	MaxLon    float64 `json:"max_longitude"`
}

func Decode(pin string) (Decoded, error) {
	pin = strings.ToUpper(strings.TrimSpace(pin))
	if len(pin) != 10 {
		return Decoded{}, fmt.Errorf("%w: must be exactly 10 characters", ErrInvalidDIGIPIN)
	}

	minLat, maxLat := MinLat, MaxLat
	minLon, maxLon := MinLon, MaxLon

	for i := 0; i < len(pin); i++ {
		row, col, ok := gridPosition(pin[i])
		if !ok {
			return Decoded{}, fmt.Errorf("%w: unsupported character %q at position %d", ErrInvalidDIGIPIN, pin[i], i+1)
		}

		latDiv := (maxLat - minLat) / 4
		lonDiv := (maxLon - minLon) / 4

		lat1 := maxLat - latDiv*float64(row+1)
		lat2 := maxLat - latDiv*float64(row)
		lon1 := minLon + lonDiv*float64(col)
		lon2 := minLon + lonDiv*float64(col+1)

		minLat, maxLat = lat1, lat2
		minLon, maxLon = lon1, lon2
	}

	return Decoded{
		Latitude:  (minLat + maxLat) / 2,
		Longitude: (minLon + maxLon) / 2,
		MinLat:    minLat,
		MaxLat:    maxLat,
		MinLon:    minLon,
		MaxLon:    maxLon,
	}, nil
}

func IsValid(pin string) bool {
	_, err := Decode(pin)
	return err == nil
}

func gridPosition(ch byte) (int, int, bool) {
	for r := 0; r < 4; r++ {
		for c := 0; c < 4; c++ {
			if grid[r][c] == ch {
				return r, c, true
			}
		}
	}
	return -1, -1, false
}

func clamp(v, min, max int) int {
	if v < min {
		return min
	}
	if v > max {
		return max
	}
	return v
}
