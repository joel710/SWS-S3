<?php include_once 'check_auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Object Storage Admin</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="admin-layout">
        <!-- Header -->
        <header class="header">
            <div class="logo">Object Storage</div>
            <div class="user-menu"><span>Admin</span></div>
        </header>

        <!-- Sidebar -->
        <aside class="sidebar">
            <nav>
                <ul>
                    <li><a href="index.php">Projects</a></li>
                    <li><a href="buckets.php">Buckets</a></li>
                    <li><a href="upload.php">Upload</a></li>
                    <li><a href="analytics.php">Analytics</a></li>
                    <li><a href="signed_urls.php">Signed URLs</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <h1>Analytics</h1>
            <div id="analytics-data">
                <!-- Analytics data will be loaded here -->
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            fetch('api.php?action=get_analytics')
                .then(response => response.json())
                .then(data => {
                    const analyticsDiv = document.getElementById('analytics-data');
                    analyticsDiv.innerHTML = `
                        <p>Total Projects: ${data.total_projects}</p>
                        <p>Total Buckets: ${data.total_buckets}</p>
                        <p>Total Objects: ${data.total_objects}</p>
                        <p>Total Storage Used: ${data.total_storage_used} bytes</p>
                    `;
                });
        });
    </script>
</body>
    </div>
</body>
</html>
