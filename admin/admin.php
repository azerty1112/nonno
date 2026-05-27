<?php
/**
 * Admin Panel - Main Entry Point
 * Orchestrates authentication and dashboard
 */

session_start();

// Check authentication
require_once __DIR__ . '/auth.php';

// Load dashboard data and handle requests
require_once __DIR__ . '/data.php';

// Render dashboard view
require_once __DIR__ . '/views/dashboard.php';
