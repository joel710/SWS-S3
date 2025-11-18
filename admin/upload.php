<?php include_once 'check_auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload File - Object Storage Admin</title>
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
            <h1>Upload a New File</h1>
            <form id="upload-form" enctype="multipart/form-data">
                <div class="form-group">
                    <label for="api_key">API Key:</label>
                    <input type="text" id="api_key" name="api_key" required>
                </div>
                <div class="form-group">
                    <label for="bucket">Bucket Name:</label>
                    <input type="text" id="bucket" name="bucket" required>
                </div>
                <div class="form-group">
                    <label for="file">File:</label>
                    <input type="file" id="file" name="file" required>
                </div>
                <button type="submit">Upload</button>
            </form>
            <div id="upload-status"></div>
        </main>
    </div>

    <script src="assets/upload.js"></script>
</body>
</html>
