"""Pydantic models for the internal AI service (see docs/INTERNAL-SERVICE-CONTRACT.md)."""

from __future__ import annotations

from datetime import date
from typing import Literal, Optional

from pydantic import BaseModel, Field


# --------------------------------------------------------------------------- scan


class ScanLimits(BaseModel):
    max_pages: int = Field(default=15, ge=1, le=500)
    max_bytes: int = Field(default=3_000_000, ge=10_000, le=64_000_000)
    timeout_seconds: float = Field(default=20.0, gt=0, le=120)


class ScanRequest(BaseModel):
    job_id: str = Field(min_length=1, max_length=128)
    url: str = Field(min_length=1, max_length=2048)
    mode: Literal["LIGHT", "DEEP"] = "LIGHT"
    criteria_version: int = 1
    limits: ScanLimits = Field(default_factory=ScanLimits)


class Evidence(BaseModel):
    kind: str
    excerpt: str
    selector: Optional[str] = None


class Signals(BaseModel):
    is_policy_page: bool = False
    policy_kinds: list[str] = Field(default_factory=list)
    has_data_subject_rights: bool = False
    has_processing_purpose: bool = False
    has_controller_identity: bool = False
    has_contact_channel: bool = False
    collects_personal_data: bool = False
    has_tax_code: bool = False
    has_business_identity: bool = False


class PageResult(BaseModel):
    url: str
    http_status: Optional[int] = None
    title: Optional[str] = None
    fetch_error: Optional[str] = None
    content_hash: str = ""
    snapshot_html: Optional[str] = None
    signals: Signals = Field(default_factory=Signals)
    evidence: list[Evidence] = Field(default_factory=list)


class SiteResult(BaseModel):
    https: bool = False
    tls_valid: bool = False
    security_headers: dict[str, bool] = Field(default_factory=dict)
    cookie_banner: bool = False
    cookie_policy_found: bool = False
    has_login_form: bool = False
    links_checked: int = 0
    broken_links: list[str] = Field(default_factory=list)


class Finding(BaseModel):
    code: str
    severity: Literal["CRITICAL", "HIGH", "MEDIUM", "LOW", "INFO"]
    title: str
    detail: str
    evidence_url: str


class ScanResponse(BaseModel):
    status: Literal["COMPLETED", "BLOCKED", "FAILED"]
    blocked_reason: Optional[str] = None
    final_url: Optional[str] = None
    resolved_ip: Optional[str] = None
    robots_checked: bool = False
    pages: list[PageResult] = Field(default_factory=list)
    site: SiteResult = Field(default_factory=SiteResult)
    findings: list[Finding] = Field(default_factory=list)
    # Additive field (documented in "Ghi chú triển khai"): true when the scan
    # stopped because /internal/scan/{job_id}/cancel was called.
    cancelled: bool = False


# ----------------------------------------------------------------------------- ai


class Source(BaseModel):
    id: str
    doc_number: Optional[str] = None
    doc_title: Optional[str] = None
    article: Optional[str] = None
    clause: Optional[str] = None
    point: Optional[str] = None
    content: str = ""
    effective_from: Optional[date] = None
    official_url: Optional[str] = None


class AnswerRequest(BaseModel):
    question: str = Field(min_length=1, max_length=8000)
    as_of_date: date
    stream: bool = False
    sources: list[Source] = Field(default_factory=list)


class Categories(BaseModel):
    MANDATORY: list[str] = Field(default_factory=list)
    GUIDANCE: list[str] = Field(default_factory=list)
    TECHNICAL: list[str] = Field(default_factory=list)
    EXPERT: list[str] = Field(default_factory=list)


class AnswerResponse(BaseModel):
    answer: str
    cited_source_ids: list[str] = Field(default_factory=list)
    refused: bool = False
    refusal_reason: Optional[str] = None
    model: str = "extractive"
    categories: Categories = Field(default_factory=Categories)
