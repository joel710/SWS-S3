// admin/assets/main.js

document.addEventListener('DOMContentLoaded', function() {
    loadProjects();
    loadSystemStats();
    loadOptimizationJobs();

    // Initialize auto-refresh for real-time data
    setInterval(() => {
        loadSystemStats();
        loadOptimizationJobs();
    }, 30000); // Refresh every 30 seconds

    // Example of how to add a new project
    const addProjectForm = document.getElementById('add-project-form');
    if (addProjectForm) {
        addProjectForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('action', 'add_project');

            fetch('api.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                console.log(data);
                loadProjects(); // Reload projects after adding
            });
        });
    }
});

function loadProjects() {
    fetch('api.php?action=get_projects')
        .then(response => response.json())
        .then(data => {
            const projectsList = document.getElementById('projects-list');
            if (projectsList) {
                projectsList.innerHTML = ''; // Clear current list
                data.forEach(project => {
                    const projectCard = `
                        <div class="project-card">
                            <h3>${project.name}</h3>
                            <p>API Key: <code>${project.api_key}</code></p>
                            <p>Buckets: ${project.bucket_count}</p>
                            <button onclick="deleteProject(${project.id})">Delete</button>
                            <div id="buckets-for-project-${project.id}"></div>
                            <form onsubmit="addBucket(event, ${project.id})">
                                <input type="text" name="name" placeholder="Bucket Name" required>
                                <label><input type="checkbox" name="is_public"> Public</label>
                                <button type="submit">Add Bucket</button>
                            </form>
                        </div>
                    `;
                    projectsList.innerHTML += projectCard;
                    loadBuckets(project.id);
                });
            }
        });
}

function deleteProject(id) {
    if (!confirm('Are you sure you want to delete this project?')) {
        return;
    }

    const formData = new FormData();
    formData.append('action', 'delete_project');
    formData.append('id', id);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log(data);
        loadProjects();
    });
}

function loadBuckets(projectId) {
    fetch(`api.php?action=get_buckets&project_id=${projectId}`)
        .then(response => response.json())
        .then(data => {
            const bucketsList = document.getElementById(`buckets-for-project-${projectId}`);
            if (bucketsList) {
                bucketsList.innerHTML = '<h4>Buckets:</h4>';
                data.forEach(bucket => {
                    bucketsList.innerHTML += `<p>${bucket.name} (${bucket.is_public ? 'Public' : 'Private'})</p>`;
                });
            }
        });
}

function addBucket(event, projectId) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'add_bucket');
    formData.append('project_id', projectId);

    fetch('api.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        console.log(data);
        loadProjects();
    });
}

// Optimization and Monitoring Functions

/**
 * Load system statistics and display them in the dashboard
 */
function loadSystemStats() {
    fetch('../api/health')
        .then(response => response.json())
        .then(data => {
            if (data.success && data.data) {
                displaySystemStats(data.data);
            }
        })
        .catch(error => {
            console.error('Failed to load system stats:', error);
        });
}

/**
 * Display system statistics in the dashboard
 */
function displaySystemStats(stats) {
    // Update status indicator
    const statusElement = document.getElementById('system-status');
    if (statusElement) {
        statusElement.textContent = stats.status.toUpperCase();
        statusElement.className = `status-badge status-${stats.status}`;
    }

    // Update service status
    updateServiceStatus(stats.services);

    // Update metrics
    if (stats.metrics) {
        updateMetrics(stats.metrics);
    }

    // Update uptime if available
    if (stats.uptime) {
        updateUptime(stats.uptime);
    }
}

/**
 * Update individual service status indicators
 */
function updateServiceStatus(services) {
    Object.keys(services).forEach(service => {
        const element = document.getElementById(`status-${service}`);
        if (element) {
            const status = services[service];
            element.className = `service-status ${status.healthy ? 'healthy' : 'unhealthy'}`;
            element.innerHTML = status.healthy ?
                '<span class="status-indicator green"></span>Healthy' :
                '<span class="status-indicator red"></span>' + (status.error || 'Unhealthy');
        }
    });
}

/**
 * Update system metrics display
 */
