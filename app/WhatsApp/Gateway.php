<?php

declare(strict_types=1);

namespace App\WhatsApp;

use App\Environment;
use DateTimeImmutable;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Exception\NoSuchElementException;
use Facebook\WebDriver\Exception\TimeoutException;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition as ExpectedCondition;
use Facebook\WebDriver\WebDriverWait;
use Monolog\Logger;
use PDO;
use RuntimeException;

class Gateway
{
    private PDO $db;
    private Logger $logger;
    /** @var array<string,mixed> */
    private array $session;
    private ?RemoteWebDriver $driver = null;

    public function __construct(PDO $db, Logger $logger, array $session)
    {
        $this->db = $db;
        $this->logger = $logger;
        $this->session = $session;
    }

    public function __destruct()
    {
        if ($this->driver instanceof RemoteWebDriver) {
            try {
                $this->driver->quit();
            } catch (\Throwable $e) {
                $this->logger->warning('Failed to close WebDriver', ['error' => $e->getMessage()]);
            }
        }
    }

    public function getStatus(): array
    {
        $driver = $this->getDriver();
        $driver->get('https://web.whatsapp.com/');

        $wait = new WebDriverWait($driver, 15);
        try {
            $wait->until(ExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('canvas')));
        } catch (TimeoutException $exception) {
            $this->logger->debug('Canvas not present, possibly already logged in', ['error' => $exception->getMessage()]);
        }

        if ($this->isConnected($driver)) {
            $this->markConnected();
            return [
                'status' => 'connected',
                'last_seen' => $this->session['last_seen'] ?? null,
                'qr' => null,
            ];
        }

        $qr = $this->captureQr($driver);
        $this->markPending();

        return [
            'status' => 'pending',
            'last_seen' => $this->session['last_seen'] ?? null,
            'qr' => $qr,
        ];
    }

    public function sendMessage(string $phoneE164, string $text, array $options = []): void
    {
        $driver = $this->getDriver();
        $query = http_build_query([
            'phone' => preg_replace('/[^0-9]/', '', $phoneE164),
            'text' => $text,
        ]);

        $url = 'https://web.whatsapp.com/send?' . $query;
        $this->logger->info('Navigating to send URL', ['url' => $url]);
        $driver->get($url);

        $wait = new WebDriverWait($driver, 30);
        try {
            $wait->until(ExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('div[data-testid="conversation-panel"]')));
        } catch (TimeoutException $exception) {
            throw new RuntimeException('Conversation did not load in time: ' . $exception->getMessage(), 0, $exception);
        }

        try {
            $wait->until(ExpectedCondition::elementToBeClickable(WebDriverBy::cssSelector('span[data-icon="send"]')));
            $sendButton = $driver->findElement(WebDriverBy::cssSelector('span[data-icon="send"]'));
            $sendButton->click();
        } catch (NoSuchElementException|TimeoutException $exception) {
            throw new RuntimeException('Send button not available: ' . $exception->getMessage(), 0, $exception);
        }

        $wait->until(function () use ($driver, $text) {
            $messages = $driver->findElements(WebDriverBy::cssSelector('div[role="row"] span.selectable-text span'));
            foreach ($messages as $message) {
                if ($message->getText() === $text) {
                    return true;
                }
            }
            return false;
        });

        $this->markConnected();
        $this->logger->info('Message sent successfully', ['phone' => $phoneE164]);
    }

    private function getDriver(): RemoteWebDriver
    {
        if ($this->driver instanceof RemoteWebDriver) {
            return $this->driver;
        }

        $seleniumHost = Environment::get('SELENIUM_HOST', 'http://selenium:4444/wd/hub') ?? 'http://selenium:4444/wd/hub';
        $sessionBase = Environment::get('WHATSAPP_SESSION_DIR_BASE', dirname(__DIR__, 2) . '/storage/sessions');
        $sessionDir = rtrim($sessionBase ?? '', '/') . '/' . $this->session['client_id'];
        if (!is_dir($sessionDir)) {
            mkdir($sessionDir, 0775, true);
        }

        $options = new ChromeOptions();
        $options->addArguments([
            '--user-data-dir=' . $sessionDir,
            '--profile-directory=Default',
            '--disable-dev-shm-usage',
            '--no-sandbox',
            '--disable-gpu',
            '--remote-allow-origins=*',
        ]);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        $this->driver = RemoteWebDriver::create($seleniumHost, $capabilities, 60000, 60000);
        $this->driver->manage()->timeouts()->implicitlyWait(5);

        return $this->driver;
    }

    private function captureQr(RemoteWebDriver $driver): ?string
    {
        try {
            $wait = new WebDriverWait($driver, 20);
            $wait->until(ExpectedCondition::presenceOfElementLocated(WebDriverBy::cssSelector('canvas')));
            $dataUrl = $driver->executeScript(<<<'JS'
                return (function() {
                    const canvas = document.querySelector('canvas');
                    if (!canvas) {
                        return null;
                    }
                    return canvas.toDataURL('image/png');
                })();
            JS
            );

            if (is_string($dataUrl) && str_starts_with($dataUrl, 'data:image')) {
                return $dataUrl;
            }
        } catch (TimeoutException $exception) {
            $this->logger->warning('QR code not available yet', ['error' => $exception->getMessage()]);
        }

        return null;
    }

    private function isConnected(RemoteWebDriver $driver): bool
    {
        try {
            $driver->findElement(WebDriverBy::cssSelector('div[data-testid="chatlist"]'));
            return true;
        } catch (NoSuchElementException $exception) {
            $this->logger->debug('Chat list not found', ['error' => $exception->getMessage()]);
        }

        return false;
    }

    private function markConnected(): void
    {
        $now = new DateTimeImmutable('now');
        $stmt = $this->db->prepare('UPDATE whatsapp_sessions SET status = :status, last_seen = :last_seen, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':status' => 'connected',
            ':last_seen' => $now->format('Y-m-d H:i:s'),
            ':updated_at' => $now->format('Y-m-d H:i:s'),
            ':id' => $this->session['id'],
        ]);
        $this->session['status'] = 'connected';
        $this->session['last_seen'] = $now->format('Y-m-d H:i:s');
    }

    private function markPending(): void
    {
        $now = new DateTimeImmutable('now');
        $stmt = $this->db->prepare('UPDATE whatsapp_sessions SET status = :status, updated_at = :updated_at WHERE id = :id');
        $stmt->execute([
            ':status' => 'pending',
            ':updated_at' => $now->format('Y-m-d H:i:s'),
            ':id' => $this->session['id'],
        ]);
        $this->session['status'] = 'pending';
    }
}
