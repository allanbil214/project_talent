<?php
// public/logout.php

require_once __DIR__ . '/../config/session.php';

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Database.php';
require_once __DIR__ . '/../classes/User.php';
require_once __DIR__ . '/../classes/Auth.php';

$database = new Database($db);
$auth = new Auth($database);

// Logout
$auth->logout();

// Set flash message
setFlash('success', 'You have been logged out successfully.');

// Redirect to login
redirect(SITE_URL . '/public/login.php');