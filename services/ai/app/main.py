"""FastAPI entrypoint for the internal AI service (port 8100, never published)."""

from __future__ import annotations

import asyncio
import json
import logging
import os
import time
import uuid
from contextvars import ContextVar
from typing import Any, Optional

from fastapi import Depends, FastAPI, HTTPException, Request
from fastapi.responses import StreamingResponse
from pydantic import ValidationError

from .ai import answer_question, provider_settings, sse_events
from .auth import require_signature
from .models import AnswerRequest, ProbeRequest, ProbeResponse, ScanRequest, ScanResponse
from .scan import Fetcher, FetchFailure, request_cancel, run_scan

SERVICE_VERSION = "1"
SCAN_WALL_SECONDS = 275.0

REQUEST_ID: ContextVar[str] = ContextVar("vl_request_id", default="-")
log = logging.getLogger("vl.app")


class _RequestIdFilter(logging.Filter):
    def filter(self, record: logging.LogRecord) -> bool:
        if not hasattr(record, "request_id"):
            record.request_id = REQUEST_ID.get()
        return True


class _JsonFormatter(logging.Formatter):
    """One JSON object per line. Never log bodies, HTML snapshots or PII."""

    def format(self, record: logging.LogRecord) -> str:
        payload = {
            "ts": time.strftime("%Y-%m-%dT%H:%M:%S", time.gmtime(record.created)) + "Z",
            "level": record.levelname,
            "logger": record.name,
            "request_id": getattr(record, "request_id", "-"),
            "message": record.getMessage(),
        }
        if record.exc_info:
            payload["error"] = self.formatException(record.exc_info)
        return json.dumps(payload, ensure_ascii=False)


def configure_logging() -> None:
    handler = logging.StreamHandler()
    handler.setFormatter(_JsonFormatter())
    handler.addFilter(_RequestIdFilter())
    root = logging.getLogger("vl")
    if not root.handlers:
        root.addHandler(handler)
    root.setLevel(os.environ.get("LOG_LEVEL", "INFO").upper())
    root.propagate = False


configure_logging()

app = FastAPI(title="VietLegal Check internal service", version=SERVICE_VERSION)
# Test seams: injected transports keep the suite offline.
app.state.scan_transport = None
app.state.ai_transport = None


@app.middleware("http")
async def request_context(request: Request, call_next):
    request_id = (request.headers.get("X-Request-ID") or uuid.uuid4().hex)[:64]
    token = REQUEST_ID.set(request_id)
    started = time.monotonic()
    status_code = 500
    try:
        response = await call_next(request)
        status_code = response.status_code
        response.headers["X-Request-ID"] = request_id
        return response
    except Exception:
        log.exception("request failed method=%s path=%s", request.method, request.url.path)
        raise
    finally:
        log.info(
            "request method=%s path=%s status=%d duration_ms=%d",
            request.method,
            request.url.path,
            status_code,
            int((time.monotonic() - started) * 1000),
        )
        REQUEST_ID.reset(token)


def _parse_body(body: bytes, model: Any) -> Any:
    try:
        return model.model_validate_json(body)
    except ValidationError as exc:
        raise HTTPException(
            status_code=400,
            detail={"error": "INVALID_REQUEST", "detail": exc.errors(include_url=False, include_context=False)},
        ) from exc
    except ValueError as exc:
        raise HTTPException(status_code=400, detail={"error": "INVALID_REQUEST"}) from exc


@app.get("/health")
async def health() -> dict:
    provider_url, _key, _model = provider_settings()
    return {
        "status": "ok",
        "ai_provider": "openai-compatible" if provider_url else "none",
        "version": SERVICE_VERSION,
    }


@app.post("/internal/scan", response_model=ScanResponse)
async def internal_scan(request: Request, body: bytes = Depends(require_signature)) -> ScanResponse:
    payload: ScanRequest = _parse_body(body, ScanRequest)
    log.info(
        "scan start job_id=%s mode=%s host=%s",
        payload.job_id,
        payload.mode,
        _host_only(payload.url),
    )
    try:
        return await asyncio.wait_for(
            run_scan(payload, transport=request.app.state.scan_transport),
            timeout=SCAN_WALL_SECONDS,
        )
    except asyncio.TimeoutError:
        log.warning("scan timed out job_id=%s", payload.job_id)
        return ScanResponse(status="FAILED")


def _host_only(url: str) -> str:
    from urllib.parse import urlsplit

    try:
        return (urlsplit(url).hostname or "")[:253]
    except ValueError:
        return ""


@app.post("/internal/probe", response_model=ProbeResponse)
async def internal_probe(request: Request, body: bytes = Depends(require_signature)) -> ProbeResponse:
    """Kiểm tra trang chủ có thẻ meta xác minh quyền sở hữu (FR-SCAN-07).

    Chỉ fetch một trang với budget 256 KB và đầy đủ bảo mật SSRF của Fetcher.
    Nội dung trang là dữ liệu không đáng tin; chỉ trích xuất meta tag.
    """
    payload: ProbeRequest = _parse_body(body, ProbeRequest)
    expect = payload.expect.removeprefix("meta:").strip()
    transport = request.app.state.scan_transport
    fetcher = Fetcher(transport=transport, max_bytes=262_144)
    try:
        try:
            result = await fetcher.fetch(payload.url, max_bytes=262_144)
        except Exception as exc:  # BlockedURL, FetchFailure, network
            code = getattr(exc, "code", None) or getattr(exc, "reason", None) or type(exc).__name__
            log.info("probe blocked url=%s reason=%s", _host_only(payload.url), str(code)[:64])
            return ProbeResponse(found=False, fetch_error=str(code)[:64])
        if result.error:
            return ProbeResponse(found=False, status=result.status, fetch_error=result.error)
        from bs4 import BeautifulSoup

        soup = BeautifulSoup(result.body, "html.parser")
        head = soup.find("head") or soup
        found = False
        for meta in head.find_all("meta"):
            name = (meta.get("name") or meta.get("property") or "").strip().lower()
            content = (meta.get("content") or "").strip()
            if name in ("vietlegal-verification", "vlc-domain-verification") and content == expect:
                found = True
                break
        if not found and head is not soup:
            for meta in soup.find_all("meta"):
                name = (meta.get("name") or meta.get("property") or "").strip().lower()
                content = (meta.get("content") or "").strip()
                if name in ("vietlegal-verification", "vlc-domain-verification") and content == expect:
                    found = True
                    break
        log.info("probe url=%s found=%s", _host_only(payload.url), found)
        return ProbeResponse(found=found, status=result.status)
    finally:
        await fetcher.aclose()


@app.post("/internal/scan/{job_id}/cancel")
async def internal_cancel(job_id: str, _body: bytes = Depends(require_signature)) -> dict:
    request_cancel(job_id)
    log.info("scan cancel requested job_id=%s", job_id[:128])
    return {"cancelled": True}


@app.post("/internal/ai/answer")
async def internal_answer(request: Request, body: bytes = Depends(require_signature)):
    payload: AnswerRequest = _parse_body(body, AnswerRequest)
    result = await answer_question(payload, transport=request.app.state.ai_transport)
    log.info(
        "ai answer stream=%s refused=%s model=%s",
        payload.stream,
        result.refused,
        result.model,
    )
    if not payload.stream:
        return result
    return StreamingResponse(
        sse_events(result),
        media_type="text/event-stream",
        headers={"Cache-Control": "no-store", "X-Accel-Buffering": "no"},
    )
