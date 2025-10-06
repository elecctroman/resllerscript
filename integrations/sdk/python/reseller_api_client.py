import json
import urllib.request


class ResellerApiClient:
    def __init__(self, base_url: str, api_key: str, api_secret: str, domain: str | None = None) -> None:
        self.base_url = base_url.rstrip('/')
        self.api_key = api_key
        self.api_secret = api_secret
        self.domain = domain

    def request(self, method: str, path: str, payload: dict | None = None) -> dict:
        url = f"{self.base_url}{path}"
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
