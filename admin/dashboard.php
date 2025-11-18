<?php include_once 'check_auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Object Storage Dashboard</title>
    <link rel="stylesheet" href="assets/style.css">
    <style>
        /* Dashboard specific styles */
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .dashboard-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #e0e0e0;
        }

        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-healthy { background: #d4edda; color: #155724; }
        .status-degraded { background: #fff3cd; color: #856404; }
        .status-unhealthy { background: #f8d7da; color: #721c24; }

        .service-status {
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 5px 0;
        }

        .status-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
        }

        .status-indicator.green { background: #28a745; }
        .status-indicator.red { background: #dc3545; }
        .status-indicator.yellow { background: #ffc107; }

        .progress-bar-container {
            width: 100%;
            height: 8px;
            background: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 5px 0;
        }

        .progress-bar {
            height: 100%;
            background: #007bff;
            transition: width 0.3s ease;
        }

        .progress-bar.warning { background: #ffc107; }
        .progress-bar.normal { background: #28a745; }
        .progress-bar.progress-active {
            background: linear-gradient(90deg, #007bff, #0056b3);
            animation: progress-pulse 2s infinite;
        }

        @keyframes progress-pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        .jobs-list {
            max-height: 400px;
            overflow-y: auto;
        }

        .job-item {
            border: 1px solid #e0e0e0;
            border-radius: 6px;
            padding: 15px;
            margin-bottom: 10px;
            background: #f8f9fa;
        }

        .job-item.status-completed {
            border-left: 4px solid #28a745;
            background: #f8fff9;
        }

        .job-item.status-failed {
            border-left: 4px solid #dc3545;
            background: #fff8f8;
        }

        .job-item.status-processing {
            border-left: 4px solid #007bff;
            background: #f0f8ff;
        }

        .job-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .job-type {
            font-weight: bold;
            color: #495057;
        }

        .job-status {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }

        .status-pending {
            background: #e9ecef;
            color: #6c757d;
        }

        .status-processing {
            background: #cce5ff;
            color: #004085;
        }

        .status-completed {
            background: #d4edda;
            color: #155724;
        }

        .status-failed {
            background: #f8d7da;
            color: #721c24;
        }

        .job-progress {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .progress-text {
            font-size: 12px;
            color: #6c757d;
            min-width: 40px;
        }

        .job-details {
            font-size: 12px;
            color: #6c757d;
        }

        .error-message {
            color: #dc3545;
            font-size: 11px;
            margin-top: 5px;
            padding: 5px;
            background: #f8d7da;
            border-radius: 3px;
        }

        .metrics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }

        .metric-item {
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .metric-value {
            font-size: 24px;
            font-weight: bold;
            color: #495057;
        }

        .metric-label {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }

        .objects-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .object-card {
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            padding: 15px;
            background: white;
        }

        .object-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 10px;
        }

        .object-header h4 {
            margin: 0;
            font-size: 14px;
            word-break: break-word;
        }

        .file-type {
            font-size: 11px;
            color: #6c757d;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
        }

        .object-info p {
            margin: 5px 0;
            font-size: 12px;
            color: #6c757d;
        }

        .object-info .success {
            color: #28a745;
        }

        .object-info .warning {
            color: #ffc107;
        }

        .object-actions {
            display: flex;
            gap: 5px;
            margin-top: 10px;
            flex-wrap: wrap;
        }

        .object-actions button {
            font-size: 11px;
            padding: 4px 8px;
            border: none;
            border-radius: 3px;
            cursor: pointer;
        }

        .btn-optimize { background: #007bff; color: white; }
        .btn-view { background: #28a745; color: white; }
        .btn-download { background: #6c757d; color: white; }
        .btn-delete { background: #dc3545; color: white; }
        .btn-small {
            font-size: 10px;
            padding: 2px 6px;
            margin: 0 2px;
            text-decoration: none;
            background: #6c757d;
            color: white;
            border-radius: 3px;
        }

        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }

        .modal-content {
            background: white;
            margin: 5% auto;
            padding: 20px;
            border-radius: 8px;
            width: 90%;
            max-width: 800px;
            max-height: 80vh;
            overflow-y: auto;
        }

        .thumbnail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .thumbnail-item {
            text-align: center;
        }

        .thumbnail-item img {
            max-width: 100%;
            height: auto;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            margin: 10px 0;
        }

        .thumbnail-actions {
            display: flex;
            justify-content: center;
            gap: 5px;
        }

        .close-modal {
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            color: #aaa;
        }

        .close-modal:hover {
            color: #000;
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <!-- Header -->
        <header class="header">
            <div class="logo">Object Storage Dashboard</div>
            <div class="search-bar">
                <input type="text" placeholder="Search...">
            </div>
            <div class="user-menu">
                <span>Admin</span>
                <span id="system-status" class="status-badge status-healthy">LOADING</span>
            </div>
        </header>

        <!-- Sidebar -->
        <aside class="sidebar">
            <nav>
                <ul>
                    <li><a href="index.php">Projects</a></li>
                    <li><a href="dashboard.php" class="active">Dashboard</a></li>
                    <li><a href="buckets.php">Buckets</a></li>
                    <li><a href="upload.php">Upload</a></li>
                    <li><a href="analytics.php">Analytics</a></li>
                    <li><a href="signed_urls.php">Signed URLs</a></li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <h1>System Dashboard</h1>

            <div class="dashboard-grid">
                <!-- System Status Card -->
                <div class="dashboard-card">
                    <h3>System Status</h3>
                    <div id="system-services">
                        <div class="service-status" id="status-database">
                            <span class="status-indicator green"></span>
                            <span>Database</span>
                        </div>
                        <div class="service-status" id="status-storage">
                            <span class="status-indicator green"></span>
                            <span>Storage</span>
                        </div>
                        <div class="service-status" id="status-image_processing">
                            <span class="status-indicator green"></span>
                            <span>Image Processing</span>
                        </div>
                        <div class="service-status" id="status-memory">
                            <span class="status-indicator green"></span>
                            <span>Memory</span>
                        </div>
                    </div>
                    <div style="margin-top: 15px; font-size: 12px; color: #6c757d;">
                        <div>PHP Version: <span id="php-version">Loading...</span></div>
                        <div>System Load: <span id="system-uptime">Loading...</span></div>
                    </div>
                </div>

                <!-- Performance Metrics Card -->
                <div class="dashboard-card">
                    <h3>Performance Metrics</h3>
                    <div class="metrics-grid">
                        <div class="metric-item">
                            <div class="metric-value" id="total-objects">-</div>
                            <div class="metric-label">Total Objects</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value" id="total-projects">-</div>
                            <div class="metric-label">Projects</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value" id="total-storage">-</div>
                            <div class="metric-label">Storage Used</div>
                        </div>
                        <div class="metric-item">
                            <div class="metric-value" id="active-jobs">-</div>
                            <div class="metric-label">Active Jobs</div>
                        </div>
                    </div>
                </div>

                <!-- Resource Usage Card -->
                <div class="dashboard-card">
                    <h3>Resource Usage</h3>
                    <div>
                        <label>Memory Usage:</label>
                        <div class="progress-bar-container">
                            <div id="memory-usage" class="progress-bar normal" style="width: 0%"></div>
                        </div>
                        <div id="memory-usage-text">Loading...</div>
                    </div>
                    <div style="margin-top: 15px;">
                        <label>Disk Usage:</label>
                        <div class="progress-bar-container">
                            <div id="disk-usage" class="progress-bar normal" style="width: 0%"></div>
                        </div>
                        <div id="disk-usage-text">Loading...</div>
                    </div>
                </div>
            </div>

            <!-- Optimization Jobs Section -->
            <div class="dashboard-card">
                <h3>Optimization Jobs</h3>
                <div id="optimization-jobs">
                    <p>Loading optimization jobs...</p>
                </div>
            </div>

            <!-- Bucket Objects Section -->
            <div class="dashboard-card">
                <h3>Recent Objects</h3>
                <div id="bucket-objects">
                    <p>Loading objects...</p>
                </div>
            </div>
        </main>
    </div>

    <!-- Thumbnail Modal -->
    <div id="thumbnail-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal" onclick="closeThumbnailModal()">&times;</span>
            <h2>Thumbnails</h2>
            <div id="thumbnail-container">
                <!-- Thumbnails will be loaded here -->
            </div>
        </div>
    </div>

    <script src="assets/main.js"></script>
</body>
</html>