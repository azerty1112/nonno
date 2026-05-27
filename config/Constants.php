<?php
/**
 * Application Constants
 * Define all application-wide constants and defaults
 */

namespace Config;

class Constants
{
    // ========================================================================
    // PATHS
    // ========================================================================
    public const APP_ROOT = __DIR__ . '/..';
    public const CONFIG_DIR = self::APP_ROOT . '/config';
    public const SRC_DIR = self::APP_ROOT . '/src';
    public const PUBLIC_DIR = self::APP_ROOT . '/public';
    public const VIEWS_DIR = self::APP_ROOT . '/admin/views';
    public const HANDLERS_DIR = self::APP_ROOT . '/admin/handlers';
    public const SCRIPTS_DIR = self::APP_ROOT . '/scripts';
    public const DATA_DIR = self::APP_ROOT . '/data';
    public const VENDOR_DIR = self::APP_ROOT . '/vendor';

    // ========================================================================
    // DATABASE
    // ========================================================================
    public const DB_DEFAULT_PATH = self::DATA_DIR . '/data.db';
    public const DB_TIMEOUT = 5000; // milliseconds
    public const DB_BUSY_TIMEOUT = 5000; // milliseconds

    // ========================================================================
    // CONTENT LIMITS
    // ========================================================================
    public const MIN_ARTICLE_TITLE_LENGTH = 3;
    public const MAX_ARTICLE_TITLE_LENGTH = 500;
    public const MIN_ARTICLE_SLUG_LENGTH = 3;
    public const MAX_ARTICLE_SLUG_LENGTH = 255;
    public const MIN_ARTICLE_CONTENT_LENGTH = 100;
    public const MAX_ARTICLE_CONTENT_LENGTH = 100000; // characters
    public const MIN_AUTO_TAG_COUNT = 1;
    public const MAX_AUTO_TAG_COUNT = 10;

    // ========================================================================
    // SETTINGS LIMITS
    // ========================================================================
    public const MAX_SITE_TITLE_LENGTH = 100;
    public const MAX_SEO_TITLE_LENGTH = 120;
    public const MAX_SEO_DESCRIPTION_LENGTH = 160;
    public const MAX_TWITTER_HANDLE_LENGTH = 50;
    public const MAX_ADMIN_PASSWORD_LENGTH = 255;
    public const MIN_ADMIN_PASSWORD_LENGTH = 8;

    // ========================================================================
    // SCRIPT & CODE LIMITS
    // ========================================================================
    public const MAX_CUSTOM_SCRIPT_SIZE = 20 * 1024; // 20KB
    public const MAX_AD_CODE_SIZE = 12 * 1024; // 12KB
    public const MAX_ADS_TXT_SIZE = 100 * 1024; // 100KB

    // ========================================================================
    // FETCH & QUEUE
    // ========================================================================
    public const FETCH_TIMEOUT_DEFAULT = 12; // seconds
    public const FETCH_TIMEOUT_MIN = 3;
    public const FETCH_TIMEOUT_MAX = 45;
    public const FETCH_RETRY_DEFAULT = 3;
    public const FETCH_RETRY_MIN = 1;
    public const FETCH_RETRY_MAX = 5;
    public const FETCH_BACKOFF_DEFAULT = 350; // milliseconds
    public const FETCH_BACKOFF_MIN = 100;
    public const FETCH_BACKOFF_MAX = 3000;

    // ========================================================================
    // QUEUE & SCHEDULING
    // ========================================================================
    public const QUEUE_BATCH_SIZE_MIN = 1;
    public const QUEUE_BATCH_SIZE_MAX = 50;
    public const QUEUE_BATCH_SIZE_DEFAULT = 8;
    public const QUEUE_RETRY_DELAY_MIN = 5; // seconds
    public const QUEUE_RETRY_DELAY_MAX = 7200; // 2 hours
    public const QUEUE_RETRY_ATTEMPTS_MIN = 1;
    public const QUEUE_RETRY_ATTEMPTS_MAX = 20;
    public const SOURCE_COOLDOWN_MIN = 30; // seconds
    public const SOURCE_COOLDOWN_MAX = 7200; // 2 hours

    // ========================================================================
    // CACHING
    // ========================================================================
    public const CACHE_TTL_MIN = 60; // seconds
    public const CACHE_TTL_MAX = 86400; // 24 hours
    public const CACHE_TTL_DEFAULT = 900; // 15 minutes

    // ========================================================================
    // AUTO-PUBLISH INTERVAL
    // ========================================================================
    public const AUTO_PUBLISH_INTERVAL_MIN = 1; // minute
    public const AUTO_PUBLISH_INTERVAL_MAX = 1440; // 24 hours
    public const AUTO_PUBLISH_INTERVAL_DEFAULT = 180; // 3 hours

