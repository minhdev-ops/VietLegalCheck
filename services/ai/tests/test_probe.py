"""
Kiểm tra endpoint /internal/probe (FR-SCAN-07): đối chiếu meta tag xác minh sở hữu.

Mock transport thay vì gọi mạng thật.
"""

from __future__ import annotations

import hashlib
import hmac
import json
import time

import httpx
import pytest
from fastapi.testclient import TestClient

from app.main import app

SERVICE_KEY = "test-internal-key"

PAGE = (
    "<html><head><title>Site</title>"
    '<meta name="vietlegal-verification" content="{token}">'
    "</head><body>body</body></html>"
)


def signed_post(client: TestClient, path: str, body: dict) -> httpx.Response:
    raw = json.dumps(body, ensure_ascii=False).encode("utf-8")
    ts = str(int(time.time()))
    sig = hmac.new(SERVICE_KEY.encode(), ts.encode() + b"." + raw, hashlib.sha256).hexdigest()
    return client.post(
        path,
        content=raw,
        headers={"X-VL-Timestamp": ts, "X-VL-Signature": sig, "Content-Type": "application/json"},
    )


def _client(monkeypatch) -> TestClient:
    monkeypatch.setenv("AI_SERVICE_KEY", SERVICE_KEY)
    return TestClient(app)


@pytest.fixture(autouse=True)
def _reset_transport():
    yield
    app.state.scan_transport = None


def _serve(html: str) -> None:
    """Phục vụ trang cố định qua mock transport của app state (không gọi mạng)."""
    def handler(request: httpx.Request) -> httpx.Response:
        return httpx.Response(200, text=html, request=request)

    app.state.scan_transport = httpx.MockTransport(handler)



def test_probe_matches_meta_tag(monkeypatch):
    client = _client(monkeypatch)
    _serve(PAGE.format(token="vlc-AAA"))
    response = signed_post(
        client,
        "/internal/probe",
        {"url": "https://example.vn/", "expect": "meta:vlc-AAA"},
    )
    assert response.status_code == 200, response.text
    payload = response.json()
    assert payload["found"] is True
    assert payload["status"] == 200
def test_probe_matches_meta_tag(monkeypatch):
    client = _client(monkeypatch)
    _serve(PAGE.format(token="vlc-AAA"))
    response = signed_post(
        client,
        "/internal/probe",
        {"url": "https://example.vn/", "expect": "meta:vlc-AAA"},
    )
    assert response.status_code == 200
    payload = response.json()
    assert payload["found"] is True
    assert payload["status"] == 200


def test_probe_mismatched_token_not_found(monkeypatch):
    client = _client(monkeypatch)
    _serve(PAGE.format(token="vlc-AAA"))
    response = signed_post(
        client,
        "/internal/probe",
        {"url": "https://example.vn/", "expect": "meta:vlc-WRONG"},
    )
    assert response.status_code == 200
    assert response.json()["found"] is False


def test_probe_missing_meta_tag(monkeypatch):
    client = _client(monkeypatch)
    _serve("<html><head><title>khong co meta</title></head></html>")
    response = signed_post(
        client,
        "/internal/probe",
        {"url": "https://example.vn/", "expect": "meta:vlc-AAA"},
    )
    assert response.status_code == 200
    assert response.json()["found"] is False


def test_probe_private_address_blocked(monkeypatch):
    client = _client(monkeypatch)
    response = signed_post(
        client,
        "/internal/probe",
        {"url": "http://169.254.169.254/latest/meta-data/", "expect": "meta:x"},
    )
    assert response.status_code == 200
    payload = response.json()
    assert payload["found"] is False
    assert payload["fetch_error"]
