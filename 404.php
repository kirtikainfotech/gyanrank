<?php
require_once __DIR__ . '/config.php';
http_response_code(404);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 - Page Not Found</title>
    <link rel="stylesheet" href="<?= app_url('assets/css/login.css'); ?>">
</head>
<body class="error-page">
    <section class="error-box">
        <h1>404</h1>
        <p>Page not found.</p>
        <a href="<?= app_url('index'); ?>">Back to Home</a>
    </section>
</body>
</html>
