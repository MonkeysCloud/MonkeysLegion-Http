# Roadmap

## Planned

- **HTTP/2 support** in the emitter (h2c / protocol negotiation)
- **Async request handling** (Fibers / Swoole adapters)
- **Middleware plugin API** — third-party middleware registration & auto-discovery
- **Request/response body streaming adapters** for large uploads/downloads (PSR-7 streams backed by files)
- **Observability**: distributed tracing headers (W3C `traceparent`) + OpenTelemetry spans
- **Caching layer**: PSR-6 cache adapter for ETag/rate-limit state
- **Content negotiation refinements**: charset & encoding negotiation (`Accept-Charset`, `Accept-Encoding`)
- **Performance benchmarks** in CI (latency/throughput regression guard)
- **Server-sent events (SSE) emitter**
