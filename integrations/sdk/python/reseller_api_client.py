from __future__ import annotations

import json
import urllib.parse
import urllib.request


class ResellerApiClient:
    def __init__(self, base_url: str, api_key: str, api_secret: str, domain: str | None = None) -> None:
        self.base_url = base_url.rstrip('/')
        self.api_key = api_key
        self.api_secret = api_secret
        self.domain = domain

    def request(self, method: str, path: str, payload: dict | None = None, query: dict | None = None) -> dict:
        url = f"{self.base_url}{path}"
        if query:
            filtered = {key: value for key, value in query.items() if value not in (None, "")}
            querystring = urllib.parse.urlencode(filtered)
            if querystring:
                url = f"{url}?{querystring}"
        data = None
        headers = {
            "X-API-KEY": self.api_key,
            "X-API-SECRET": self.api_secret,
            "Accept": "application/json",
        }
        if self.domain:
            headers["X-CLIENT-DOMAIN"] = self.domain
        if payload is not None:
            data = json.dumps(payload).encode('utf-8')
            headers["Content-Type"] = "application/json"
        req = urllib.request.Request(url, data=data, method=method, headers=headers)
        with urllib.request.urlopen(req) as response:
            body = response.read().decode('utf-8')
            return json.loads(body)

    def get_products(self) -> dict:
        return self.request("GET", "/v1/products")

    def create_order(self, product_id: int, note: str | None = None, reference: str | None = None) -> dict:
        payload: dict[str, object] = {"product_id": product_id}
        if note:
            payload["note"] = note
        if reference:
            payload["external_reference"] = reference
        return self.request("POST", "/v1/order/create", payload)

    def get_order_status(self, order_id: int | None = None, reference: str | None = None) -> dict:
        query: dict[str, object] = {}
        if order_id is not None:
            query["order_id"] = order_id
        if reference:
            query["external_reference"] = reference
        return self.request("GET", "/v1/order/status", query=query)

    def get_balance(self) -> dict:
        return self.request("GET", "/v1/balance")

    def get_user_info(self) -> dict:
        return self.request("GET", "/v1/user/info")

    def create_api_key(self, allowed_ips: list[str] | None = None, allowed_domains: list[str] | None = None) -> dict:
        payload: dict[str, object] = {}
        if allowed_ips:
            payload["allowed_ips"] = allowed_ips
        if allowed_domains:
            payload["allowed_domains"] = allowed_domains
        return self.request("POST", "/v1/api-keys/create", payload)

    def list_api_keys(self) -> dict:
        return self.request("GET", "/v1/api-keys/list")

    def revoke_api_key(self, key: str) -> dict:
        return self.request("POST", "/v1/api-keys/revoke", {"key": key})
