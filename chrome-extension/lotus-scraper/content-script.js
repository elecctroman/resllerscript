(() => {
  if (window.__lotusScraperInitialized) {
    return;
  }
  window.__lotusScraperInitialized = true;

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

  const escapeForCsv = (value) => {
    const text = value == null ? '' : String(value);
    const safe = text.replace(/"/g, '""');
    return `"${safe}"`;
  };

  const CSV_BOM = String.fromCharCode(0xfeff);

  const parsePriceValue = (priceText) => {
    if (!priceText) {
      return { numeric: '', formatted: '' };
    }

    let cleaned = priceText
      .toString()
      .toLowerCase()
      .replace(/kredi|tl|₺|try|:/gi, ' ')
      .replace(/[^0-9.,-]/g, '')
      .trim();

    if (!cleaned) {
      return { numeric: '', formatted: '' };
    }

    if (cleaned.includes('.') && cleaned.includes(',')) {
      cleaned = cleaned.replace(/\./g, '').replace(',', '.');
    } else if (cleaned.includes(',')) {
      cleaned = cleaned.replace(',', '.');
    }

    const numericValue = Number.parseFloat(cleaned);
    if (Number.isNaN(numericValue)) {
      return { numeric: '', formatted: cleaned };
    }

    const isInteger = Number.isInteger(numericValue);
    return {
      numeric: numericValue.toFixed(isInteger ? 0 : 2),
      formatted: isInteger ? `${numericValue}` : numericValue.toFixed(2),
    };
  };

  const convertToLotusCsv = (records) => {
    const lines = [];
    lines.push('LOTUS PARTNERLİK SİSTEMİ - ÖZEL FİYAT LİSTESİ');
    lines.push('');
    lines.push(['ÜRÜN ADI', 'FİYAT (KREDİ)'].map(escapeForCsv).join(','));

    const hierarchy = new Map();
    records.forEach((record) => {
      const segments = (record.categoryPath || 'Kategorisiz')
        .split('>')
        .map((segment) => segment.trim())
        .filter(Boolean);
      const topLevel = segments.shift() || 'Kategorisiz';
      const remainder = segments.join(' > ');

      if (!hierarchy.has(topLevel)) {
        hierarchy.set(topLevel, new Map());
      }

      const subMap = hierarchy.get(topLevel);
      const key = remainder || null;
      if (!subMap.has(key)) {
        subMap.set(key, []);
      }
      subMap.get(key).push(record);
    });

    Array.from(hierarchy.entries())
      .sort(([a], [b]) => a.localeCompare(b, 'tr'))
      .forEach(([topLevel, subMap]) => {
        lines.push(`${escapeForCsv(topLevel)},`);

        const directItems = subMap.get(null) || [];
        if (directItems.length > 0) {
          directItems
            .sort((a, b) => a.name.localeCompare(b.name, 'tr'))
            .forEach((item) => {
              const { formatted } = parsePriceValue(item.price);
              lines.push([
                escapeForCsv(item.name),
                escapeForCsv(formatted || item.price),
              ].join(','));
            });
          lines.push('');
        }

        Array.from(subMap.entries())
          .filter(([key]) => key !== null)
          .sort(([a], [b]) => a.localeCompare(b, 'tr'))
          .forEach(([label, items]) => {
            const headerLabel = `${topLevel} > ${label}`;
            lines.push(`${escapeForCsv(headerLabel)},`);
            items
              .sort((a, b) => a.name.localeCompare(b.name, 'tr'))
              .forEach((item) => {
                const { formatted } = parsePriceValue(item.price);
                lines.push([
                  escapeForCsv(item.name),
                  escapeForCsv(formatted || item.price),
                ].join(','));
              });
            lines.push('');
          });

        lines.push('');
      });

    return CSV_BOM + lines.join('\r\n');
  };

  const formatCategoryPath = (path) => {
    const formatted = (path || '')
      .split('>')
      .map((segment) => segment.trim())
      .filter(Boolean)
      .join(' > ');
    return formatted || 'Kategorisiz';
  };

  const slugify = (value) =>
    value
      .toString()
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '')
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/(^-|-$)+/g, '');

  const convertToWooCommerceCsv = (records) => {
    const headers = [
      'Type',
      'SKU',
      'Name',
      'Published',
      'Is featured?',
      'Visibility in catalog',
      'Short description',
      'Description',
      'Tax status',
      'Tax class',
      'In stock?',
      'Stock',
      'Backorders allowed?',
      'Low stock amount',
      'Sold individually?',
      'Allow customer reviews?',
      'Sale price',
      'Regular price',
      'Categories',
      'Tags',
      'Images',
      'Download limit',
      'Download expiry days',
      'Parent',
      'Grouped products',
      'Upsells',
      'Cross-sells',
      'External URL',
      'Button text',
      'Position',
    ];

    const lines = [headers.map(escapeForCsv).join(',')];
    const skuTracker = new Map();

    records
      .slice()
      .sort((a, b) => a.name.localeCompare(b.name, 'tr'))
      .forEach((record) => {
        const { numeric, formatted } = parsePriceValue(record.price);
        const baseSku = slugify(record.name) || 'urun';
        const count = skuTracker.get(baseSku) || 0;
        skuTracker.set(baseSku, count + 1);
        const sku = count === 0 ? baseSku : `${baseSku}-${count + 1}`;

        const wooValues = [
          'simple',
          sku,
          record.name,
          '1',
          '0',
          'visible',
          '',
          `Kaynak: ${record.sourceUrl}`,
          'taxable',
          '',
          '1',
          '',
          'no',
          '',
          'no',
          '1',
          '',
          numeric || formatted || record.price,
          formatCategoryPath(record.categoryPath),
          '',
          '',
          '',
          '',
          '',
          '',
          '',
          '',
          '',
          '',
        ];

        lines.push(wooValues.map(escapeForCsv).join(','));
      });

    return CSV_BOM + lines.join('\r\n');
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
    const timestamp = new Date().toISOString().replace(/[:T]/g, '-').split('.')[0];
    const lotusCsv = convertToLotusCsv(records);
    const lotusFilename = `lotus-fiyat-listesi-${timestamp}.csv`;
    triggerDownload(lotusFilename, lotusCsv);

    const wooCsv = convertToWooCommerceCsv(records);
    const wooFilename = `lotus-woocommerce-${timestamp}.csv`;
    triggerDownload(wooFilename, wooCsv);

    notifyProgress({
      type: 'complete',
      message: `${records.length} ürün başarıyla indirildi. Dosyalar: ${lotusFilename}, ${wooFilename}`,
      filenames: [lotusFilename, wooFilename],
    });

    return {
      filenames: [lotusFilename, wooFilename],
      recordCount: records.length,
      visitedPages: processedCount,
    };
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
