"""HMAC-SHA256 request authentication shared with Laravel (SRS 6.4)."""

from __future__ import annotations

import hashlib
import hmac
import os
import time
from typing import Optional

from fastapi import HTTPException, Request

MAX_SKEW_SECONDS = 300


def service_key() -> str:
    return os.environ.get("AI_SERVICE_KEY", "")


def compute_signature(key: str, timestamp: str, body: bytes) -> str:
    """hex(hmac_sha256(key, f"{timestamp}.{raw_body}"))"""
    mac = hmac.new(key.encode("utf-8"), timestamp.encode("utf-8") + b"." + body, hashlib.sha256)
    return mac.hexdigest()


def verify_signature(
    key: str,
    timestamp: Optional[str],
    signature: Optional[str],
    body: bytes,
    now: Optional[float] = None,
) -> Optional[str]:
    """Return None when the request is authentic, otherwise a short reason code."""
    if not key:
        return "key_not_configured"
    if not timestamp or not signature:
        return "missing_header"
    try:
        ts = int(timestamp.strip())
    except ValueError:
        return "bad_timestamp"
    if ts <= 0:
        return "bad_timestamp"
    current = time.time() if now is None else now
    if abs(current - ts) > MAX_SKEW_SECONDS:
        return "stale_timestamp"
    expected = compute_signature(key, timestamp.strip(), body)
    if not hmac.compare_digest(expected, signature.strip().lower()):
        return "bad_signature"
    return None


async def require_signature(request: Request) -> bytes:
    """FastAPI dependency: reject the request unless the HMAC envelope is valid.

    Returns the raw body so handlers do not have to re-read the stream.
    """
    body = await request.body()
    reason = verify_signature(
        service_key(),
        request.headers.get("X-VL-Timestamp"),
        request.headers.get("X-VL-Signature"),
        body,
    )
    if reason is not None:
        raise HTTPException(status_code=401, detail={"error": "UNAUTHORIZED", "reason": reason})
    return body
