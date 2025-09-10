<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';
?>

<style>
#preview img {
  border: 2px solid #ccc;
  border-radius: 8px;
  box-shadow: 0 0 5px rgba(0,0,0,0.2);
}
</style>


<div class="container">
  <div class="card mx-auto" style="max-width: 600px;">
    <div class="card-header text-center">
      <h2>Add New Customer</h2>
    </div>
    <?php if (isset($_GET['error'])): ?>
    <div class="alert alert-danger m-3">
        <?php echo htmlspecialchars($_GET['error']); ?>
    </div>
    <?php endif; ?>
    <div class="card-body">
      <form method="POST" action="../actions/insert_customer.php" enctype="multipart/form-data">
      <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="name" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">CNIC</label>
          <input type="text" name="cnic" class="form-control" required>
        </div>
        <div class="mb-3">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="3"></textarea>
        </div>
        <div class="mb-3">
          <label class="form-label">Guarantor 1</label>
          <input type="text" name="guarantor_1" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Guarantor 2</label>
          <input type="text" name="guarantor_2" class="form-control">
        </div>
        <div class="mb-3">
          <label class="form-label">Customer Image</label>
          <input type="file" name="customer_image" class="form-control" accept="image/*" onchange="previewFile()">
          <div class="mt-2">
            <button type="button" class="btn btn-secondary" onclick="startCamera()">📷 Take Photo</button>
            <button type="button" class="btn btn-success mt-2" onclick="capturePhoto()" style="display: none;" id="captureBtn">✅ Capture</button>
          </div>
          
          <!-- Webcam preview -->
          <video id="camera" autoplay playsinline width="300" height="225" style="display: none; margin-top: 10px;"></video>
          <canvas id="snapshot" width="300" height="225" style="display: none; margin-top: 10px;"></canvas>
          
          <!-- Preview for file upload -->
          <div id="preview" style="margin-top: 10px;"></div>
          
          <!-- Hidden input to store image -->
          <input type="hidden" name="camera_image" id="cameraImage">
        </div>
        <button type="submit" class="btn btn-success w-100">Add Customer</button>
      </form>
    </div>
  </div>
</div>

<script>
let video = document.getElementById('camera');
let canvas = document.getElementById('snapshot');
let captureBtn = document.getElementById('captureBtn');

function previewFile() {
  const file = document.querySelector('input[name="customer_image"]').files[0];
  const preview = document.getElementById('preview');
  
  if (file) {
    const reader = new FileReader();
    reader.onload = function(e) {
      preview.innerHTML = '<img src="' + e.target.result + '" class="img-thumbnail" style="max-width: 200px; border: 2px solid #ccc; border-radius: 8px;">';
    };
    reader.readAsDataURL(file);
  }
}

function startCamera() {
  navigator.mediaDevices.getUserMedia({ video: true })
    .then(stream => {
      video.srcObject = stream;
      video.style.display = 'block';
      captureBtn.style.display = 'inline-block';
    })
    .catch(err => {
      alert("Camera access denied or not available.");
    });
}

function capturePhoto() {
  canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
  canvas.style.display = 'block';

  // Convert to base64 and store in hidden input
  let imageData = canvas.toDataURL('image/jpeg');
  document.getElementById('cameraImage').value = imageData;

  // Clear file input since we're using camera
  document.querySelector('input[name="customer_image"]').value = '';
  document.getElementById('preview').innerHTML = '';

  // Stop camera
  video.srcObject.getTracks().forEach(track => track.stop());
  video.style.display = 'none';
  captureBtn.style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>