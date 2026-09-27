package digipin

import (
	"math"
	"testing"
)

func TestOfficialVector(t *testing.T) {
	got, err := Encode(13.11179621, 80.20264269)
	if err != nil {
		t.Fatal(err)
	}
	if got != "4T396F42L7" {
		t.Fatalf("got %s want 4T396F42L7", got)
	}
}

func TestDecodeOfficialVector(t *testing.T) {
	d, err := Decode("4T396F42L7")
	if err != nil {
		t.Fatal(err)
	}
	if math.Abs(d.Latitude-13.11179621) > 0.00005 || math.Abs(d.Longitude-80.20264269) > 0.00005 {
		t.Fatalf("decoded center too far away: %+v", d)
	}
}

func TestRoundTrip(t *testing.T) {
	samples := [][2]float64{{13.11179621, 80.20264269}, {18.5204303, 73.8567437}, {28.6139, 77.2090}, {2.5, 63.5}, {38.5, 99.5}}
	for _, s := range samples {
		pin, err := Encode(s[0], s[1])
		if err != nil {
			t.Fatal(err)
		}
		d, err := Decode(pin)
		if err != nil {
			t.Fatal(err)
		}
		if s[0] < d.MinLat || s[0] > d.MaxLat || s[1] < d.MinLon || s[1] > d.MaxLon {
			t.Fatalf("point %v not inside decoded cell %+v (%s)", s, d, pin)
		}
	}
}

func TestRejectInvalid(t *testing.T) {
	if _, err := Decode("ABC"); err == nil {
		t.Fatal("expected invalid length")
	}
	if _, err := Decode("AAAAAAAAAA"); err == nil {
		t.Fatal("expected invalid charset")
	}
	if _, err := Encode(0, 0); err == nil {
		t.Fatal("expected bounds error")
	}
}
