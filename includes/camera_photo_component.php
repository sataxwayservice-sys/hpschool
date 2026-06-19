<?php
/**
 * Camera Photo Component
 * Reusable component for photo upload with camera capture
 *
 * Usage: include this file where you need photo upload with camera
 *
 * Required parameters (pass before including):
 * - $photoPreviewId (e.g., 'photoPreview')
 * - $photoInputId (e.g., 'photo')
 * - $currentPhotoUrl (e.g., APP_URL . '/uploads/students/photo.jpg')
 */

if (!isset($photoPreviewId)) $photoPreviewId = 'photoPreview';
if (!isset($photoInputId)) $photoInputId = 'photo';
if (!isset($currentPhotoUrl)) $currentPhotoUrl = getStudentPhotoSrc();
?>

<!-- Photo Upload with Camera Component -->
<div class="text-center mb-4">
    <label class="form-label fw-bold">Student Photo</label>
    <div>
        <img src="<?php echo $currentPhotoUrl; ?>"
             class="student-photo mb-3" id="<?php echo $photoPreviewId; ?>" alt="Photo"
             style="width: 200px; height: 200px; object-fit: cover; border-radius: 10px; border: 3px solid #ddd;">
    </div>
    <div class="btn-group mb-2" role="group">
        <button type="button" class="btn btn-primary" onclick="document.getElementById('<?php echo $photoInputId; ?>').click()">
            <i class="bi bi-upload"></i> Upload Photo
        </button>
        <button type="button" class="btn btn-success camera-trigger-btn">
            <i class="bi bi-camera"></i> Take Photo
        </button>
    </div>
    <input type="file" class="form-control d-none photo-input" name="<?php echo $photoInputId; ?>" id="<?php echo $photoInputId; ?>"
           accept="image/jpeg,image/jpg,image/png">
    <input type="hidden" name="photo_from_camera" class="photo-from-camera-flag" value="">
    <small class="d-block text-muted">Max size: 10MB | Formats: JPG, PNG | Or take photo directly</small>
</div>