function updateMetrics(metrics) {
    if (metrics.memory_usage && metrics.memory_limit) {
        const memoryPercent = ((metrics.memory_usage / metrics.memory_limit) * 100).toFixed(1);
        updateProgressBar('memory-usage', memoryPercent, formatBytes(metrics.memory_usage));
    }

    if (metrics.disk_usage) {
        const diskPercent = metrics.disk_usage.usage_percent;
        updateProgressBar('disk-usage', diskPercent, `${diskPercent}% used`);
    }

    if (metrics.php_version) {
        const phpVersionElement = document.getElementById('php-version');
        if (phpVersionElement) {
            phpVersionElement.textContent = metrics.php_version;
        }
    }
}

/**
 * Update uptime display
 */
function updateUptime(uptime) {
    const uptimeElement = document.getElementById('system-uptime');
    if (uptimeElement && uptime.load_1min) {
        uptimeElement.textContent = `Load: ${uptime.load_1min.toFixed(2)}`;
    }
}

/**
 * Update progress bar
 */
function updateProgressBar(id, percent, text) {
    const barElement = document.getElementById(id);
    const textElement = document.getElementById(id + '-text');

    if (barElement) {
        barElement.style.width = `${Math.min(100, Math.max(0, percent))}%`;
        barElement.className = `progress-bar ${percent > 80 ? 'warning' : 'normal'}`;
    }

    if (textElement) {
        textElement.textContent = text;
    }
}

/**
 * Load optimization jobs status
 */
function loadOptimizationJobs() {
    fetch('api.php?action=get_optimization_jobs')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayOptimizationJobs(data.jobs);
            }
        })
        .catch(error => {
            console.error('Failed to load optimization jobs:', error);
        });
}

/**
 * Display optimization jobs in the admin interface
 */
function displayOptimizationJobs(jobs) {
    const jobsContainer = document.getElementById('optimization-jobs');
    if (!jobsContainer) return;

    if (jobs.length === 0) {
        jobsContainer.innerHTML = '<p>No optimization jobs in queue</p>';
        return;
    }

    let html = '<h3>Optimization Jobs</h3><div class="jobs-list">';

    jobs.forEach(job => {
        const statusClass = getJobStatusClass(job.status);
        const progressClass = job.status === 'processing' ? 'progress-active' : '';

        html += `
            <div class="job-item ${statusClass}">
                <div class="job-header">
                    <span class="job-type">${job.job_type.replace('_', ' ').toUpperCase()}</span>
                    <span class="job-status ${statusClass}">${job.status.toUpperCase()}</span>
                </div>
                <div class="job-progress">
                    <div class="progress-bar-container">
                        <div class="progress-bar ${progressClass}" style="width: ${job.progress}%"></div>
                    </div>
                    <span class="progress-text">${job.progress}%</span>
                </div>
                <div class="job-details">
                    <small>Object ID: ${job.object_id} | Priority: ${job.priority}</small>
                    ${job.error_message ? `<div class="error-message">${job.error_message}</div>` : ''}
                </div>
            </div>
        `;
    });

    html += '</div>';
    jobsContainer.innerHTML = html;
}

/**
 * Get CSS class for job status
 */
function getJobStatusClass(status) {
    const statusClasses = {
        'pending': 'status-pending',
        'processing': 'status-processing',
        'completed': 'status-completed',
        'failed': 'status-failed'
    };
    return statusClasses[status] || 'status-unknown';
}

/**
 * Format bytes to human readable format
 */
function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';

    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));

    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

/**
 * Load objects for a specific bucket with optimization status
 */
