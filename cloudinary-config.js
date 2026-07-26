// Cloudinary configuration
const cloudName = 'dimhb6x48'; // your Cloudinary cloud name
// The upload preset name is 'CARS' (case-sensitive), as set in your Cloudinary dashboard.
// You do NOT use a link here, just the preset name string.
const unsignedUploadPreset = 'cars'; // your unsigned upload preset name

function uploadToCloudinary(file, onSuccess, onError, onProgress) {
    const url = `https://api.cloudinary.com/v1_1/${cloudName}/upload`;
    const formData = new FormData();
    formData.append('file', file);
    formData.append('upload_preset', unsignedUploadPreset);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', url, true);

    xhr.onload = function () {
        if (xhr.status === 200) {
            const response = JSON.parse(xhr.responseText);
            if (response.secure_url) {
                onSuccess(response.secure_url, response);
            } else {
                onError(response);
            }
        } else {
            try {
                const err = JSON.parse(xhr.responseText);
                alert('Cloudinary error: ' + (err.error && err.error.message ? err.error.message : xhr.responseText));
                onError(err.error && err.error.message ? err.error.message : xhr.responseText);
            } catch {
                alert('Cloudinary error: ' + xhr.responseText);
                onError(xhr.responseText);
            }
        }
    };

    if (onProgress) {
        xhr.upload.onprogress = function (event) {
            if (event.lengthComputable) {
                onProgress(Math.round((event.loaded / event.total) * 100));
            }
        };
    }

    xhr.onerror = function () {
        onError(xhr.responseText);
    };

    xhr.send(formData);
}
