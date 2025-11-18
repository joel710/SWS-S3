<?php include_once 'check_auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Buckets - Object Storage Admin</title>
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
            <h1>Buckets</h1>
            <div id="buckets-list">
                <!-- Buckets will be loaded here by JavaScript -->
            </div>
        </main>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            fetch('api.php?action=get_projects')
                .then(response => response.json())
                .then(projects => {
                    const bucketsList = document.getElementById('buckets-list');
                    projects.forEach(project => {
                        const projectDiv = document.createElement('div');
                        projectDiv.innerHTML = `<h2>${project.name}</h2>`;
                        bucketsList.appendChild(projectDiv);

                        fetch(`api.php?action=get_buckets&project_id=${project.id}`)
                            .then(response => response.json())
                            .then(buckets => {
                                buckets.forEach(bucket => {
                                    projectDiv.innerHTML += `<p>${bucket.name} (${bucket.is_public ? 'Public' : 'Private'})</p>`;
                                });
                            });
                    });
                });
        });
    </script>
</body>
    </div>
</body>
</html>
