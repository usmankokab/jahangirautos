<?php
include '../config/db.php';
include '../config/auth.php';

$auth->requireLogin();

include '../includes/header.php';

$customer_id = $_GET['id'] ?? null;
if (!$customer_id) {
    echo "<div class='alert alert-danger'>Customer ID is missing.</div>";
    exit;
}

$query = "SELECT * FROM customers WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $customer_id);
$stmt->execute();
$result = $stmt->get_result();
$customer = $result->fetch_assoc();

if (!$customer) {
    echo "<div class='alert alert-warning'>Customer not found.</div>";
    exit;
}
?>

<div class="container">
  <div class="card mx-auto" style="max-width: 600px;">
    <div class="card-header text-center">
      <h2>Edit Customer</h2>
    </div>
    <div class="card-body">
      <form method="POST" action="../actions/update_customer.php" enctype="multipart/form-data">
        <input type="hidden" name="id" value="<?= $customer['id'] ?>">

        <div class="mb-3 text-center">
          <img src="../<?= $customer['image_path'] ?? 'assets/default.png' ?>" class="img-thumbnail" style="max-width: 150px;">
        </div>

        <div class="mb-3">
          <label class="form-label">Full Name</label>
          <input type="text" name="name" class="form-control" value="<?= $customer['name'] ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">CNIC</label>
          <input type="text" name="cnic" class="form-control" value="<?= $customer['cnic'] ?>" required>
        </div>

        <div class="mb-3">
          <label class="form-label">Phone</label>
          <input type="text" name="phone" class="form-control" value="<?= $customer['phone'] ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="3"><?= $customer['address'] ?></textarea>
        </div>

        <div class="mb-3">
          <label class="form-label">Guarantor 1</label>
          <input type="text" name="guarantor_1" class="form-control" value = "<?= $customer['guarantor_1']?>" >
        </div>
        <div class="mb-3">
          <label class="form-label">Guarantor 2</label>
          <input type="text" name="guarantor_2" class="form-control" value = "<?= $customer['guarantor_2']?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Update Customer Image</label>
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
          
          <!-- Hidden input to store base64 image -->
          <input type="hidden" name="camera_image" id="cameraImage">
        </div>

        
        <button type="submit" class="btn btn-primary w-100">Update Customer</button>
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

  let imageData = canvas.toDataURL('image/jpeg');
  document.getElementById('cameraImage').value = imageData;

  // Clear file input since we're using camera
  document.querySelector('input[name="customer_image"]').value = '';
  document.getElementById('preview').innerHTML = '';

  video.srcObject.getTracks().forEach(track => track.stop());
  video.style.display = 'none';
  captureBtn.style.display = 'none';
}
</script>

<?php include '../includes/footer.php'; ?>