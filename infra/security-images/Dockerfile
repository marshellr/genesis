FROM golang:1.27.4-alpine@sha256:70bd7ddea8b6cbe5d6943cde279d6b67feb10cc924fb9100d1d8757c1575b280 AS gosu-build
WORKDIR /build
RUN wget -q https://github.com/tianon/gosu/archive/refs/tags/1.19.tar.gz -O /tmp/gosu.tar.gz \
    && echo 'cd9719b775dbfedae53923c9b0dc792b66d42c51e0b36652ed6f747fbadc0164  /tmp/gosu.tar.gz' | sha256sum -c - \
    && tar -xzf /tmp/gosu.tar.gz --strip-components=1 \
    && CGO_ENABLED=0 GOTOOLCHAIN=local go build -trimpath -ldflags='-s -w -X main.Version=1.19' -o /out/gosu .

FROM mariadb:11.4@sha256:a8992b5e5404d11e03c54a31d0c1bdea3fd116d283aa6d1a627551f29045309b AS mariadb
# Same upstream gosu source and interface; replace only the vulnerable old Go build.
COPY --from=gosu-build /out/gosu /usr/local/bin/gosu
RUN gosu nobody test "$(gosu nobody id -u)" != 0

FROM louislam/uptime-kuma:2.5.12-slim@sha256:f2a9bc853deb2a6f37df229649f96d62efc3cb24e38b08eee79234f76613d063 AS kuma-source
FROM golang:1.27.4-alpine@sha256:70bd7ddea8b6cbe5d6943cde279d6b67feb10cc924fb9100d1d8757c1575b280 AS kuma-health-build
COPY --from=kuma-source /app/extra/healthcheck.go /src/healthcheck.go
RUN CGO_ENABLED=0 GOTOOLCHAIN=local go build -trimpath -ldflags='-s -w' -o /out/healthcheck /src/healthcheck.go

FROM kuma-source AS uptime-kuma
WORKDIR /app
# Keep the existing native SQLite binary and application; update only these three
# audited JavaScript dependencies. No dependency lifecycle scripts are executed.
RUN npm pkg set dependencies.jsonata=2.2.1 dependencies.protobufjs=7.5.5 dependencies.tar=7.5.19 overrides.tar=7.5.19 \
    && npm install --omit=dev --ignore-scripts --no-audit --no-fund \
    && rm -rf /root/.npm \
    && node -e 'const db=new (require("@louislam/sqlite3").Database)(":memory:");db.close()'
COPY --from=kuma-health-build /out/healthcheck /app/extra/healthcheck
COPY infra/security-images/test-kuma.cjs /tmp/test-kuma.cjs
RUN node /tmp/test-kuma.cjs && rm /tmp/test-kuma.cjs
