<?php
require_once __DIR__ . '/session.php';
require_once 'db.php';
require_once 'db_helpers.php';

if (!isset($_SESSION['VendorID'])) {
    header("Location: Login.html");
    exit();
}

$vendorId = (int)$_SESSION['VendorID'];

$vendor = db_fetch_one($pdo,
    'SELECT "ShopName", "OpenHours", "Location", "FoodType", "Description", "Image", "Latitude", "Longitude" FROM vendor WHERE "VendorID" = ?',
    [$vendorId]
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shopName = trim($_POST['ShopName'] ?? '');
    $openTime = trim($_POST['OpenTime'] ?? '');
    $closeTime = trim($_POST['CloseTime'] ?? '');
    
    if ($openTime !== '' && $closeTime !== '') {
        $openHours = date("h:i A", strtotime($openTime)) . " - " . date("h:i A", strtotime($closeTime));
    } else {
        $openHours = '';
    }
    $location = trim($_POST['Location'] ?? '');
    $foodType = trim($_POST['FoodType'] ?? '');
    $description = trim($_POST['Description'] ?? '');

    // Handle image upload
    $image = $vendor['Image'] ?? ''; // Keep existing image by default

    // Check if vendor wants to remove the current image
    if (isset($_POST['RemoveImage']) && $_POST['RemoveImage'] === '1') {
        // Delete old file if it exists
        if (!empty($vendor['Image']) && file_exists($vendor['Image'])) {
            unlink($vendor['Image']);
        }
        $image = '';
    }

    // Handle new file upload
    if (isset($_FILES['ImageFile']) && $_FILES['ImageFile']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/vendor/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $fileType = $_FILES['ImageFile']['type'];
        $fileSize = $_FILES['ImageFile']['size'];
        $maxSize = 5 * 1024 * 1024; // 5MB

        if (!in_array($fileType, $allowedTypes)) {
            header("Location: VendorProfileEdit.php?type=error&msg=" . urlencode("Invalid image type. Only JPG, PNG, GIF, and WEBP are allowed."));
            exit();
        }

        if ($fileSize > $maxSize) {
            header("Location: VendorProfileEdit.php?type=error&msg=" . urlencode("Image file is too large. Maximum size is 5MB."));
            exit();
        }

        // Generate unique filename
        $ext = pathinfo($_FILES['ImageFile']['name'], PATHINFO_EXTENSION);
        $newFileName = 'vendor_' . $vendorId . '_' . time() . '.' . $ext;
        $targetPath = $uploadDir . $newFileName;

        if (move_uploaded_file($_FILES['ImageFile']['tmp_name'], $targetPath)) {
            // Delete old file if it exists
            if (!empty($vendor['Image']) && file_exists($vendor['Image']) && $vendor['Image'] !== $targetPath) {
                unlink($vendor['Image']);
            }
            $image = $targetPath;
        } else {
            header("Location: VendorProfileEdit.php?type=error&msg=" . urlencode("Failed to upload image."));
            exit();
        }
    }
    $latitude = trim($_POST['Latitude'] ?? '');
    $longitude = trim($_POST['Longitude'] ?? '');

    if ($shopName === '' || $latitude === '' || $longitude === '') {
        header("Location: VendorProfileEdit.php?type=error&msg=" . urlencode("Shop name and Map Location are required."));
        exit();
    }

    $rows = db_execute($pdo,
        'UPDATE vendor SET "ShopName" = ?, "OpenHours" = ?, "Location" = ?, "FoodType" = ?, "Description" = ?, "Image" = ?, "Latitude" = ?, "Longitude" = ?, "LastUpdate" = NOW() WHERE "VendorID" = ?',
        [$shopName, $openHours, $location, $foodType, $description, $image, $latitude, $longitude, $vendorId]
    );

    if ($rows > 0) {
        $_SESSION['ShopName'] = $shopName;
        db_execute($pdo,
            'UPDATE admin_notifications SET "IsRead" = TRUE WHERE "VendorID" = ? AND "IsRead" = FALSE',
            [$vendorId]
        );
        header("Location: VendorDashboard.php?type=success&msg=" . urlencode("Profile updated successfully."));
        exit();
    } else {
        header("Location: VendorProfileEdit.php?type=error&msg=" . urlencode("Failed to update profile."));
        exit();
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        .premium-form-card {
            background: #fff;
            border: 1px solid #f0f0f0;
            border-radius: 16px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.04);
            padding: 32px;
            max-width: 650px;
            margin: 0 auto;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 700; color: #333; margin-bottom: 8px; font-size: 0.9rem; }
        .modern-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #e8e8e8;
            border-radius: 10px;
            font-size: 0.95rem;
            color: #333;
            background: #fdfdfd;
            transition: border-color 0.2s, background 0.2s;
            box-sizing: border-box;
        }
        .modern-input:focus {
            outline: none;
            border-color: #ff2a44;
            background: #fff;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ff2a44;
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s, transform 0.1s;
            width: 100%;
            margin-top: 10px;
        }
        .btn-primary:hover { background: #cc001b; transform: translateY(-2px); }
        .btn-return { display: block; text-align: center; margin-top: 20px; color: #666; font-weight: 600; text-decoration: none; transition: color 0.2s; }
        .btn-return:hover { color: #333; }
        
        .image-preview-box { margin-top:10px; max-width:220px; border-radius:12px; overflow:hidden; border:2px dashed #e8e8e8; }
        .image-preview-box img { width:100%; display:block; }
        .file-upload-label { display:inline-block; padding:16px 20px; background:#fff; border:2px dashed #e8e8e8; border-radius:12px; cursor:pointer; text-align:center; width:100%; box-sizing:border-box; transition:border-color 0.2s,background 0.2s; }
        .file-upload-label:hover { border-color:#ff2a44; background:#fff0f2; }
        .file-upload-label .upload-icon { font-size:1.8rem; display:block; margin-bottom:8px; }
        .file-upload-label .upload-text { font-size:0.95rem; color:#444; font-weight:600; }
        .file-upload-label .upload-subtext { font-size:0.8rem; color:#888; }
        .file-input-hidden { display:none; }
    </style>
    <title>Edit Profile - Vendor - CraveFood</title>
    <link rel="stylesheet" href="../style.css?v=<?= time() ?>">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
</head>
<body>
    <?php include('vendor_header.php'); ?>

    <?php
        $noticeMsg = isset($_GET['msg']) ? urldecode($_GET['msg']) : '';
        $noticeType = isset($_GET['type']) ? $_GET['type'] : 'error';
    ?>
    <?php if ($noticeMsg !== ''): ?>
        <div class="notice show <?php echo ($noticeType === 'success') ? 'notice-success' : 'notice-error'; ?>">
            <?php echo htmlspecialchars($noticeMsg); ?>
        </div>
    <?php endif; ?>

    <div style="padding: 40px 20px;">
        <div style="text-align:center; margin-bottom:30px;">
            <h1 class="hero-title" style="font-size:2.2rem; font-weight:800; color:#1a1a1a; margin:0 0 8px; letter-spacing:-0.5px;">Edit Store Profile</h1>
            <p style="color:#666; font-size:1rem; margin:0;">Update your shop details, operating hours, and location map.</p>
        </div>

        <div class="premium-form-card">
        <form action="VendorProfileEdit.php" method="POST" enctype="multipart/form-data">
            <div class="form-group">
                <label>Shop Name</label>
                <input type="text" class="modern-input"  name="ShopName" value="<?php echo htmlspecialchars((string)($vendor['ShopName'] ?? '')); ?>" required>
            </div>
            <div class="form-group">
                <label>Location (Address)</label>
                <input type="text" class="modern-input"  name="Location" value="<?php echo htmlspecialchars((string)($vendor['Location'] ?? '')); ?>">
            </div>
            <div class="form-group">
                <label>Operating Hours</label>
                <?php
                $currentOpenTime = '';
                $currentCloseTime = '';
                if (!empty($vendor['OpenHours'])) {
                    $parts = explode(' - ', $vendor['OpenHours']);
                    if (count($parts) === 2) {
                        $currentOpenTime = date("H:i", strtotime($parts[0]));
                        $currentCloseTime = date("H:i", strtotime($parts[1]));
                    }
                }
                ?>
                <div style="display: flex; gap: 10px; align-items: center;">
                    <input type="time" class="modern-input"  name="OpenTime" value="<?php echo htmlspecialchars($currentOpenTime); ?>" style="flex: 1;" required>
                    <span style="font-weight: 600; color: #555;">to</span>
                    <input type="time" class="modern-input"  name="CloseTime" value="<?php echo htmlspecialchars($currentCloseTime); ?>" style="flex: 1;" required>
                </div>
            </div>
            <div class="form-group">
                <label>Main Cuisine Category</label>
                <input type="text" class="modern-input"  name="FoodType" value="<?php echo htmlspecialchars((string)($vendor['FoodType'] ?? '')); ?>" placeholder="e.g. Malay, Chinese, Western">
            </div>
            <div class="form-group">
                <label>Store Image</label>
                <?php if (!empty($vendor['Image'])): ?>
                    <div style="margin-bottom: 10px; position: relative; display: inline-block;">
                        <img src="<?php echo htmlspecialchars($vendor['Image']); ?>" alt="Current Store Image" style="width: 200px; height: 140px; object-fit: cover; border-radius: var(--border-radius-md); border: 2px solid var(--gray-light);">
                        <label style="display: flex; align-items: center; gap: 6px; margin-top: 6px; cursor: pointer; color: #dc3545; font-size: 0.9rem;">
                            <input type="checkbox" name="RemoveImage" value="1" id="removeImageCheck" onchange="toggleFileInput(this)"> Remove current image
                        </label>
                    </div>
                <?php endif; ?>
                <label for="imageFileInput" class="file-drop-zone" style="margin-top:10px;">
                    <span class="file-drop-icon">&#128247;</span>
                    <span class="file-drop-text">Click to upload a new store image</span>
                    <span class="file-drop-subtext">Accepted: JPG, PNG, GIF, WEBP. Max size: 5MB</span>
                    <input type="file" name="ImageFile" id="imageFileInput" accept="image/jpeg,image/png,image/gif,image/webp" onchange="previewImage(this)">
                </label>
                <div class="image-preview-box" id="imagePreview" style="display:none;">
                    <img id="previewImg" src="#" alt="Preview">
                </div>
            </div>

            <div class="form-group">
                <label>Pin your Location on the Map</label>
                <div id="vendorMap" style="height: 300px; width: 100%; border-radius: 12px; border: 2px solid #e8e8e8; margin-bottom: 15px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); z-index: 1;"></div>
                <div style="display: flex; gap: 10px;">
                    <input type="text" class="modern-input" id="Latitude" name="Latitude" value="<?php echo htmlspecialchars((string)($vendor['Latitude'] ?? '')); ?>" placeholder="Latitude" readonly style="background: #f5f5f5; color: #888; cursor: not-allowed;">
                    <input type="text" class="modern-input" id="Longitude" name="Longitude" value="<?php echo htmlspecialchars((string)($vendor['Longitude'] ?? '')); ?>" placeholder="Longitude" readonly style="background: #f5f5f5; color: #888; cursor: not-allowed;">
                </div>
                <small style="display:block; margin-top:8px; color:#888; font-size:0.85rem;">Click anywhere on the map to pin your exact location. You can drag the pin to adjust it.</small>
            </div>

            <button type="submit" class="btn-primary" style="margin-top: 10px;">Save Profile Updates</button>
        </form>
        </div>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    <script>
    /* â”€â”€ Navbar active link â”€â”€ */
    var page = window.location.pathname.split('/').pop().toLowerCase() || 'homepage.php';
    if (page === '' || page === 'index.php') page = 'homepage.php';
    document.querySelectorAll('.nav-links a').forEach(function(link) {
        var href = (link.getAttribute('href') || '').toLowerCase();
        if (href === page || (page.startsWith('vendorprofileedit') && href === 'vendordashboard.php')) {
            link.classList.add('active');
        }
    });

    /* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
       VENDOR MAP &mdash; click to pin location
    â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
    var savedLat = parseFloat(document.getElementById('Latitude').value);
    var savedLng = parseFloat(document.getElementById('Longitude').value);

    var hasExisting = !isNaN(savedLat) && !isNaN(savedLng) && savedLat !== 0 && savedLng !== 0;
    var defaultLat = hasExisting ? savedLat : 3.0738;
    var defaultLng = hasExisting ? savedLng : 101.5183;
    var defaultZoom = hasExisting ? 16 : 13;

    var map = L.map('vendorMap', {
        zoomControl: true
    }).setView([defaultLat, defaultLng], defaultZoom);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
        maxZoom: 19
    }).addTo(map);

    /* Force Leaflet to recalculate size after layout settles */
    setTimeout(function() { map.invalidateSize(); }, 200);
    setTimeout(function() { map.invalidateSize(); }, 600);

    /* â”€â”€ Custom pin icon â”€â”€ */
    var pinIcon = L.divIcon({
        className: '',
        html: '<div style="width:28px;height:28px;background:#c1121f;border:3px solid #fff;border-radius:50%;box-shadow:0 2px 10px rgba(193,18,31,0.4);"></div>',
        iconSize: [28, 28],
        iconAnchor: [14, 14]
    });

    /* â”€â”€ Place existing marker if lat/lng are saved â”€â”€ */
    var marker = null;

    if (hasExisting) {
        marker = L.marker([savedLat, savedLng], { icon: pinIcon, draggable: true }).addTo(map);
        marker.bindPopup('<strong style="font-family:Inter,sans-serif;font-size:0.85rem;">&#128205; Your store</strong>').openPopup();

        /* Allow dragging to reposition */
        marker.on('dragend', function(e) {
            var pos = e.target.getLatLng();
            document.getElementById('Latitude').value = pos.lat.toFixed(8);
            document.getElementById('Longitude').value = pos.lng.toFixed(8);
        });
    }

    /* â”€â”€ Click on map to place / move pin â”€â”€ */
    map.on('click', function(e) {
        var lat = e.latlng.lat;
        var lng = e.latlng.lng;

        document.getElementById('Latitude').value = lat.toFixed(8);
        document.getElementById('Longitude').value = lng.toFixed(8);

        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], { icon: pinIcon, draggable: true }).addTo(map);
            marker.bindPopup('<strong style="font-family:Inter,sans-serif;font-size:0.85rem;">&#128205; Your store</strong>');

            marker.on('dragend', function(ev) {
                var pos = ev.target.getLatLng();
                document.getElementById('Latitude').value = pos.lat.toFixed(8);
                document.getElementById('Longitude').value = pos.lng.toFixed(8);
            });
        }

        marker.openPopup();
    });

    /* If no saved location, try to center on user's browser geolocation */
    if (!hasExisting && navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(function(pos) {
            map.setView([pos.coords.latitude, pos.coords.longitude], 15);
        }, function() {}, { enableHighAccuracy: true, timeout: 6000 });
    }

});

/* â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•
   IMAGE PREVIEW / REMOVE helpers
â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â•â• */
function previewImage(input) {
    var preview = document.getElementById('imagePreview');
    var img = document.getElementById('previewImg');
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            preview.style.display = 'block';
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.style.display = 'none';
    }
}

function toggleFileInput(checkbox) {
    var fileInput = document.getElementById('imageFileInput');
    if (checkbox.checked) {
        fileInput.disabled = true;
        fileInput.value = '';
        document.getElementById('imagePreview').style.display = 'none';
    } else {
        fileInput.disabled = false;
    }
}
</script>
</body>
</html>



