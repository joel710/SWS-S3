// admin/assets/main.js

document.addEventListener('DOMContentLoaded', function() {
    loadProjects();

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