<!-- Camera Modal -->
<div class="modal fade camera-modal" tabindex="-1" aria-labelledby="cameraModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title">
                    <i class="bi bi-camera-fill"></i> Take Student Photo
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <!-- Camera View -->
                <div class="camera-view-section">
                    <video class="camera-stream" autoplay playsinline style="width: 100%; max-width: 640px; border-radius: 10px; border: 3px solid #ddd;"></video>
                    <div class="mt-3">
                        <button type="button" class="btn btn-success btn-lg camera-capture-btn">
                            <i class="bi bi-camera"></i> Capture Photo
                        </button>
                        <button type="button" class="btn btn-info btn-lg camera-switch-btn" style="display: none;">
                            <i class="bi bi-arrow-repeat"></i> Switch Camera
                        </button>
                    </div>
                    <small class="d-block text-muted mt-2">Position yourself in the frame and click capture</small>
                </div>

                <!-- Captured Photo View -->
                <div class="captured-photo-section" style="display: none;">
                    <canvas class="photo-canvas" style="width: 100%; max-width: 640px; border-radius: 10px; border: 3px solid #ddd;"></canvas>
                    <div class="mt-3">
                        <button type="button" class="btn btn-warning btn-lg camera-retake-btn">
                            <i class="bi bi-arrow-counterclockwise"></i> Retake
                        </button>
                        <button type="button" class="btn btn-primary btn-lg camera-use-btn">
                            <i class="bi bi-check-circle"></i> Use This Photo
                        </button>
                    </div>
                    <small class="d-block text-muted mt-2">Retake if not satisfied, or use this photo</small>
                </div>

                <!-- Camera Error Message -->
                <div class="camera-error-section alert alert-danger" style="display: none;">
                    <i class="bi bi-exclamation-triangle"></i>
                    <strong>Camera Access Denied</strong>
                    <p class="mb-2">Please allow camera access in your browser settings to use this feature.</p>
                    <small class="text-muted">
                        <strong>How to enable:</strong><br>
                        • Click the camera icon in browser address bar<br>
                        • Select "Allow" for camera access<br>
                        • Refresh the page if needed
                    </small>
                </div>

                <!-- Loading State -->
                <div class="camera-loading-section" style="display: none;">
                    <div class="spinner-border text-success" role="status" style="width: 3rem; height: 3rem;">
                        <span class="visually-hidden">Loading camera...</span>
                    </div>
                    <p class="mt-3">Starting camera...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Close
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Camera Photo Component JavaScript
(function() {
    let cameraStream = null;
    let currentFacingMode = 'user'; // 'user' for front camera, 'environment' for back camera
    const photoPreviewId = '<?php echo $photoPreviewId; ?>';
    const photoInputId = '<?php echo $photoInputId; ?>';

    // Get modal elements
    const modalElement = document.querySelector('.camera-modal');
    const cameraModal = new bootstrap.Modal(modalElement);
    const video = modalElement.querySelector('.camera-stream');
    const canvas = modalElement.querySelector('.photo-canvas');

    // Open camera when button clicked
    document.querySelectorAll('.camera-trigger-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            cameraModal.show();
            startCamera();
        });
    });

    // Start camera
    async function startCamera() {
        try {
            // Show loading
            modalElement.querySelector('.camera-loading-section').style.display = 'block';
            modalElement.querySelector('.camera-error-section').style.display = 'none';
            modalElement.querySelector('.camera-view-section').style.display = 'none';
            modalElement.querySelector('.captured-photo-section').style.display = 'none';

            // Request camera access
            const constraints = {
                video: {
                    width: { ideal: 1280 },
                    height: { ideal: 720 },
                    facingMode: currentFacingMode
                },
                audio: false
            };

            cameraStream = await navigator.mediaDevices.getUserMedia(constraints);
            video.srcObject = cameraStream;

            // Wait for video to be ready
            video.onloadedmetadata = function() {
                // Hide loading, show camera view
                modalElement.querySelector('.camera-loading-section').style.display = 'none';
                modalElement.querySelector('.camera-view-section').style.display = 'block';

                // Check if we have multiple cameras
                navigator.mediaDevices.enumerateDevices().then(devices => {
                    const videoDevices = devices.filter(device => device.kind === 'videoinput');
                    if (videoDevices.length > 1) {
                        modalElement.querySelector('.camera-switch-btn').style.display = 'inline-block';
                    }
                });
            };
        } catch (error) {
            console.error('Camera error:', error);
            modalElement.querySelector('.camera-loading-section').style.display = 'none';
            modalElement.querySelector('.camera-view-section').style.display = 'none';
            modalElement.querySelector('.camera-error-section').style.display = 'block';
        }
    }

    // Stop camera
    function stopCamera() {
        if (cameraStream) {
            cameraStream.getTracks().forEach(track => track.stop());
            cameraStream = null;
            video.srcObject = null;
        }
    }

    // Switch camera (front/back)
    modalElement.querySelector('.camera-switch-btn').addEventListener('click', function() {
        stopCamera();
        currentFacingMode = currentFacingMode === 'user' ? 'environment' : 'user';
        startCamera();
    });

    // Capture photo
    modalElement.querySelector('.camera-capture-btn').addEventListener('click', function() {
        const context = canvas.getContext('2d');

        // Set canvas size to match video
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;

        // Draw video frame to canvas
        context.drawImage(video, 0, 0, canvas.width, canvas.height);

        // Hide camera view, show captured photo
        modalElement.querySelector('.camera-view-section').style.display = 'none';
        modalElement.querySelector('.captured-photo-section').style.display = 'block';

        // Stop camera stream
        stopCamera();
    });

    // Retake photo
    modalElement.querySelector('.camera-retake-btn').addEventListener('click', function() {
        modalElement.querySelector('.captured-photo-section').style.display = 'none';
        modalElement.querySelector('.camera-view-section').style.display = 'block';
        startCamera();
    });

    // Use captured photo
    modalElement.querySelector('.camera-use-btn').addEventListener('click', function() {
        // Get image data from canvas
        canvas.toBlob(function(blob) {
            // Convert blob to File object
            const file = new File([blob], 'camera-photo.jpg', { type: 'image/jpeg' });

            // Create a data transfer object to set files
            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(file);

            // Set the file input's files
            document.getElementById(photoInputId).files = dataTransfer.files;

            // Update preview image
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(photoPreviewId).src = e.target.result;
            }
            reader.readAsDataURL(blob);

            // Set flag that photo is from camera
            document.querySelector('.photo-from-camera-flag').value = '1';

            // Close modal
            cameraModal.hide();
        }, 'image/jpeg', 0.9);
    });

    // Stop camera when modal is closed
    modalElement.addEventListener('hidden.bs.modal', function() {
        stopCamera();
        modalElement.querySelector('.camera-view-section').style.display = 'none';
        modalElement.querySelector('.captured-photo-section').style.display = 'none';
        modalElement.querySelector('.camera-error-section').style.display = 'none';
        modalElement.querySelector('.camera-loading-section').style.display = 'none';
    });

    // Handle file input change
    document.getElementById(photoInputId).addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(e) {
                document.getElementById(photoPreviewId).src = e.target.result;
            }
            reader.readAsDataURL(file);
            // Clear camera flag if file is uploaded
            document.querySelector('.photo-from-camera-flag').value = '';
        }
    });
})();
</script>
