<?php
/**
 * Production configuration for the reseller platform.
 *
 * This file defines the fixed database credentials supplied by the operator so
 * the application can connect without relying on the installer flow.
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'maxipros_resellers');
define('DB_USER', 'maxipros_resellers');
define('DB_PASSWORD', 'j.5LpgvX90tfHe[6');

// Optional integrations can be populated later if needed.
define('TELEGRAM_BOT_TOKEN', '');
define('TELEGRAM_CHAT_ID', '');

define('DEFAULT_LANGUAGE', 'tr');
