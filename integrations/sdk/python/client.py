"""Reseller REST API için hafif Python istemcisi."""
from __future__ import annotations

import hashlib
import hmac
import json
import time
from typing import Any, Dict, Optional

import requests


class ResellerApiClient:
    def __init__(
        self,
        base_url: str,
        api_key: str,
        bearer_token: Optional[str] = None,
        hmac_secret: Optional[str] = None,
        session: Optional[requests.Session] = None,
    ) -> None:
        self.base_url = base_url.rstrip('/')
        self.api_key = api_key
        self.bearer_token = bearer_token
        self.hmac_secret = hmac_secret
        self.session = session or requests.Session()

    def get_products(self) -> Any:
        response = self._request('GET', '/products')
        return response.get('data', {}).get('products', [])

    def create_order(self, payload: Dict[str, Any]) -> Dict[str, Any]:
        response = self._request('POST', '/order/create', json_body=payload)
        return response.get('data', {})

    def get_order_status(self, **params: Any) -> Dict[str, Any]:
        response = self._request('GET', '/order/status', params=params)
        return response.get('data', {}).get('order', {})

    def get_balance(self) -> Dict[str, Any]:
        response = self._request('GET', '/balance')
        return response.get('data', {})

    def get_user_info(self) -> Dict[str, Any]:
        response = self._request('GET', '/user/info')
        return response.get('data', {})

    def _request(
        self,
        method: str,
        path: str,
        json_body: Optional[Dict[str, Any]] = None,
        params: Optional[Dict[str, Any]] = None,
    ) -> Dict[str, Any]:
        url = f"{self.base_url}{path}"
        headers = {
            'Accept': 'application/json',
            'X-API-Key': self.api_key,
        }

        body = json.dumps(json_body, ensure_ascii=False) if json_body is not None else ''
        data_to_send = body if body else None

        if data_to_send is not None:
            headers['Content-Type'] = 'application/json'

        if self.bearer_token:
            headers['Authorization'] = f'Bearer {self.bearer_token}'

        if self.hmac_secret:
            timestamp = str(int(time.time()))
            signature_payload = f"{timestamp}\n{method.upper()}\n{path}\n{body}"
            signature = hmac.new(
                self.hmac_secret.encode('utf-8'),
                signature_payload.encode('utf-8'),
                hashlib.sha256,
            ).hexdigest()
            headers['X-Request-Timestamp'] = timestamp
            headers['X-Signature'] = f'sha256={signature}'

        response = self.session.request(
            method=method,
            url=url,
            headers=headers,
            data=data_to_send,
            params=params,
            timeout=30,
        )
        response.raise_for_status()
        return response.json()
