const https = require('https');

class ResellerApiClient {
  constructor(baseUrl, apiKey, apiSecret, domain) {
    this.baseUrl = baseUrl.replace(/\/$/, '');
    this.apiKey = apiKey;
    this.apiSecret = apiSecret;
    this.domain = domain;
  }

  request(method, path, payload) {
    const url = new URL(path, this.baseUrl);
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
}

module.exports = ResellerApiClient;
