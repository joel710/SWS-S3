// admin/assets/upload.js

document.getElementById('upload-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const apiKey = document.getElementById('api_key').value;
    const bucket = document.getElementById('bucket').value;
    const fileInput = document.getElementById('file');
    const file = fileInput.files[0];
    const statusDiv = document.getElementById('upload-status');

    if (!apiKey || !bucket || !file) {
        statusDiv.textContent = 'All fields are required.';
        return;
    }

    const formData = new FormData();
    formData.append('bucket', bucket);
    formData.append('file', file);

    fetch('../api/upload', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${apiKey}`
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            statusDiv.innerHTML = `File uploaded successfully. URL: <a href="${data.url}" target="_blank">${data.url}</a>`;
        } else {
            statusDiv.textContent = `Error: ${data.message}`;
        }
    })
    .catch(error => {
        statusDiv.textContent = 'An error occurred during upload.';
        console.error('Error:', error);
    });
});
