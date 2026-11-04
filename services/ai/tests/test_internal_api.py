"""
Kiểm tra dịch vụ nội bộ (SRS 6.4, docs/INTERNAL-SERVICE-CONTRACT.md).

Mock transport thay vì gọi mạng thật: mọi lưới SSRF phải được xác nhận bằng tham số,
không bao giờ gọi ra Internet trong test.
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


@pytest.fixture(autouse=True)
def service_key(monkeypatch):
    monkeypatch.setenv("AI_SERVICE_KEY", SERVICE_KEY)


@pytest.fixture()
def client():
    return TestClient(app)


def signed_post(client: TestClient, path: str, body: dict) -> httpx.Response:
    """Ký đúng trên raw bytes gửi đi, giống Laravel InternalServiceClient."""
    raw = json.dumps(body, ensure_ascii=False).encode("utf-8")
    ts = str(int(time.time()))
    sig = hmac.new(SERVICE_KEY.encode(), ts.encode() + b"." + raw, hashlib.sha256).hexdigest()
    return client.post(
        path,
        content=raw,
        headers={"X-VL-Timestamp": ts, "X-VL-Signature": sig, "Content-Type": "application/json"},
    )


def blocked_response(response: httpx.Response) -> dict:
    assert response.status_code == 200, response.text
    payload = response.json()
    assert payload["status"] == "BLOCKED", payload
    return payload


def test_health_open(client):
    response = client.get("/health")
    assert response.status_code == 200
    assert response.json()["status"] == "ok"


def test_missing_signature_rejected(client):
    response = client.post("/internal/scan", json={"job_id": "x", "url": "https://example.vn"})
    assert response.status_code == 401


def test_bad_signature_rejected(client):
    raw = json.dumps({"job_id": "x", "url": "https://example.vn"}).encode()
    ts = str(int(time.time()))
    sig = hmac.new(b"wrong-key", ts.encode() + b"." + raw, hashlib.sha256).hexdigest()
    response = client.post(
        "/internal/scan",
        content=raw,
        headers={"X-VL-Timestamp": ts, "X-VL-Signature": sig, "Content-Type": "application/json"},
    )
    assert response.status_code == 401


def test_stale_timestamp_rejected(client):
    raw = json.dumps({"job_id": "x", "url": "https://example.vn"}).encode()
    ts = str(int(time.time()) - 400)  # ngoài cửa sổ 300 giây
    sig = hmac.new(SERVICE_KEY.encode(), ts.encode() + b"." + raw, hashlib.sha256).hexdigest()
    response = client.post(
        "/internal/scan",
        content=raw,
        headers={"X-VL-Timestamp": ts, "X-VL-Signature": sig, "Content-Type": "application/json"},
    )
    assert response.status_code == 401


def test_private_address_blocked(client):
    payload = blocked_response(signed_post(client, "/internal/scan", {"job_id": "j1", "url": "http://10.0.0.1/admin"}))
    assert payload["blocked_reason"] == "PRIVATE_ADDRESS"


def test_loopback_address_blocked(client):
    payload = blocked_response(signed_post(client, "/internal/scan", {"job_id": "j1b", "url": "http://127.0.0.1/"}))
    assert payload["blocked_reason"] == "PRIVATE_ADDRESS"


def test_metadata_address_blocked(client):
    payload = blocked_response(
        signed_post(client, "/internal/scan", {"job_id": "j2", "url": "http://169.254.169.254/latest/meta-data/"})
    )
    assert payload["blocked_reason"] == "METADATA_ADDRESS"


def test_alibaba_metadata_blocked(client):
    payload = blocked_response(
        signed_post(client, "/internal/scan", {"job_id": "j2b", "url": "http://100.100.100.200/latest/meta-data/"})
    )
    assert payload["blocked_reason"] == "METADATA_ADDRESS"


def test_invalid_url_blocked(client):
    payload = blocked_response(signed_post(client, "/internal/scan", {"job_id": "j3", "url": "http://user:pass@example.vn/"}))
    assert payload["blocked_reason"] == "INVALID_URL"

def test_no_scheme_blocked(client):
    payload = blocked_response(signed_post(client, "/internal/scan", {"job_id": "j3b", "url": "example.vn/"}))
    assert payload["blocked_reason"] == "UNSUPPORTED_SCHEME"


def test_unsupported_scheme_blocked(client):
    payload = blocked_response(signed_post(client, "/internal/scan", {"job_id": "j4", "url": "ftp://example.vn/file"}))
    assert payload["blocked_reason"] == "UNSUPPORTED_SCHEME"


def test_unsupported_port_blocked(client):
    payload = blocked_response(signed_post(client, "/internal/scan", {"job_id": "j5", "url": "http://example.vn:22/"}))
    assert payload["blocked_reason"] == "UNSUPPORTED_PORT"


def test_answer_refuses_without_sources(client):
    response = signed_post(
        client,
        "/internal/ai/answer",
        {"question": "Nghĩa vụ xuất hóa đơn điện tử?", "as_of_date": "2026-01-01", "stream": False, "sources": []},
    )
    assert response.status_code == 200
    assert response.json()["refused"] is True


def test_answer_extractive_cites_sources(client):
    response = signed_post(
        client,
        "/internal/ai/answer",
        {
            "question": "Khi nào phải xin đồng ý xử lý dữ liệu cá nhân?",
            "as_of_date": "2026-01-01",
            "stream": False,
            "sources": [
                {
                    "id": "p1",
                    "doc_number": "LUAT-BVDS-2023",
                    "doc_title": "Luật Bảo vệ dữ liệu cá nhân 2023",
                    "article": "Điều 11",
                    "content": "Xử lý dữ liệu cá nhân phải có đồng ý của chủ thể dữ liệu; đồng ý phải tự nguyện và có thể rút lại.",
                }
            ],
        },
    )
    assert response.status_code == 200
    payload = response.json()
    assert payload["refused"] is False
    assert "p1" in payload["cited_source_ids"]
    assert "Điều 11" in payload["answer"] or "[1]" in payload["answer"]


def test_offline_transport_never_calls_network():
    """Test seam: mọi request qua transport đều phải đi qua mock, không ra Internet."""
    def boom(request: httpx.Request) -> httpx.Response:
        raise AssertionError(f"Test gọi mạng thật: {request.url}")

    transport = httpx.MockTransport(boom)
    probe = httpx.AsyncClient(transport=transport, trust_env=False)
    assert probe._transport is transport

    async def handler(request: httpx.Request) -> httpx.Response:
        return httpx.Response(200, text="<html>ok</html>", request=request)

    safe = httpx.MockTransport(handler)
    app.state.scan_transport = safe
    try:
        response = signed_post(
            client_with(safe),
            "/internal/scan",
            {"job_id": "j6", "url": "https://example.vn/", "mode": "LIGHT"},
        )
        assert response.status_code == 200
    finally:
        app.state.scan_transport = None


def client_with(transport) -> TestClient:
    app.state.scan_transport = transport
    return TestClient(app)
