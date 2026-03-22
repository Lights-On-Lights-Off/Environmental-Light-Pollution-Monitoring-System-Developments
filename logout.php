<?php
require_once __DIR__ . '/config/session.php';
session_destroy();
header('Location: ' . rootUrl('index.php'));
exit;
