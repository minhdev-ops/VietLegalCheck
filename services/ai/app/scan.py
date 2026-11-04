"""SSRF-hardened crawler for the internal scan endpoint.

Safety model (SRS FR-SCAN-06..09):

* only http/https on ports 80/443, no credentials in the URL;
* DNS is resolved here, every address is classified, and the connection is
  pinned to a validated IP (``Host`` header + ``sni_hostname`` extension) so a
  second lookup inside the HTTP client cannot point us somewhere else;
* redirects are followed manually, at most 5 hops, with a fresh resolution and
  validation per hop (a hop that lands on a blocked address is DNS_REBINDING);
* responses are streamed into a bounded buffer, robots.txt is honoured and all
  page content is treated as inert data.
"""

from __future__ import annotations

import asyncio
import hashlib
import ipaddress
import logging
import os
import socket
from collections import deque
from dataclasses import dataclass, field
from typing import Callable, Iterable, Optional
from urllib import robotparser
from urllib.parse import urljoin, urlsplit, urlunsplit

import httpx

from .models import (
    Evidence,
    Finding,
    PageResult,
    ScanLimits,
    ScanRequest,
    ScanResponse,
    Signals,
    SiteResult,
)
from .signals import (
    SECURITY_HEADERS,
    SNAPSHOT_MAX_CHARS,
    USER_AGENT,
    PageAnalysis,
    analyze_page,
    compute_findings,
    policy_link_kind,
)

log = logging.getLogger("vl.scan")

DEFAULT_PORTS = {"http": 80, "https": 443}
ALLOWED_PORTS = frozenset({80, 443})
MAX_REDIRECTS = 5
LIGHT_MAX_EXTRA_PAGES = 5
LINK_CHECK_LIMIT = 12
LINK_CHECK_BYTES = 65_536
ROBOTS_MAX_BYTES = 131_072
CANCEL_POLL = "CANCELLED"

METADATA_ADDRESSES = frozenset(
    {
        ipaddress.ip_address("169.254.169.254"),
        ipaddress.ip_address("100.100.100.200"),  # Alibaba Cloud metadata
        ipaddress.ip_address("fd00:ec2::254"),  # AWS IPv6 metadata
    }
)

# Address ranges that Python's ipaddress does not flag but must never be reached.
EXTRA_BLOCKED_V4 = tuple(
    ipaddress.ip_network(net)
    for net in (
        "0.0.0.0/8",
        "100.64.0.0/10",  # CGNAT
        "198.18.0.0/15",
        "192.0.0.0/24",
        "240.0.0.0/4",
    )
)
EXTRA_BLOCKED_V6 = tuple(
    ipaddress.ip_network(net)
    for net in (
        "::/128",
        "::ffff:0:0/96",
        "64:ff9b::/96",
        "2001:db8::/32",
        "2002::/16",
    )
)


# ------------------------------------------------------------------- exceptions


class BlockedURL(Exception):
    """The URL is unsafe to contact; ``reason`` is a contract blocked_reason."""

    def __init__(self, reason: str):
        super().__init__(reason)
        self.reason = reason


class FetchFailure(Exception):
    """Unsafe-free failure: DNS error, timeout, TLS problem, cancel, ..."""

    def __init__(self, code: str, detail: str = ""):
        super().__init__(code)
        self.code = code
        self.detail = detail


# ---------------------------------------------------------------- target parsing


@dataclass(frozen=True)
class Target:
    scheme: str
    host: str
    port: int
    path: str = "/"
    query: str = ""

    @property
    def default_port(self) -> int:
        return DEFAULT_PORTS[self.scheme]

    @property
    def netloc(self) -> str:
        host = f"[{self.host}]" if ":" in self.host else self.host
        return host if self.port == self.default_port else f"{host}:{self.port}"

    @property
    def url(self) -> str:
        return urlunsplit((self.scheme, self.netloc, self.path or "/", self.query, ""))


