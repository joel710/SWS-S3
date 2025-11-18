<?php include_once 'check_auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Object Storage Admin</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="admin-layout">
        <!-- Header -->
        <header class="header">
            <div class="logo">Object Storage</div>
            <div class="search-bar">
                <input type="text" placeholder="Search...">
            </div>
            <div class="user-menu">
                <span>Admin</span>
            </div>
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
            <h1>Projects</h1>

            <div id="projects-list">
                <!-- Projects will be loaded here by JavaScript -->
            </div>

            <hr>

            <h2>Add New Project</h2>
            <form id="add-project-form">
                <input type="text" name="name" placeholder="Project Name" required>
                <button type="submit">Add Project</button>
            </form>
        </main>
    </div>

    <script src="assets/main.js"></script>
</body>
</html>