    // ========================================================================
    // ADS CONFIGURATION
    // ========================================================================
    public const ADS_INJECTION_MODES = ['smart', 'interval'];
    public const ADS_PARAGRAPH_INTERVAL_MIN = 2;
    public const ADS_PARAGRAPH_INTERVAL_MAX = 10;
    public const ADS_MAX_UNITS_MIN = 1;
    public const ADS_MAX_UNITS_MAX = 6;
    public const ADS_MIN_WORDS_BEFORE_FIRST_MIN = 80;
    public const ADS_MIN_WORDS_BEFORE_FIRST_MAX = 600;
    public const ADS_MIN_ARTICLE_WORDS_MIN = 120;
    public const ADS_MIN_ARTICLE_WORDS_MAX = 3000;
    public const ADS_LABEL_MAX_LENGTH = 40;

    // ========================================================================
    // AUTO TITLE GENERATION
    // ========================================================================
    public const TITLE_TEMPLATE_MODE = 'template';
    public const TITLE_LIST_MODE = 'list';
    public const TITLE_MODES = [self::TITLE_TEMPLATE_MODE, self::TITLE_LIST_MODE];
    public const TITLE_YEAR_OFFSET_MIN = -1;
    public const TITLE_YEAR_OFFSET_MAX = 3;
    public const TITLE_VARIABLES = ['{year}', '{brand}', '{model}', '{modifier}', '{angle}', '{audience}'];

    // ========================================================================
    // SECURITY
    // ========================================================================
    public const SESSION_TIMEOUT = 3600; // seconds (1 hour)
    public const CSRF_TOKEN_LENGTH = 32;
    public const CSRF_TOKEN_EXPIRY_HOURS = 24;
    public const LOGIN_MAX_ATTEMPTS = 5;
    public const LOGIN_LOCKOUT_DURATION = 60; // seconds

    // ========================================================================
    // PAGINATION
    // ========================================================================
    public const ARTICLES_PER_PAGE = 20;
    public const SOURCES_PER_PAGE = 20;
    public const SETTINGS_PER_PAGE = 25;
    public const PAGE_VISITS_PER_PAGE = 50;

    // ========================================================================
    // ANALYTICS
    // ========================================================================
    public const PAGE_VISITS_RETENTION_DAYS = 30; // Keep 30 days of data
    public const PAGE_VISITS_MAX_RECORDS = 100000; // Max records before cleanup

    // ========================================================================
    // API
    // ========================================================================
    public const API_RESPONSE_LIMIT = 100; // Max items per API response
    public const API_TIMEOUT = 30; // seconds
    public const API_VERSION = '1.0';

    // ========================================================================
    // VALIDATION PATTERNS
    // ========================================================================
    public const PATTERN_SLUG = '/^[a-z0-9\-]+$/';
    public const PATTERN_SETTING_KEY = '/^[a-z0-9_\-.]{2,80}$/i';
    public const PATTERN_EMAIL = '/^[^\s@]+@[^\s@]+\.[^\s@]+$/';
    public const PATTERN_URL = '/^https?:\/\/.+/';
    public const PATTERN_GA_ID = '/^(G-|UA-|AW-)[A-Z0-9]+$/';
    public const PATTERN_GTM_ID = '/^GTM-[A-Z0-9]+$/';
    public const PATTERN_TWITTER_HANDLE = '/^@?[a-zA-Z0-9_]{1,15}$/';

    // ========================================================================
    // WORKFLOWS
    // ========================================================================
    public const WORKFLOW_RSS = 'rss';
    public const WORKFLOW_WEB = 'web';
    public const WORKFLOWS = [self::WORKFLOW_RSS, self::WORKFLOW_WEB];

    // ========================================================================
    // ROBOTS DIRECTIVE
    // ========================================================================
    public const ROBOTS_INDEX = 'index';
    public const ROBOTS_NOINDEX = 'noindex';
    public const ROBOTS_FOLLOW = 'follow';
    public const ROBOTS_NOFOLLOW = 'nofollow';
    public const ROBOTS_VALID_OPTIONS = [
        self::ROBOTS_INDEX,
        self::ROBOTS_NOINDEX,
        self::ROBOTS_FOLLOW,
        self::ROBOTS_NOFOLLOW,
    ];

    // ========================================================================
    // TRANSLATION LANGUAGES
    // ========================================================================
    public const TRANSLATION_LANGUAGES = [
        'ar' => 'العربية',
        'en' => 'English',
        'fr' => 'Français',
        'de' => 'Deutsch',
        'es' => 'Español',
        'pt' => 'Português',
        'it' => 'Italiano',
    ];
}