def parse_target(url: str) -> tuple[Optional[Target], Optional[str]]:
    """Validate scheme/host/port. Returns (target, None) or (None, reason)."""
    if not isinstance(url, str):
        return None, "INVALID_URL"
    candidate = url.strip()
    if not candidate or len(candidate) > 2048:
        return None, "INVALID_URL"
    try:
        parts = urlsplit(candidate)
    except ValueError:
        return None, "INVALID_URL"
    scheme = parts.scheme.lower()
    if scheme not in DEFAULT_PORTS:
        return None, "UNSUPPORTED_SCHEME"
    if parts.username is not None or parts.password is not None:
        return None, "INVALID_URL"
    host = parts.hostname
    if not host:
        return None, "INVALID_URL"
    host = host.lower()
    if host.endswith("."):
        host = host[:-1]
    if not host or any(ch in host for ch in " /\\@"):
        return None, "INVALID_URL"
    try:
        raw_port = parts.port
    except ValueError:
        return None, "INVALID_URL"
    port = DEFAULT_PORTS[scheme] if raw_port is None else raw_port
    if port not in ALLOWED_PORTS:
        return None, "UNSUPPORTED_PORT"
    return Target(scheme=scheme, host=host, port=port, path=parts.path or "/", query=parts.query), None


def classify_ip(raw: str) -> Optional[str]:
    """Return a blocked_reason when the address must not be contacted."""
    try:
        ip = ipaddress.ip_address(str(raw).split("%")[0])
    except ValueError:
        return "INVALID_URL"
    if isinstance(ip, ipaddress.IPv6Address):
        if ip.ipv4_mapped is not None:
            return classify_ip(str(ip.ipv4_mapped))
        if ip in METADATA_ADDRESSES:
            return "METADATA_ADDRESS"
        if (
            ip.is_loopback
            or ip.is_private
            or ip.is_link_local
            or ip.is_multicast
            or ip.is_reserved
            or ip.is_unspecified
        ):
            return "PRIVATE_ADDRESS"
        if any(ip in network for network in EXTRA_BLOCKED_V6):
            return "PRIVATE_ADDRESS"
        return None
    if ip in METADATA_ADDRESSES:
        return "METADATA_ADDRESS"
    if (
        ip.is_loopback
        or ip.is_private
        or ip.is_link_local
        or ip.is_multicast
        or ip.is_reserved
        or ip.is_unspecified
    ):
        return "PRIVATE_ADDRESS"
    if any(ip in network for network in EXTRA_BLOCKED_V4):
        return "PRIVATE_ADDRESS"
    return None


def _system_resolve(host: str, port: int) -> list[str]:
    infos = socket.getaddrinfo(host, port, type=socket.SOCK_STREAM)
    addresses: list[str] = []
    for info in infos:
        sockaddr = info[4]
        if sockaddr and sockaddr[0] not in addresses:
            addresses.append(sockaddr[0])
    return addresses


# --------------------------------------------------------------------- fetching


@dataclass
class FetchResult:
    requested_url: str
    final_url: str
    status: Optional[int]
    headers: httpx.Headers
    body: bytes
    truncated: bool = False
    error: Optional[str] = None
    resolved_ip: Optional[str] = None
    resolved_ips: list[str] = field(default_factory=list)


