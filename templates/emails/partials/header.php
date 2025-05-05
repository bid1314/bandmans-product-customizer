<!DOCTYPE html>
<html>
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        .email-wrapper {
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .email-header {
            background: #f8f9fa;
            padding: 20px;
            text-align: center;
            border-radius: 4px 4px 0 0;
        }
        .email-logo {
            max-width: 200px;
            margin-bottom: 10px;
        }
        .email-content {
            background: #ffffff;
            padding: 30px;
            border: 1px solid #e9ecef;
            border-radius: 0 0 4px 4px;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background: #0073aa;
            color: #ffffff;
            text-decoration: none;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="email-wrapper">
        <div class="email-header">
            <?php if ($logo_url = get_theme_mod('custom_logo')): ?>
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" class="email-logo">
            <?php else: ?>
                <h1><?php echo esc_html($site_name); ?></h1>
            <?php endif; ?>
        </div>
        <div class="email-content">
