"""Vietnamese-safe page analysis: criteria signals, evidence and findings.

All page content is untrusted input. Nothing here executes or interprets page
content as code; it only classifies text and links.
"""

from __future__ import annotations

import re
import unicodedata
from dataclasses import dataclass, field
from typing import Iterable, Optional
from urllib.parse import urljoin, urlsplit, urlunsplit

from bs4 import BeautifulSoup

from .models import Finding, PageResult, SiteResult

try:  # lxml is pinned in the image; html.parser keeps host runs dependency-light
    import lxml  # noqa: F401

    _PARSER = "lxml"
except Exception:  # pragma: no cover - fallback only
    _PARSER = "html.parser"

USER_AGENT = "VietLegalCheckBot/1.0 (+https://vietlegal.local/bot)"
SNAPSHOT_MAX_CHARS = 200_000
EXCERPT_MAX_CHARS = 300
EVIDENCE_MAX_ITEMS = 12

SECURITY_HEADERS = (
    "strict-transport-security",
    "content-security-policy",
    "x-content-type-options",
    "x-frame-options",
    "referrer-policy",
)


# ------------------------------------------------------------------ text helpers


def normalize_vi(text: str) -> str:
    """Lowercase, strip diacritics, collapse punctuation/whitespace.

    "Chính sách bảo mật" -> "chinh sach bao mat"; the result is used for
    keyword matching only, never for output.
    """
    if not text:
        return ""
    lowered = text.lower().replace("đ", "d")
    decomposed = unicodedata.normalize("NFD", lowered)
    stripped = "".join(ch for ch in decomposed if unicodedata.category(ch) != "Mn")
    stripped = unicodedata.normalize("NFC", stripped)
    return re.sub(r"[^0-9a-z]+", " ", stripped).strip()


def _collapse(text: Optional[str]) -> str:
    return re.sub(r"\s+", " ", (text or "")).strip()


def _sentences(text: str) -> list[str]:
    parts = re.split(r"(?<=[.!?;])\s+|\n{2,}|\r|\u2022", text or "")
    return [p.strip() for p in parts if p and p.strip()]


def _first_excerpt(sentences: Iterable[str], keywords: Iterable[str]) -> Optional[str]:
    keys = tuple(keywords)
    for sentence in sentences:
        norm = normalize_vi(sentence)
        if any(k in norm for k in keys):
            return _collapse(sentence)[:EXCERPT_MAX_CHARS]
    return None


# ------------------------------------------------------------------ vocabulary

PRIVACY_KW = (
    "chinh sach bao mat",
    "bao mat thong tin ca nhan",
    "bao ve du lieu ca nhan",
    "quyen rieng tu",
    "privacy policy",
    "chinh sach du lieu",
    "chinh sach quyen rieng tu",
    "thu thap va su dung thong tin",
)
TERMS_KW = (
    "dieu khoan su dung",
    "dieu khoan dich vu",
    "dieu khoan va dieu kien",
    "terms of service",
    "terms and conditions",
    "quy che hoat dong",
    "chinh sach su dung",
    "dieu kien giao dich",
)
COOKIE_KW = ("chinh sach cookie", "cookie policy", "su dung cookie", "cookie")
CONTACT_KW = ("lien he", "contact", "ho tro khach hang", "thong tin lien he")

RIGHTS_KW = (
    "quyen cua chu the du lieu",
    "quyen truy cap thong tin",
    "quyen chinh sua thong tin",
    "quyen xoa du lieu",
    "quyen xoa thong tin",
    "rut lai su dong y",
    "quyen phan doi",
    "quyen han che xu ly",
    "quyen khieu nai",
    "yeu cau xoa thong tin",
    "quyen chuyen du lieu",
)
PURPOSE_KW = (
    "muc dich xu ly",
    "muc dich thu thap",
    "muc dich su dung",
    "su dung thong tin",
    "muc dich cung cap",
    "nham muc dich",
    "de phuc vu",
)
CONTROLLER_KW = (
    "cong ty trach nhiem huu han",
    "cong ty tnhh",
    "cong ty co phan",
    "doanh nghiep tu nhan",
    "ho kinh doanh",
    "don vi kiem soat du lieu",
    "ben kiem soat du lieu",
    "chu quan website",
    "nha cung cap dich vu",
)
BUSINESS_KW = (
    "giay phep kinh doanh",
    "giay chung nhan dang ky doanh nghiep",
    "dang ky kinh doanh",
    "ma so doanh nghiep",
    "ma so thue",
    "noi dang ky kinh doanh",
    "giay phep",
)
TAX_KW = ("ma so thue", "mst", "ma so doanh nghiep")
COOKIE_BANNER_KW = ("su dung cookie", "cookie", "chap nhan", "dong y", "accept", "tu choi")

