// admin/assets/signed_urls.js

document.getElementById('signed-url-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const apiKey = document.getElementById('api_key').value;
    const bucket = document.getElementById('bucket').value;
    const file = document.getElementById('file').value;
    const expires = document.getElementById('expires').value;
    const resultDiv = document.getElementById('signed-url-result');

    if (!apiKey || !bucket || !file || !expires) {
        resultDiv.textContent = 'All fields are required.';
        return;
    }

    const formData = new FormData();
    formData.append('bucket', bucket);
    formData.append('file', file);
    formData.append('expires', expires);

    fetch('../api/generate-signed-url', {
        method: 'POST',
        headers: {
            'Authorization': `Bearer ${apiKey}`
        },
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            resultDiv.innerHTML = `Signed URL: <a href="${data.url}" target="_blank">${data.url}</a>`;
        } else {
            resultDiv.textContent = `Error: ${data.message}`;
        }
    })
    .catch(error => {
        resultDiv.textContent = 'An error occurred during URL generation.';
        console.error('Error:', error);
    });
});
