const startButton = document.getElementById('start-scan');
const statusContainer = document.getElementById('status');

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

const triggerScrape = async () => {
  setLoading(true);
  statusContainer.textContent = '';
  appendStatus('Tarama başlatılıyor...');

  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });
  if (!tab || !tab.id) {
    appendStatus('Aktif sekme bulunamadı.', 'error');
    setLoading(false);
    return;
  }

  chrome.tabs.sendMessage(tab.id, { action: 'scrapeProducts' }, (response) => {
    if (!response) {
      const lastError = chrome.runtime.lastError;
      appendStatus(lastError ? lastError.message : 'İletişim hatası oluştu.', 'error');
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
  });
};

startButton.addEventListener('click', triggerScrape);
