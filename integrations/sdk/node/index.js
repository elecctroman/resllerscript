import crypto from 'node:crypto';
import https from 'node:https';

/**
 * Minimal Node.js istemcisi. Node 18+ üzerinde çalışır.
 */
export default class ResellerApiClient {
  /**
   * @param {string} baseUrl
   * @param {string} apiKey
   * @param {{bearerToken?: string, hmacSecret?: string}} [options]
   */
  constructor(baseUrl, apiKey, options = {}) {
    this.baseUrl = baseUrl.replace(/\/$/, '');
    this.apiKey = apiKey;
    this.bearerToken = options.bearerToken ?? null;
    this.hmacSecret = options.hmacSecret ?? null;
    this.httpAgent = new https.Agent({ keepAlive: true });
  }

  async getProducts() {
    const response = await this.request('GET', '/products');
    return response.data?.products ?? [];
  }

  async createOrder(payload) {
    const response = await this.request('POST', '/order/create', payload);
    return response.data ?? {};
  }

  async getOrderStatus(query) {
    const response = await this.request('GET', '/order/status', null, query);
    return response.data?.order ?? {};
  }

  async getBalance() {
    const response = await this.request('GET', '/balance');
    return response.data ?? {};
  }

  async getUserInfo() {
    const response = await this.request('GET', '/user/info');
    return response.data ?? {};
  }

  async request(method, path, body = null, query = undefined) {
    let url = `${this.baseUrl}${path}`;
    if (query && Object.keys(query).length > 0) {
      const params = new URLSearchParams(query);
      url += `?${params.toString()}`;
    }

    const headers = {
      'Accept': 'application/json',
      'X-API-Key': this.apiKey,
    };

    let payload = undefined;
    if (body !== null && body !== undefined) {
      payload = JSON.stringify(body);
      headers['Content-Type'] = 'application/json';
    }

    if (this.bearerToken) {
      headers['Authorization'] = `Bearer ${this.bearerToken}`;
    }

    if (this.hmacSecret) {
      const timestamp = Math.floor(Date.now() / 1000).toString();
      const signingPayload = `${timestamp}\n${method.toUpperCase()}\n${path}\n${payload ?? ''}`;
      const signature = crypto.createHmac('sha256', this.hmacSecret).update(signingPayload).digest('hex');
      headers['X-Request-Timestamp'] = timestamp;
      headers['X-Signature'] = `sha256=${signature}`;
    }

    const response = await fetch(url, {
      method,
      headers,
      body: payload,
      agent: this.httpAgent,
    });

    const json = await response.json().catch(() => ({}));
    if (!response.ok) {
      const message = json?.error ?? `API error (${response.status})`;
      throw new Error(message);
    }

    return json;
  }
}