function loadBucketObjects(projectId, bucketId) {
    fetch(`api.php?action=get_objects&project_id=${projectId}&bucket_id=${bucketId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                displayBucketObjects(data.objects);
            }
        })
        .catch(error => {
            console.error('Failed to load bucket objects:', error);
        });
}

/**
 * Display objects with optimization controls
 */
function displayBucketObjects(objects) {
    const objectsContainer = document.getElementById('bucket-objects');
    if (!objectsContainer) return;

    if (objects.length === 0) {
        objectsContainer.innerHTML = '<p>No objects in this bucket</p>';
        return;
    }

    let html = '<div class="objects-grid">';

    objects.forEach(obj => {
        const isImage = obj.mime_type.startsWith('image/');
        const hasThumbnails = obj.thumbnails_available;

        html += `
            <div class="object-card">
                <div class="object-header">
                    <h4>${obj.filename}</h4>
                    <span class="file-type">${obj.mime_type}</span>
                </div>
                <div class="object-info">
                    <p>Size: ${formatBytes(obj.size)}</p>
                    <p>Created: ${new Date(obj.created_at).toLocaleString()}</p>
                    ${obj.optimized_at ? `<p>Optimized: ${new Date(obj.optimized_at).toLocaleString()}</p>` : '<p class="warning">Not optimized</p>'}
                    ${hasThumbnails ? '<p class="success">✓ Thumbnails available</p>' : '<p class="warning">✗ No thumbnails</p>'}
                </div>
                ${isImage ? `
                    <div class="object-actions">
                        <button onclick="optimizeObject(${obj.id})" class="btn-optimize">Optimize</button>
                        <button onclick="viewThumbnails(${obj.id})" class="btn-view">View Thumbnails</button>
                        <button onclick="downloadObject(${obj.id})" class="btn-download">Download</button>
                        <button onclick="deleteObject(${obj.id})" class="btn-delete">Delete</button>
                    </div>
                ` : `
                    <div class="object-actions">
                        <button onclick="downloadObject(${obj.id})" class="btn-download">Download</button>
                        <button onclick="deleteObject(${obj.id})" class="btn-delete">Delete</button>
                    </div>
                `}
            </div>
        `;
    });

    html += '</div>';
    objectsContainer.innerHTML = html;
}

/**
 * Optimize an object
 */
function optimizeObject(objectId) {
    if (!confirm('Start optimization for this object? This may take a few moments.')) {
        return;
    }

    fetch(`../api/optimize`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Authorization': 'Bearer ' + getAdminApiKey()
        },
        body: JSON.stringify({
            object_id: objectId,
            optimize_original: true,
            generate_thumbnails: true,
            convert_webp: true
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Optimization started successfully!');
            loadOptimizationJobs(); // Refresh jobs list
        } else {
            alert('Optimization failed: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Optimization error:', error);
        alert('Optimization failed due to network error');
    });
}

/**
 * View thumbnails for an object
 */
function viewThumbnails(objectId) {
    const modal = document.getElementById('thumbnail-modal');
    const container = document.getElementById('thumbnail-container');

    if (!modal || !container) return;

    // Generate thumbnail URLs
    const sizes = ['xs', 'sm', 'md', 'lg'];
    let html = '<div class="thumbnail-grid">';

    sizes.forEach(size => {
        html += `
            <div class="thumbnail-item">
                <h5>${size.toUpperCase()} (${getThumbnailSize(size)}px)</h5>
                <img src="../api/thumbnails/${objectId}/${size}.jpg"
                     alt="${size} thumbnail"
                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwIiBoZWlnaHQ9IjEwMCIgZmlsbD0iI2VlZSIvPjx0ZXh0IHg9IjUwIiB5PSI1MCIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEyIiBmaWxsPSIjOTk5IiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBkeT0iLjNlbSI+Tm8gVGh1bWJuYWlsPC90ZXh0Pjwvc3ZnPg==';">
                <div class="thumbnail-actions">
                    <a href="../api/thumbnails/${objectId}/${size}.jpg" target="_blank" class="btn-small">JPG</a>
                    <a href="../api/thumbnails/${objectId}/${size}.webp" target="_blank" class="btn-small">WebP</a>
                </div>
            </div>
        `;
    });

    html += '</div>';
    container.innerHTML = html;
    modal.style.display = 'block';
}

/**
 * Get thumbnail size in pixels
 */
function getThumbnailSize(size) {
    const sizes = {
        'xs': 100,
        'sm': 300,
        'md': 600,
        'lg': 1200
    };
    return sizes[size] || 'Unknown';
}

/**
 * Download an object
 */
function downloadObject(objectId) {
    window.open(`api.php?action=download_object&object_id=${objectId}`, '_blank');
}

/**
 * Delete an object
 */
function deleteObject(objectId) {
    if (!confirm('Are you sure you want to delete this object? This action cannot be undone.')) {
        return;
    }

    fetch(`api.php?action=delete_object&object_id=${objectId}`, {
        method: 'POST'
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Object deleted successfully');
            // Reload the current view
            location.reload();
        } else {
            alert('Delete failed: ' + data.error);
        }
    })
    .catch(error => {
        console.error('Delete error:', error);
        alert('Delete failed due to network error');
    });
}

/**
 * Get admin API key (should be stored securely)
 */
function getAdminApiKey() {
    // In a real implementation, this should come from a secure source
    return localStorage.getItem('admin_api_key') || '';
}

/**
 * Close thumbnail modal
 */
function closeThumbnailModal() {
    const modal = document.getElementById('thumbnail-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}