class Fetcher:
    """Single-use, pinned-IP HTTP fetcher with a hard byte budget."""

    def __init__(
        self,
        *,
        transport: Optional[httpx.AsyncBaseTransport] = None,
        resolver: Optional[Callable[[str, int], Iterable[str]]] = None,
        timeout: float = 20.0,
        max_bytes: int = 3_000_000,
        max_redirects: int = MAX_REDIRECTS,
        cancel: Optional[Callable[[], bool]] = None,
    ) -> None:
        self.transport = transport
        self.resolver = resolver
        self.timeout = timeout
        self.max_bytes = max_bytes
        self.max_redirects = max_redirects
        self._cancel = cancel
        self._client: Optional[httpx.AsyncClient] = None

    # -- lifecycle

    def _ensure_client(self) -> httpx.AsyncClient:
        if self._client is None:
            self._client = httpx.AsyncClient(
                transport=self.transport,
                timeout=httpx.Timeout(self.timeout),
                follow_redirects=False,
                trust_env=False,
                verify=True,
            )
        return self._client

    async def aclose(self) -> None:
        if self._client is not None:
            await self._client.aclose()
            self._client = None

    # -- primitives

    def _check_cancel(self) -> None:
        if self._cancel is not None and self._cancel():
            raise FetchFailure(CANCEL_POLL)

    async def resolve(self, host: str, port: int) -> list[str]:
        try:
            ipaddress.ip_address(host)
        except ValueError:
            pass
        else:
            return [host]
        loop = asyncio.get_running_loop()
        resolver = self.resolver or _system_resolve
        try:
            addresses = await loop.run_in_executor(None, resolver, host, port)
        except FetchFailure:
            raise
        except Exception as exc:  # DNS failure, gaierror, timeout
            raise FetchFailure("DNS_ERROR", type(exc).__name__) from exc
        result = [str(a) for a in addresses if a]
        if not result:
            raise FetchFailure("DNS_ERROR", "no_address")
        return result

    def _pinned_url(self, target: Target, ip: str) -> str:
        host = f"[{ip}]" if ":" in ip else ip
        netloc = host if target.port == target.default_port else f"{host}:{target.port}"
        return urlunsplit((target.scheme, netloc, target.path or "/", target.query, ""))

    def _build_request(self, target: Target, ip: str) -> httpx.Request:
        client = self._ensure_client()
        headers = {
            "Host": target.netloc,
            "User-Agent": USER_AGENT,
            "Accept": "text/html,application/xhtml+xml,text/plain;q=0.9,*/*;q=0.1",
            "Accept-Language": "vi-VN,vi;q=0.9,en;q=0.5",
            "Connection": "close",
        }
        extensions: dict = {}
        if target.scheme == "https":
            extensions["sni_hostname"] = target.host
        return client.build_request(
            "GET", self._pinned_url(target, ip), headers=headers, extensions=extensions
        )

    async def _validate_addresses(
        self, addresses: list[str], *, initial_safe: Optional[list[str]]
    ) -> list[str]:
        safe: list[str] = []
        blocked_reason: Optional[str] = None
        for address in addresses:
            reason = classify_ip(address)
            if reason is None:
                safe.append(address)
            else:
                blocked_reason = blocked_reason or reason
        if blocked_reason is not None:
            # A redirect (or repeated lookup) that now resolves to a blocked
            # address is a rebinding attempt, never a normal hop.
            raise BlockedURL("DNS_REBINDING" if initial_safe is not None else blocked_reason)
        if not safe:
            raise FetchFailure("DNS_ERROR", "no_safe_address")
        if initial_safe is not None and not set(safe) & set(initial_safe):
            raise BlockedURL("DNS_REBINDING")
        return safe

    async def fetch(self, url: str, *, max_bytes: Optional[int] = None) -> FetchResult:
        budget = self.max_bytes if max_bytes is None else max(1, min(max_bytes, self.max_bytes))
        requested_url = url
        current = url
        initial_safe: Optional[list[str]] = None
        pinned_ip: Optional[str] = None
        hops = 0

        while True:
            self._check_cancel()
            target, reason = parse_target(current)
            if reason is not None or target is None:
                raise BlockedURL(reason or "INVALID_URL")

            addresses = await self.resolve(target.host, target.port)
            safe = await self._validate_addresses(addresses, initial_safe=initial_safe)
            if initial_safe is None:
                initial_safe = safe
            ip = safe[0]
            if pinned_ip is None:
                pinned_ip = ip

            request = self._build_request(target, ip)
            try:
                response = await self._ensure_client().send(request, stream=True)
            except httpx.TimeoutException as exc:
                raise FetchFailure("TIMEOUT", type(exc).__name__) from exc
            except httpx.ConnectError as exc:
                message = str(exc).upper()
                code = "TLS_INVALID" if any(token in message for token in ("SSL", "TLS", "CERTIFICATE")) else "CONNECT_ERROR"
                raise FetchFailure(code, str(exc)[:200]) from exc
            except httpx.HTTPError as exc:
                raise FetchFailure("HTTP_ERROR", type(exc).__name__) from exc

            try:
                location = response.headers.get("location")
                if response.status_code in (301, 302, 303, 307, 308) and location:
                    hops += 1
                    if hops > self.max_redirects:
                        raise BlockedURL("REDIRECT_LIMIT")
                    current = urljoin(target.url, location.strip())
                    continue

                chunks = bytearray()
                truncated = False
                try:
                    async for chunk in response.aiter_bytes():
                        remaining = budget - len(chunks)
                        if remaining <= 0:
                            truncated = True
                            break
                        if len(chunk) > remaining:
                            chunks.extend(chunk[:remaining])
                            truncated = True
                            break
                        chunks.extend(chunk)
                except httpx.HTTPError as exc:
                    raise FetchFailure("READ_ERROR", type(exc).__name__) from exc

                return FetchResult(
                    requested_url=requested_url,
                    final_url=current,
                    status=response.status_code,
                    headers=response.headers,
                    body=bytes(chunks),
                    truncated=truncated,
                    resolved_ip=pinned_ip,
                    resolved_ips=list(initial_safe),
                )
            finally:
                await response.aclose()


