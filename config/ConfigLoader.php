<?php
/**
 * Application Configuration Loader
 * Loads settings from multiple sources with priority:
 * 1. Environment variables (.env file)
 * 2. config.txt file
 * 3. Database settings
 * 4. Default constants
 */

namespace Config;

class ConfigLoader
{
    private static $config = [];
    private static $loaded = false;

    /**
     * Load all configuration
     */
    public static function load(): void
    {
        if (self::$loaded) {
            return;
        }

        // Load from .env file
        self::loadEnvFile();

        // Load from config.txt
        self::loadConfigFile();

        self::$loaded = true;
    }

    /**
     * Load .env file if it exists
     */
    private static function loadEnvFile(): void
    {
        $envFile = __DIR__ . '/../.env';
        if (!file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = trim($parts[0]);
            $value = trim($parts[1]);

            // Remove quotes if present
            if (($value[0] ?? null) === '"' && substr($value, -1) === '"') {
                $value = substr($value, 1, -1);
            }

            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }

    /**
     * Load config.txt file if it exists
     */
    private static function loadConfigFile(): void
    {
        $configFile = __DIR__ . '/../config.txt';
        if (!file_exists($configFile)) {
            return;
        }

        $lines = file($configFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || $line[0] === '#') {
                continue;
            }

            $parts = explode('=', $line, 2);
            if (count($parts) !== 2) {
                continue;
            }

            $key = strtoupper(trim($parts[0]));
            $value = trim($parts[1]);

            self::$config[$key] = $value;
        }
    }

    /**
     * Get a configuration value
     */
    public static function get(string $key, $default = null)
    {
        self::load();

        $key = strtoupper($key);

        // Check environment variables first
        if (isset($_ENV[$key])) {
            return self::parseValue($_ENV[$key]);
        }

        // Check config array
        if (isset(self::$config[$key])) {
            return self::parseValue(self::$config[$key]);
        }

        return $default;
    }

    /**
     * Parse configuration value (convert to appropriate type)
     */
    private static function parseValue($value)
    {
        if (is_bool($value)) {
            return $value;
        }

        $value = (string)$value;

        // Handle boolean strings
        if (in_array(strtolower($value), ['true', '1', 'yes', 'on'], true)) {
            return true;
        }
        if (in_array(strtolower($value), ['false', '0', 'no', 'off'], true)) {
            return false;
        }

        // Handle numeric strings
        if (is_numeric($value)) {
            return strpos($value, '.') !== false ? (float)$value : (int)$value;
        }

        return $value;
    }

    /**
     * Set a configuration value
     */
    public static function set(string $key, $value): void
    {
        self::load();
        self::$config[strtoupper($key)] = $value;
    }

    /**
     * Get all loaded configuration
     */
    public static function all(): array
    {
        self::load();
        return self::$config;
    }
}

// Auto-load on require
ConfigLoader::load();
