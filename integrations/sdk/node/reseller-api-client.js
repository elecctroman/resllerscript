const https = require('https');

class ResellerApiClient {
  constructor(baseUrl, apiKey, apiSecret, domain) {
    this.baseUrl = baseUrl.replace(/\/$/, '');
    this.apiKey = apiKey;
    this.apiSecret = apiSecret;
    this.domain = domain;
  }

  request(method, path, payload, query) {
    const url = new URL(path, this.baseUrl);
    if (query) {
      Object.entries(query).forEach(([key, value]) => {
        if (value !== undefined && value !== null && value !== '') {
          url.searchParams.append(key, value);
        }
      });
    }
    const body = payload ? JSON.stringify(payload) : null;
    const options = {
      method,
      headers: {
        'X-API-KEY': this.apiKey,
        'X-API-SECRET': this.apiSecret,
        'Accept': 'application/json'
      }
    };
    if (this.domain) {
      options.headers['X-CLIENT-DOMAIN'] = this.domain;
    }
    if (body) {
      options.headers['Content-Type'] = 'application/json';
    }

    return new Promise((resolve, reject) => {
      const req = https.request(url, options, res => {
        let data = '';
        res.on('data', chunk => { data += chunk; });
        res.on('end', () => {
          try {
            resolve(JSON.parse(data));
          } catch (error) {
            reject(error);
          }
        });
      });
      req.on('error', reject);
      if (body) {
        req.write(body);
      }
      req.end();
    });
  }

  getProducts() {
    return this.request('GET', '/v1/products');
  }

  createOrder(productId, options = {}) {
    const payload = { product_id: productId };
    if (options.note) {
      payload.note = options.note;
    }
    if (options.external_reference) {
      payload.external_reference = options.external_reference;
    }
    return this.request('POST', '/v1/order/create', payload);
  }

  getOrderStatus({ order_id, external_reference } = {}) {
    return this.request('GET', '/v1/order/status', null, { order_id, external_reference });
  }

  getBalance() {
    return this.request('GET', '/v1/balance');
  }

  getUserInfo() {
    return this.request('GET', '/v1/user/info');
  }

  createApiKey({ allowed_ips, allowed_domains } = {}) {
    const payload = {};
    if (Array.isArray(allowed_ips) && allowed_ips.length) {
      payload.allowed_ips = allowed_ips;
    }
    if (Array.isArray(allowed_domains) && allowed_domains.length) {
      payload.allowed_domains = allowed_domains;
    }
    return this.request('POST', '/v1/api-keys/create', payload);
  }

  listApiKeys() {
    return this.request('GET', '/v1/api-keys/list');
  }

  revokeApiKey(key) {
    return this.request('POST', '/v1/api-keys/revoke', { key });
  }
}

module.exports = ResellerApiClient;