EMAIL_RE = re.compile(r"[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}")
PHONE_RE = re.compile(r"(?:\+?84|0)[\s.\-]?\d{3}[\s.\-]?\d{3}[\s.\-]?\d{2,4}")
TAX_RE = re.compile(r"(?:ma so thue|mst|ma so doanh nghiep)[^0-9]{0,20}(\d{10}(?:-?\d{3})?)")
TAX_BARE_RE = re.compile(r"\b\d{10}(?:-\d{3})?\b")

POLICY_LINK_KINDS = (
    ("PRIVACY", PRIVACY_KW),
    ("TERMS", TERMS_KW),
    ("COOKIE", COOKIE_KW),
    ("CONTACT", CONTACT_KW),
)


def policy_link_kind(text: str, href: str = "") -> Optional[str]:
    """Classify an anchor (text + url) as a policy/contact link."""
    probe = normalize_vi(f"{text} {href.replace('-', ' ').replace('_', ' ')}")
    if not probe:
        return None
    for kind, keywords in POLICY_LINK_KINDS:
        for kw in keywords:
            if kw == "cookie":
                continue
            if kw in probe:
                return kind
    if "cookie" in probe:
        return "COOKIE"
    return None


# ------------------------------------------------------------------ page analysis


@dataclass
class PageAnalysis:
    title: Optional[str]
    signals: dict
    evidence: list[dict] = field(default_factory=list)
    links: list[tuple[str, str]] = field(default_factory=list)
    has_login_form: bool = False
    cookie_banner: bool = False
    headings: list[str] = field(default_factory=list)
    wants_cookie_policy: bool = False

    @property
    def is_policy_page(self) -> bool:
        return bool(self.signals.get("policy_kinds"))


def _clean_url(url: str) -> Optional[str]:
    try:
        parts = urlsplit(url)
    except ValueError:
        return None
    if parts.scheme not in ("http", "https") or not parts.hostname:
        return None
    path = parts.path or "/"
    return urlunsplit((parts.scheme, parts.netloc, path, parts.query, ""))


def _extract_links(soup: BeautifulSoup, page_url: str) -> list[tuple[str, str]]:
    seen: set[str] = set()
    links: list[tuple[str, str]] = []
    for anchor in soup.find_all("a", href=True):
        href = (anchor.get("href") or "").strip()
        if not href or href.startswith(("#", "mailto:", "tel:", "javascript:", "data:")):
            continue
        try:
            absolute = _clean_url(urljoin(page_url, href))
        except ValueError:
            continue
        if not absolute or absolute in seen:
            continue
        seen.add(absolute)
        links.append((absolute, _collapse(anchor.get_text(" "))[:120]))
    return links


def _form_facts(soup: BeautifulSoup) -> tuple[bool, bool]:
    """(collects_personal_data, has_login_form)"""
    collects = False
    login = False
    personal_names = ("email", "mail", "phone", "sdt", "dien-thoai", "dienthoai", "name", "ten", "ho-ten", "hoten", "address", "dia-chi", "diachi")
    for form in soup.find_all("form"):
        fields = form.find_all(["input", "textarea", "select"])
        if not fields:
            continue
        collects = True
        for element in fields:
            if element.name == "input":
                input_type = (element.get("type") or "text").lower()
                if input_type in ("password", "email", "tel"):
                    collects = True
                if input_type == "password":
                    login = True
                ident = normalize_vi(f"{element.get('name') or ''} {element.get('id') or ''} {element.get('placeholder') or ''}")
                if any(token in ident.replace(" ", "-") for token in personal_names):
                    collects = True
            elif element.name == "textarea":
                collects = True
    return collects, login


