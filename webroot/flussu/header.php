<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle : 'Flussu Dashboard'; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background-color: #f5f5f5;
            color: #333;
        }

        .header {
            background-color: #2c3e50;
            color: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header-content {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-left h1 {
            font-size: 1.5rem;
            font-weight: 600;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 1rem;
            background-color: rgba(255,255,255,0.1);
            border-radius: 8px;
        }

        .user-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background-color: #3498db;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1rem;
        }

        .user-details {
            display: flex;
            flex-direction: column;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.95rem;
        }

        .user-email {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .btn {
            padding: 0.5rem 1.25rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            text-decoration: none;
            transition: all 0.2s;
            display: inline-block;
        }

        .btn-logout {
            background-color: #e74c3c;
            color: white;
        }

        .btn-logout:hover {
            background-color: #c0392b;
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
        }

        .nav-links {
            display: flex;
            gap: 1rem;
        }

        .nav-link {
            color: white;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            transition: background-color 0.2s;
        }

        .nav-link:hover {
            background-color: rgba(255,255,255,0.1);
        }

        .nav-link.active {
            background-color: rgba(255,255,255,0.2);
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <h1>Flussu Dashboard</h1>
                <nav class="nav-links">
                    <a href="dashboard.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) === 'dashboard.php' ? 'active' : ''; ?>">I miei Workflow</a>
                </nav>
            </div>
            <div class="header-right">
                <?php
                $currentUser = getCurrentUser();
                if ($currentUser):
                    $displayName = trim($currentUser->getDisplayName());
                    if (empty($displayName)) {
                        $displayName = $currentUser->getUserId();
                    }
                    $initials = '';
                    $nameParts = explode(' ', $displayName);
                    foreach ($nameParts as $part) {
                        if (!empty($part)) {
                            $initials .= strtoupper(substr($part, 0, 1));
                        }
                    }
                    if (empty($initials)) {
                        $initials = strtoupper(substr($currentUser->getUserId(), 0, 2));
                    }
                ?>
                <div class="user-info">
                    <div class="user-avatar"><?php echo htmlspecialchars($initials); ?></div>
                    <div class="user-details">
                        <span class="user-name"><?php echo htmlspecialchars($displayName); ?></span>
                        <span class="user-email"><?php echo htmlspecialchars($currentUser->getEmail()); ?></span>
                    </div>
                </div>
                <a href="?action=logout" class="btn btn-logout">Logout</a>
                <?php endif; ?>
            </div>
        </div>
    </header>
    <div class="container">