# ---------------------------------------------------------------- cancel registry


_CANCEL_FLAGS: dict[str, asyncio.Event] = {}


def begin_job(job_id: str) -> asyncio.Event:
    event = _CANCEL_FLAGS.get(job_id)
    if event is None:
        event = asyncio.Event()
        _CANCEL_FLAGS[job_id] = event
    return event


def request_cancel(job_id: str) -> None:
    begin_job(job_id).set()


def is_cancelled(job_id: str) -> bool:
    event = _CANCEL_FLAGS.get(job_id)
    return bool(event and event.is_set())


def finish_job(job_id: str) -> None:
    _CANCEL_FLAGS.pop(job_id, None)


# ------------------------------------------------------------------- scan runner


def effective_limits(limits: ScanLimits) -> ScanLimits:
    """Apply the environment ceilings on top of the per-request limits."""

    def env_int(name: str, fallback: int) -> int:
        raw = os.environ.get(name, "").strip()
        try:
            value = int(raw)
        except (TypeError, ValueError):
            return fallback
        return value if value > 0 else fallback

    max_pages = min(limits.max_pages, env_int("SCAN_MAX_PAGES", limits.max_pages))
    max_bytes = min(limits.max_bytes, env_int("SCAN_MAX_BYTES", limits.max_bytes))
    timeout = min(limits.timeout_seconds, float(env_int("SCAN_TIMEOUT_SECONDS", int(limits.timeout_seconds) or 20)))
    return ScanLimits(max_pages=max_pages, max_bytes=max_bytes, timeout_seconds=timeout)


@dataclass
class _Page:
    result: PageResult
    analysis: Optional[PageAnalysis]
    set_cookie: bool = False


