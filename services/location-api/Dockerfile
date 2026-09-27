FROM golang:1.22-alpine AS build
WORKDIR /src
COPY go.mod ./
COPY cmd ./cmd
COPY internal ./internal
RUN CGO_ENABLED=0 GOOS=linux go build -trimpath -ldflags="-s -w" -o /out/location-api ./cmd/location-api

FROM gcr.io/distroless/static-debian12:nonroot
COPY --from=build /out/location-api /location-api
EXPOSE 8080
USER nonroot:nonroot
ENTRYPOINT ["/location-api"]