def _cookie_banner_present(soup: BeautifulSoup, body_norm: str) -> bool:
    for element in soup.find_all(True):
        ident = f"{element.get('id') or ''} {element.get('class') or ''}".lower()
        if "cookie" not in ident:
            continue
        text = _collapse(element.get_text(" "))
        if len(text) <= 600:
            return True
    return ("su dung cookie" in body_norm or "cookie policy" in body_norm) and any(
        token in body_norm for token in ("chap nhan", "dong y", "accept", "tu choi")
    )


def analyze_page(html: str, page_url: str) -> PageAnalysis:
    soup = BeautifulSoup(html or "", _PARSER)

    title_tag = soup.find("title")
    title = _collapse(title_tag.get_text(" "))[:300] if title_tag else None
    headings = [_collapse(h.get_text(" "))[:200] for h in soup.find_all(["h1", "h2"])]
    headings = [h for h in headings if h][:25]

    body_text = _collapse(soup.get_text(" "))
    body_norm = normalize_vi(body_text)
    heading_norm = normalize_vi(" ".join(headings + ([title] if title else [])))
    path_norm = normalize_vi(urlsplit(page_url).path.replace("-", " ").replace("_", " "))

    kinds: list[str] = []
    for kind, keywords in (("PRIVACY", PRIVACY_KW), ("TERMS", TERMS_KW), ("COOKIE", COOKIE_KW)):
        in_header = any(kw in heading_norm or kw in path_norm for kw in keywords)
        body_hits = sum(1 for kw in keywords if kw in body_norm)
        threshold = 3 if kind == "COOKIE" else 2
        if in_header or body_hits >= threshold:
            kinds.append(kind)

    sentences = _sentences(body_text)[:400]

    evidence: list[dict] = []

    def add_evidence(kind: str, excerpt: Optional[str], selector: Optional[str]) -> None:
        if not excerpt or len(evidence) >= EVIDENCE_MAX_ITEMS:
            return
        evidence.append({"kind": kind, "excerpt": excerpt, "selector": selector})

    for kind, keywords, evidence_kind in (
        ("PRIVACY", PRIVACY_KW, "PRIVACY_POLICY"),
        ("TERMS", TERMS_KW, "TERMS_OF_SERVICE"),
        ("COOKIE", COOKIE_KW, "COOKIE_POLICY"),
    ):
        if kind not in kinds:
            continue
        heading_hit = next((h for h in headings if any(kw in normalize_vi(h) for kw in keywords)), None)
        add_evidence(evidence_kind, heading_hit or title, "h1" if heading_hit else None)

    rights_hits = sorted({kw for kw in RIGHTS_KW if kw in body_norm})
    purpose_hits = sorted({kw for kw in PURPOSE_KW if kw in body_norm})
    controller_hits = sorted({kw for kw in CONTROLLER_KW if kw in body_norm})
    business_hits = sorted({kw for kw in BUSINESS_KW if kw in body_norm})

    has_contact_channel = bool(EMAIL_RE.search(body_text) or PHONE_RE.search(body_text)) or _first_excerpt(
        sentences, CONTACT_KW
    ) is not None
    has_tax_code = bool(TAX_RE.search(body_norm) or (
        TAX_BARE_RE.search(body_text) and any(kw in body_norm for kw in TAX_KW)
    ))
    has_controller_identity = bool(controller_hits) and bool(
        has_tax_code or "dia chi" in body_norm or "nguoi dai dien" in body_norm or "ma so do" in body_norm
    )
    has_business_identity = bool(business_hits) and bool(controller_hits or has_tax_code)

    collects_personal_data, has_login_form = _form_facts(soup)
    cookie_banner = _cookie_banner_present(soup, body_norm)

    add_evidence("DATA_SUBJECT_RIGHTS", _first_excerpt(sentences, rights_hits), "p")
    add_evidence("PROCESSING_PURPOSE", _first_excerpt(sentences, purpose_hits), "p")
    add_evidence("CONTROLLER_IDENTITY", _first_excerpt(sentences, controller_hits), "p")
    add_evidence("CONTACT_INFO", _first_excerpt(sentences, CONTACT_KW), "p")
    if has_tax_code:
        add_evidence("TAX_CODE", _first_excerpt(sentences, TAX_KW), "p")
    if collects_personal_data:
        add_evidence("PERSONAL_DATA_FORM", "Trang có biểu mẫu thu thập dữ liệu (email/số điện thoại/tên).", "form")
    if has_login_form:
        add_evidence("LOGIN_FORM", "Trang có biểu mẫu đăng nhập bằng mật khẩu.", "form")

    signals = {
        "is_policy_page": bool(kinds),
        "policy_kinds": kinds,
        "has_data_subject_rights": len(rights_hits) >= 2,
        "has_processing_purpose": bool(purpose_hits),
        "has_controller_identity": has_controller_identity,
        "has_contact_channel": has_contact_channel,
        "collects_personal_data": collects_personal_data,
        "has_tax_code": has_tax_code,
        "has_business_identity": has_business_identity,
    }

    return PageAnalysis(
        title=title,
        signals=signals,
        evidence=evidence,
        links=_extract_links(soup, page_url),
        has_login_form=has_login_form,
        cookie_banner=cookie_banner,
        headings=headings,
    )