class ScanHandler:
    def __init__(self, request: ScanRequest, fetcher: Fetcher, cancel_event: Optional[asyncio.Event] = None):
        self.request = request
        self.limits = effective_limits(request.limits)
        self.fetcher = fetcher
        self.cancel_event = cancel_event
        self.pages: list[_Page] = []
        self.home: Optional[FetchResult] = None
        self.home_target: Optional[Target] = None
        self.robots: Optional[robotparser.RobotFileParser] = None
        self.robots_checked = False
        self.robots_skipped: list[str] = []
        self.bytes_used = 0
        self.tls_invalid = False
        self.links_checked = 0
        self.broken_links: list[str] = []
        self._seen: set[str] = set()

    # -- helpers

    def _cancelled(self) -> bool:
        return bool(self.cancel_event is not None and self.cancel_event.is_set())

    def _check_cancel(self) -> None:
        if self._cancelled():
            raise FetchFailure(CANCEL_POLL)

    def _remaining_bytes(self) -> int:
        return self.limits.max_bytes - self.bytes_used

    def _robots_allows(self, url: str) -> bool:
        if self.robots is None:
            return True
        return self.robots.can_fetch(USER_AGENT, url)

    def _same_origin(self, url: str) -> bool:
        if self.home_target is None:
            return False
        target, reason = parse_target(url)
        if reason is not None or target is None:
            return False
        return (target.scheme, target.host, target.port) == (
            self.home_target.scheme,
            self.home_target.host,
            self.home_target.port,
        )

    def _blocked(self, reason: str) -> ScanResponse:
        log.info("scan blocked job_id=%s reason=%s", self.request.job_id, reason)
        return ScanResponse(status="BLOCKED", blocked_reason=reason)

    def _failed(self, *, cancelled: bool = False) -> ScanResponse:
        return ScanResponse(status="FAILED", blocked_reason=None, cancelled=cancelled)

    # -- robots

    async def _load_robots(self, target: Target) -> None:
        robots_url = urlunsplit((target.scheme, target.netloc, "/robots.txt", "", ""))
        parser = robotparser.RobotFileParser()
        parser.set_url(robots_url)
        try:
            result = await self.fetcher.fetch(robots_url, max_bytes=ROBOTS_MAX_BYTES)
        except BlockedURL as exc:
            log.info("scan robots blocked job_id=%s reason=%s", self.request.job_id, exc.reason)
            self.robots = None
            return
        except FetchFailure as exc:
            if exc.code == CANCEL_POLL:
                raise
            self.robots = None
            return
        self.bytes_used += len(result.body)
        if result.status is not None and 200 <= result.status < 300:
            parser.parse(result.body.decode("utf-8", "replace").splitlines())
        else:
            parser.parse([])
        self.robots = parser
        self.robots_checked = True

    # -- pages

    def _page_result(self, result: FetchResult) -> _Page:
        html: Optional[str] = None
        analysis: Optional[PageAnalysis] = None
        fetch_error = result.error
        if fetch_error is None and result.status is not None and result.status >= 400:
            fetch_error = f"HTTP_{result.status}"
        if fetch_error is None:
            content_type = (result.headers.get("content-type") or "").split(";")[0].strip().lower()
            if content_type and not (content_type.startswith("text/") or content_type == "application/xhtml+xml"):
                fetch_error = "UNSUPPORTED_CONTENT"
            elif result.truncated:
                fetch_error = "RESPONSE_TOO_LARGE"
            else:
                html = result.body.decode("utf-8", "replace")[:SNAPSHOT_MAX_CHARS]
                analysis = analyze_page(html, result.final_url)

        page = PageResult(
            url=result.final_url,
            http_status=result.status,
            title=analysis.title if analysis else None,
            fetch_error=fetch_error,
            content_hash=hashlib.sha256(result.body).hexdigest(),
            snapshot_html=html,
            signals=Signals(**analysis.signals) if analysis else Signals(),
            evidence=[Evidence(**item) for item in analysis.evidence] if analysis else [],
        )
        return _Page(result=page, analysis=analysis, set_cookie=bool(result.headers.get("set-cookie")))

    def _record(self, result: FetchResult) -> _Page:
        page = self._page_result(result)
        self.pages.append(page)
        self._seen.add(page.result.url)
        self._seen.add(result.requested_url)
        return page

    async def _fetch_page(self, url: str) -> Optional[_Page]:
        remaining = self._remaining_bytes()
        if remaining <= 0:
            return None
        self._check_cancel()
        result = await self.fetcher.fetch(url, max_bytes=remaining)
        self.bytes_used += len(result.body)
        return self._record(result)

    # -- crawl

    async def run(self) -> ScanResponse:
        self._check_cancel()
        target, reason = parse_target(self.request.url)
        if reason is not None or target is None:
            return self._blocked(reason or "INVALID_URL")
        self.home_target = target

        try:
            await self._load_robots(target)
            home = await self.fetcher.fetch(target.url, max_bytes=self.limits.max_bytes)
        except BlockedURL as exc:
            return self._blocked(exc.reason)
        except FetchFailure as exc:
            if exc.code == CANCEL_POLL:
                return self._failed(cancelled=True)
            if exc.code == "TLS_INVALID":
                self.tls_invalid = True
            log.info("scan failed job_id=%s code=%s", self.request.job_id, exc.code)
            return self._failed()

        self.home = home
        self.bytes_used += len(home.body)

        if not self._robots_allows(home.final_url):
            self.robots_skipped.append(home.final_url)
            return self._completed()

        self._record(home)
        try:
            await self._crawl()
            await self._check_links()
        except FetchFailure as exc:
            if exc.code == CANCEL_POLL:
                return self._failed(cancelled=True)
            log.info("scan aborted job_id=%s code=%s", self.request.job_id, exc.code)
        except BlockedURL:
            pass
        return self._completed()

    def _candidate_links(self) -> list[str]:
        """LIGHT: policy/terms/contact links from the homepage. DEEP: same-origin."""
        home = self.pages[0] if self.pages else None
        if home is None or home.analysis is None:
            return []
        queue: list[str] = []
        for url, text in home.analysis.links:
            if self.request.mode == "LIGHT":
                if policy_link_kind(text, url) is None:
                    continue
                if not self._same_origin(url):
                    continue
                queue.append(url)
                if len(queue) >= LIGHT_MAX_EXTRA_PAGES:
                    break
            else:
                if self._same_origin(url):
                    queue.append(url)
        return queue

    async def _crawl(self) -> None:
        pending = deque(url for url in self._candidate_links() if url not in self._seen)
        deep = self.request.mode == "DEEP"
        while pending and len(self.pages) < self.limits.max_pages:
            self._check_cancel()
            if self._remaining_bytes() <= 0:
                break
            url = pending.popleft()
            if url in self._seen:
                continue
            if not self._robots_allows(url):
                self.robots_skipped.append(url)
                continue
            try:
                page = await self._fetch_page(url)
            except BlockedURL:
                continue  # link points somewhere unsafe: never follow
            if page is None:
                break
            if deep and page.analysis is not None and len(self.pages) < self.limits.max_pages:
                for link, _text in page.analysis.links:
                    if link in self._seen or link in pending:
                        continue
                    if self._same_origin(link):
                        pending.append(link)

    async def _check_links(self) -> None:
        candidates: list[str] = []
        queued: set[str] = set()
        for page in self.pages:
            if page.analysis is None:
                continue
            for url, _text in page.analysis.links:
                if url in self._seen or url in queued:
                    continue
                target, reason = parse_target(url)
                if reason is not None:
                    continue
                queued.add(url)
                candidates.append(url)
        for url in candidates[:LINK_CHECK_LIMIT]:
            self._check_cancel()
            if not self._robots_allows(url):
                continue
            try:
                result = await self.fetcher.fetch(url, max_bytes=LINK_CHECK_BYTES)
            except BlockedURL:
                continue
            except FetchFailure as exc:
                if exc.code == CANCEL_POLL:
                    raise
                self.links_checked += 1
                self.broken_links.append(url)
                continue
            self.links_checked += 1
            if result.status is None or result.status >= 400:
                self.broken_links.append(url)

    # -- result assembly

    def _site(self) -> SiteResult:
        https = bool(self.home and self.home.final_url.startswith("https://"))
        headers = {
            name: bool(self.home and (self.home.headers.get(name) or "").strip())
            for name in SECURITY_HEADERS
        }
        return SiteResult(
            https=https,
            tls_valid=https and not self.tls_invalid,
            security_headers=headers,
            cookie_banner=any(p.analysis.cookie_banner for p in self.pages if p.analysis),
            cookie_policy_found=any("COOKIE" in p.result.signals.policy_kinds for p in self.pages),
            has_login_form=any(p.analysis.has_login_form for p in self.pages if p.analysis),
            links_checked=self.links_checked,
            broken_links=self.broken_links,
        )

    def _completed(self) -> ScanResponse:
        site = self._site()
        pages = [p.result for p in self.pages]
        findings = compute_findings(site, pages, self.request.url, self.request.criteria_version)
        for url in self.robots_skipped[:10]:
            findings.append(
                Finding(
                    code="SCAN_ROBOTS_DISALLOWED",
                    severity="INFO",
                    title="Trang bị robots.txt chặn quét",
                    detail="Đã bỏ qua trang này để tuân thủ robots.txt của website.",
                    evidence_url=url,
                )
            )
        log.info(
            "scan completed job_id=%s pages=%d findings=%d bytes=%d",
            self.request.job_id,
            len(pages),
            len(findings),
            self.bytes_used,
        )
        return ScanResponse(
            status="COMPLETED",
            blocked_reason=None,
            final_url=self.home.final_url if self.home else None,
            resolved_ip=self.home.resolved_ip if self.home else None,
            robots_checked=self.robots_checked,
            pages=pages,
            site=site,
            findings=findings,
        )


async def run_scan(
    request: ScanRequest,
    *,
    transport: Optional[httpx.AsyncBaseTransport] = None,
    resolver: Optional[Callable[[str, int], Iterable[str]]] = None,
    cancel_event: Optional[asyncio.Event] = None,
) -> ScanResponse:
    job_id = request.job_id
    event = cancel_event if cancel_event is not None else begin_job(job_id)
    limits = effective_limits(request.limits)
    fetcher = Fetcher(
        transport=transport,
        resolver=resolver,
        timeout=limits.timeout_seconds,
        max_bytes=limits.max_bytes,
        cancel=lambda: event.is_set(),
    )
    handler = ScanHandler(request, fetcher, event)
    try:
        return await handler.run()
    finally:
        await fetcher.aclose()
        finish_job(job_id)
