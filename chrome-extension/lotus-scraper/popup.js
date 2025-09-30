const startButton = document.getElementById('start-scan');
const statusContainer = document.getElementById('status');
const LOTUS_HOST_PATTERN = /^https:\/\/partner\.lotuslisans\.com\.tr\//;

const appendStatus = (message, type = 'info') => {
  const line = document.createElement('div');
  line.className = `status-line ${type}`;
  line.textContent = message;
  statusContainer.appendChild(line);
  statusContainer.scrollTop = statusContainer.scrollHeight;
};

const setLoading = (isLoading) => {
  startButton.disabled = isLoading;
  startButton.textContent = isLoading ? 'Taranıyor...' : 'Taramayı Başlat';
};

chrome.runtime.onMessage.addListener((message) => {
  if (message?.action !== 'scrapeProgress') {
    return;
  }

  if (message.type === 'status') {
    appendStatus(message.message, 'info');
  } else if (message.type === 'error') {
    appendStatus(message.message, 'error');
    setLoading(false);
  } else if (message.type === 'complete') {
    appendStatus(message.message, 'success');
    if (Array.isArray(message.filenames)) {
      message.filenames.forEach((name) => {
        appendStatus(`İndirilen dosya: ${name}`, 'success');
      });
    }
    setLoading(false);
  }
});

const runInTab = async (tabId, func) => {
  try {
    const [result] = await chrome.scripting.executeScript({
      target: { tabId },
      func,
    });
    return result?.result;
  } catch (error) {
    appendStatus(`Sekmede komut çalıştırılamadı: ${error.message}`, 'error');
    return undefined;
  }
};

const ensureContentScript = async (tabId) => {
  const isReady = await runInTab(tabId, () => Boolean(window.__lotusScraperInitialized));
  if (isReady) {
    return true;
  }

  appendStatus('Sayfa hazır değil. İçerik betiği yükleniyor...', 'info');

  try {
    await chrome.scripting.executeScript({
      target: { tabId },
      files: ['content-script.js'],
    });
  } catch (error) {
    appendStatus(`İçerik betiği yüklenemedi: ${error.message}`, 'error');
    return false;
  }

  const readyAfterInject = await runInTab(tabId, () => Boolean(window.__lotusScraperInitialized));
  if (!readyAfterInject) {
    appendStatus('İçerik betiği başlatılamadı.', 'error');
    return false;
  }

  appendStatus('İçerik betiği yüklendi, tarama başlatılıyor...', 'info');
  return true;
};

const sendScrapeCommand = (tabId) =>
  new Promise((resolve, reject) => {
    chrome.tabs.sendMessage(tabId, { action: 'scrapeProducts' }, (response) => {
      const lastError = chrome.runtime.lastError;
      if (lastError) {
        reject(lastError);
        return;
      }
      resolve(response);
    });
  });

const triggerScrape = async () => {
  setLoading(true);
  statusContainer.textContent = '';
  appendStatus('Tarama başlatılıyor...');

  let tab;
  try {
    [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  } catch (error) {
    appendStatus(`Sekme bilgisi alınamadı: ${error.message}`, 'error');
    setLoading(false);
    return;
  }

  if (!tab || !tab.id) {
    appendStatus('Aktif sekme bulunamadı.', 'error');
    setLoading(false);
    return;
  }

  if (!tab.url || !LOTUS_HOST_PATTERN.test(tab.url)) {
    appendStatus('Lütfen Lotus Partner kategori sayfasını açtıktan sonra tekrar deneyin.', 'error');
    setLoading(false);
    return;
  }

  const ensured = await ensureContentScript(tab.id);
  if (!ensured) {
    setLoading(false);
    return;
  }

  let response;
  try {
    response = await sendScrapeCommand(tab.id);
  } catch (error) {
    appendStatus(error.message || 'İletişim hatası oluştu.', 'error');
    setLoading(false);
    return;
  }

  if (!response) {
    appendStatus('Beklenmeyen yanıt alındı.', 'error');
    setLoading(false);
    return;
  }

  if (!response.success) {
    appendStatus(response.error || 'Bilinmeyen hata oluştu.', 'error');
    setLoading(false);
    return;
  }

  const { result } = response;
  appendStatus(`Toplam ${result.recordCount} ürün bulundu.`, 'success');
  if (Array.isArray(result.filenames)) {
    result.filenames.forEach((name) => {
      appendStatus(`İndirilen dosya: ${name}`, 'success');
    });
  }
  setLoading(false);
};

startButton.addEventListener('click', triggerScrape);
