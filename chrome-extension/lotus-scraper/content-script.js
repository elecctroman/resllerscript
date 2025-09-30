(() => {
  const sleep = (ms) => new Promise((resolve) => setTimeout(resolve, ms));

  const normalizeUrl = (rawUrl) => {
    try {
      const url = new URL(rawUrl, window.location.origin);
      if (url.origin !== window.location.origin) {
        return null;
      }
      if (!url.pathname.startsWith('/kategori')) {
        return null;
      }
      url.hash = '';
      return url.toString();
    } catch (error) {
      return null;
    }
  };

  const collectCategoryLinks = (doc) => {
    const links = new Set();
    const anchors = doc.querySelectorAll('a[href]');
    anchors.forEach((anchor) => {
      const href = anchor.getAttribute('href');
      if (!href || href.startsWith('javascript:')) {
        return;
      }
      const normalized = normalizeUrl(href);
      if (normalized) {
        links.add(normalized);
      }
    });
    return Array.from(links);
  };

  const extractCategoryPath = (url) => {
    try {
      const urlObj = new URL(url);
      const segments = urlObj.pathname.split('/').filter(Boolean);
      if (segments[0] !== 'kategori') {
        return '';
      }
      const filtered = segments
        .slice(1)
        .filter((segment) => !/^\d+$/.test(segment))
        .map((segment) => decodeURIComponent(segment.replace(/-/g, ' ')));
      return filtered.join(' > ');
    } catch (error) {
      return '';
    }
  };

  const findPriceElement = (root) => {
    const stack = [root];
    while (stack.length) {
      const node = stack.shift();
      if (node.textContent && /kredi/i.test(node.textContent)) {
        return node;
      }
      stack.push(...node.children);
    }
    return null;
  };

  const extractProductsFromCards = (doc, sourceUrl) => {
    const products = [];
    const cardBodies = doc.querySelectorAll('.card .card-body');
    cardBodies.forEach((body) => {
      const priceEl = findPriceElement(body);
      if (!priceEl) {
        return;
      }
      const titleEl = body.querySelector('.card-title, h5, h4, h3, .product-title');
      if (!titleEl) {
        return;
      }
      const name = titleEl.textContent.trim();
      const price = priceEl.textContent.replace(/\s+/g, ' ').trim();
      if (!name || !price) {
        return;
      }
      products.push({
        name,
        price,
        sourceUrl,
      });
    });
    return products;
  };

  const extractProductsFromTables = (doc, sourceUrl) => {
    const products = [];
    const rows = doc.querySelectorAll('table tbody tr');
    rows.forEach((row) => {
      const cells = Array.from(row.querySelectorAll('td, th'));
      if (cells.length === 0) {
        return;
      }
      const priceCellIndex = cells.findIndex((cell) => /kredi/i.test(cell.textContent));
      if (priceCellIndex === -1) {
        return;
      }
      const price = cells[priceCellIndex].textContent.replace(/\s+/g, ' ').trim();
      const nameCell = cells.find((cell, index) => index !== priceCellIndex && cell.textContent.trim().length > 0);
      if (!nameCell) {
        return;
      }
      const name = nameCell.textContent.replace(/\s+/g, ' ').trim();
      if (!name || !price) {
        return;
      }
      products.push({
        name,
        price,
        sourceUrl,
      });
    });
    return products;
  };

  const mergeProducts = (existing, incoming, categoryPath) => {
    incoming.forEach((product) => {
      const key = `${product.name}__${product.price}__${categoryPath}`;
      if (!existing.has(key)) {
        existing.set(key, {
          categoryPath,
          name: product.name,
          price: product.price,
          sourceUrl: product.sourceUrl,
        });
      }
    });
  };

  const convertToCsv = (records) => {
    const headers = ['Kategori Yolu', 'Ürün Adı', 'Fiyat', 'Kaynak URL'];
    const lines = [headers.join(',')];
    records.forEach((record) => {
      const values = [record.categoryPath, record.name, record.price, record.sourceUrl].map((value) => {
        const safe = value.replace(/"/g, '""');
        return `"${safe}"`;
      });
      lines.push(values.join(','));
    });
    return lines.join('\n');
  };

  const triggerDownload = (filename, content) => {
    const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    anchor.style.display = 'none';
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    URL.revokeObjectURL(url);
  };

  const notifyProgress = (payload) => {
    chrome.runtime.sendMessage({ action: 'scrapeProgress', ...payload });
  };

  const scrapeAllProducts = async () => {
    const startUrl = normalizeUrl(window.location.href);
    if (!startUrl) {
      throw new Error('Mevcut sayfa lotus kategori sayfası değil. Lütfen kategori sayfasını açın.');
    }

    const queue = [];
    const visited = new Set();
    if (startUrl) {
      queue.push(startUrl);
    }

    const initialLinks = collectCategoryLinks(document);
    initialLinks.forEach((link) => {
      if (!queue.includes(link)) {
        queue.push(link);
      }
    });

    const productsMap = new Map();
    let processedCount = 0;

    while (queue.length > 0) {
      const currentUrl = queue.shift();
      if (visited.has(currentUrl)) {
        continue;
      }
      visited.add(currentUrl);

      notifyProgress({ type: 'status', message: `Sayfa inceleniyor: ${currentUrl}` });

      let response;
      try {
        response = await fetch(currentUrl, { credentials: 'include' });
      } catch (error) {
        notifyProgress({ type: 'error', message: `${currentUrl} adresi alınamadı: ${error.message}` });
        continue;
      }

      if (!response.ok) {
        notifyProgress({ type: 'error', message: `${currentUrl} için beklenmeyen yanıt: ${response.status}` });
        continue;
      }

      const html = await response.text();
      const parser = new DOMParser();
      const doc = parser.parseFromString(html, 'text/html');

      const categoryPath = extractCategoryPath(currentUrl);

      const cardProducts = extractProductsFromCards(doc, currentUrl);
      const tableProducts = extractProductsFromTables(doc, currentUrl);
      mergeProducts(productsMap, cardProducts, categoryPath);
      mergeProducts(productsMap, tableProducts, categoryPath);

      processedCount += 1;
      notifyProgress({ type: 'status', message: `Toplanan ürün sayısı: ${productsMap.size}` });

      const newLinks = collectCategoryLinks(doc);
      newLinks.forEach((link) => {
        if (!visited.has(link) && !queue.includes(link)) {
          queue.push(link);
        }
      });

      await sleep(250);
    }

    if (productsMap.size === 0) {
      throw new Error('Herhangi bir ürün bulunamadı. Giriş yapıldığından ve ürün sayfasında olunduğundan emin olun.');
    }

    const records = Array.from(productsMap.values());
    const csv = convertToCsv(records);
    const timestamp = new Date().toISOString().replace(/[:T]/g, '-').split('.')[0];
    const filename = `lotus-urunler-${timestamp}.csv`;
    triggerDownload(filename, csv);

    notifyProgress({ type: 'complete', message: `${records.length} ürün başarıyla indirildi.`, filename });

    return { filename, recordCount: records.length, visitedPages: processedCount };
  };

  chrome.runtime.onMessage.addListener((message, sender, sendResponse) => {
    if (message?.action === 'scrapeProducts') {
      scrapeAllProducts()
        .then((result) => sendResponse({ success: true, result }))
        .catch((error) => {
          notifyProgress({ type: 'error', message: error.message });
          sendResponse({ success: false, error: error.message });
        });
      return true;
    }
    return undefined;
  });
})();