# ---------------------------------------------------------------------- findings


def _finding(code: str, severity: str, title: str, detail: str, evidence_url: str) -> Finding:
    return Finding(code=code, severity=severity, title=title, detail=detail, evidence_url=evidence_url)


def compute_findings(
    site: SiteResult,
    pages: list[PageResult],
    homepage_url: str,
    criteria_version: int = 1,
) -> list[Finding]:
    """Criteria set v1. Wording stays neutral: "chưa tìm thấy" / "cần xác minh"."""
    findings: list[Finding] = []
    home = pages[0] if pages else None
    home_url = home.url if home else homepage_url

    # --- transport / hosting
    if not site.https:
        findings.append(
            _finding(
                "SCAN_HTTPS_MISSING",
                "HIGH",
                "Chưa hỗ trợ HTTPS",
                "Trang được quét không phản hồi qua HTTPS; dữ liệu người dùng có thể truyền không mã hoá.",
                home_url,
            )
        )
    elif not site.tls_valid:
        findings.append(
            _finding(
                "SCAN_TLS_INVALID",
                "HIGH",
                "Chứng chỉ TLS chưa hợp lệ",
                "Kết nối HTTPS không thiết lập được hoặc chứng chỉ không xác thực được; cần xác minh cấu hình TLS.",
                home_url,
            )
        )

    # --- security headers (only meaningful over HTTPS)
    header_specs = (
        ("strict-transport-security", "SCAN_HSTS_MISSING", "LOW", "Chưa tìm thấy HSTS",
         "Phản hồi thiếu tiêu đề Strict-Transport-Security; cần xác minh cấu hình."),
        ("content-security-policy", "SCAN_CSP_MISSING", "LOW", "Chưa tìm thấy Content-Security-Policy",
         "Phản hồi thiếu tiêu đề Content-Security-Policy; cần xác minh cấu hình."),
        ("x-content-type-options", "SCAN_X_CONTENT_TYPE_OPTIONS_MISSING", "LOW", "Chưa tìm thấy X-Content-Type-Options",
         "Phản hồi thiếu tiêu đề X-Content-Type-Options; cần xác minh cấu hình."),
        ("x-frame-options", "SCAN_X_FRAME_OPTIONS_MISSING", "LOW", "Chưa tìm thấy X-Frame-Options",
         "Phản hồi thiếu tiêu đề X-Frame-Options; cần xác minh cấu hình."),
        ("referrer-policy", "SCAN_REFERRER_POLICY_MISSING", "INFO", "Chưa tìm thấy Referrer-Policy",
         "Phản hồi thiếu tiêu đề Referrer-Policy; cần xác minh cấu hình."),
    )
    for header, code, severity, title, detail in header_specs:
        if not site.security_headers.get(header, False):
            findings.append(_finding(code, severity, title, detail, home_url))

    # --- privacy policy
    privacy_pages = [p for p in pages if "PRIVACY" in p.signals.policy_kinds]
    if not privacy_pages:
        findings.append(
            _finding(
                "SCAN_PRIVACY_POLICY_MISSING",
                "HIGH",
                "Không tìm thấy chính sách bảo mật",
                "Không có trang hoặc liên kết chính sách bảo mật trong số trang đã quét.",
                home_url,
            )
        )
    else:
        policy = privacy_pages[0]
        missing = [
            label
            for flag, label in (
                ("has_controller_identity", "thông tin đơn vị kiểm soát dữ liệu"),
                ("has_processing_purpose", "mục đích xử lý dữ liệu"),
                ("has_data_subject_rights", "quyền của chủ thể dữ liệu"),
                ("has_contact_channel", "kênh liên hệ để thực hiện quyền"),
            )
            if not getattr(policy.signals, flag)
        ]
        if missing:
            findings.append(
                _finding(
                    "SCAN_PRIVACY_POLICY_INCOMPLETE",
                    "HIGH",
                    "Chính sách bảo mật chưa đầy đủ nội dung",
                    "Chưa tìm thấy trong chính sách bảo mật: " + ", ".join(missing) + "; cần xác minh thêm.",
                    policy.url,
                )
            )

    # --- terms of service
    if not [p for p in pages if "TERMS" in p.signals.policy_kinds]:
        findings.append(
            _finding(
                "SCAN_TERMS_OF_SERVICE_MISSING",
                "MEDIUM",
                "Không tìm thấy điều khoản sử dụng",
                "Không có trang hoặc liên kết điều khoản sử dụng trong số trang đã quét.",
                home_url,
            )
        )

    # --- cookies
    if not site.cookie_banner:
        findings.append(
            _finding(
                "SCAN_COOKIE_BANNER_MISSING",
                "MEDIUM",
                "Chưa tìm thấy thông báo cookie",
                "Không phát hiện thành phần thông báo/đồng ý cookie trên các trang đã quét; cần xác minh.",
                home_url,
            )
        )
    if not site.cookie_policy_found:
        findings.append(
            _finding(
                "SCAN_COOKIE_POLICY_MISSING",
                "LOW",
                "Không tìm thấy chính sách cookie",
                "Không có trang hoặc liên kết chính sách cookie trong số trang đã quét.",
                home_url,
            )
        )

    # --- seller / business identity
    if not any(p.signals.has_business_identity for p in pages):
        findings.append(
            _finding(
                "SCAN_BUSINESS_IDENTITY_MISSING",
                "MEDIUM",
                "Chưa tìm thấy thông tin đơn vị bán hàng",
                "Không phát hiện tên doanh nghiệp/hộ kinh doanh kèm thông tin đăng ký trong số trang đã quét; cần xác minh.",
                home_url,
            )
        )
    if not any(p.signals.has_tax_code for p in pages):
        findings.append(
            _finding(
                "SCAN_TAX_CODE_MISSING",
                "LOW",
                "Chưa tìm thấy mã số thuế",
                "Không phát hiện mã số thuế/mã số doanh nghiệp trong số trang đã quét; cần xác minh.",
                home_url,
            )
        )
    if not any(p.signals.has_contact_channel for p in pages):
        findings.append(
            _finding(
                "SCAN_CONTACT_INFO_MISSING",
                "MEDIUM",
                "Chưa tìm thấy thông tin liên hệ",
                "Không phát hiện email, số điện thoại hoặc trang liên hệ trong số trang đã quét; cần xác minh.",
                home_url,
            )
        )

    # --- data collection vs. notice
    collecting = [p for p in pages if p.signals.collects_personal_data]
    if collecting and not privacy_pages:
        findings.append(
            _finding(
                "SCAN_PERSONAL_DATA_WITHOUT_NOTICE",
                "HIGH",
                "Thu thập dữ liệu nhưng chưa tìm thấy thông báo bảo mật",
                "Có biểu mẫu thu thập dữ liệu cá nhân nhưng không tìm thấy chính sách bảo mật trong số trang đã quét; cần xác minh.",
                collecting[0].url,
            )
        )

    # --- broken / unreachable pages
    if site.broken_links:
        findings.append(
            _finding(
                "SCAN_BROKEN_LINKS",
                "MEDIUM",
                "Có liên kết không truy cập được",
                f"{len(site.broken_links)} liên kết trả về lỗi hoặc không phản hồi (ví dụ: "
                + ", ".join(site.broken_links[:3])
                + "); cần xác minh.",
                home_url,
            )
        )
    unreachable = [p for p in pages if p.fetch_error]
    for page in unreachable[:5]:
        findings.append(
            _finding(
                "SCAN_PAGE_UNREACHABLE",
                "MEDIUM",
                "Trang không truy cập được",
                f"Không tải được trang ({page.fetch_error}); cần xác minh lại.",
                page.url,
            )
        )

    if criteria_version >= 1:
        _ = criteria_version  # criteria v1 only; kept for future versions
    return findings
