<?php
require_once 'db.php';
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

// Fetch car models for dropdown
$models = [];
$res = $conn->query("SELECT id, make, model FROM car_models ORDER BY make, model");
while ($row = $res->fetch_assoc()) {
    $models[] = $row;
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $car_model_id = intval($_POST['car_model_id'] ?? 0);
    $year = intval($_POST['year'] ?? 0);
    $color = trim($_POST['color'] ?? '');
    $mileage = intval($_POST['mileage'] ?? 0);
    $transmission = $_POST['transmission'] ?? '';
    $fuel_type = $_POST['fuel_type'] ?? '';
    $available = isset($_POST['available']) ? 1 : 0;
    $price_mad = isset($_POST['price_mad']) ? floatval($_POST['price_mad']) : 0.00;
    $image_url = '';

    // Ensure cars table has price_mad column
    $colStmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cars' AND COLUMN_NAME = 'price_mad'");
    $colStmt->execute();
    $colStmt->bind_result($col_count);
    $colStmt->fetch();
    $colStmt->close();
    if (intval($col_count) === 0) {
        $conn->query("ALTER TABLE cars ADD COLUMN price_mad DECIMAL(10,2) DEFAULT NULL");
    }

    // Handle file upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "uploads/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $ext = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $filename = uniqid('car_', true) . '.' . $ext;
        $targetFile = $targetDir . $filename;
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetFile)) {
            $image_url = $targetFile;
        } else {
            $message = "Image upload failed.";
        }
    }

    if ($car_model_id && $year && $transmission && $fuel_type) {
        $stmt = $conn->prepare("INSERT INTO cars (car_model_id, year, color, mileage, transmission, fuel_type, price_mad, available, image_url) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        // types: i(car_model_id), i(year), s(color), i(mileage), s(transmission), s(fuel_type), d(price_mad), i(available), s(image_url)
        $stmt->bind_param("iisissdis", $car_model_id, $year, $color, $mileage, $transmission, $fuel_type, $price_mad, $available, $image_url);
        if ($stmt->execute()) {
            header("Location: allcars.php");
            exit;
        } else {
            $message = "Failed to add car.";
        }
        $stmt->close();
    } else {
        $message = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Add Car</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">
    <?php include 'sidebar.php'; ?>
    <main class="flex-1 flex flex-col items-center justify-center p-10">
        <div class="bg-white shadow-lg rounded-lg p-8 w-full max-w-lg">
            <h1 class="text-2xl font-bold mb-6 text-blue-700">Add New Car</h1>
            <?php if ($message): ?>
                <div class="mb-4 text-red-600"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form method="post" class="space-y-4" enctype="multipart/form-data" id="carForm">
                <div>
                    <label class="block mb-1 font-semibold">Make & Model</label>
                    <select name="car_model_id" class="w-full border rounded px-3 py-2" required>
                        <option value="">Select Make & Model</option>
                        <?php foreach ($models as $m): ?>
                            <option value="<?php echo $m['id']; ?>">
                                <?php echo htmlspecialchars($m['make'] . ' ' . $m['model']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Year</label>
                    <input type="number" name="year" class="w-full border rounded px-3 py-2" required>
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Color</label>
                    <input type="text" name="color" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Mileage</label>
                    <input type="number" name="mileage" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Transmission</label>
                    <select name="transmission" class="w-full border rounded px-3 py-2" required>
                        <option value="">Select Transmission</option>
                        <option value="automatic">Automatic</option>
                        <option value="manual">Manual</option>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Fuel Type</label>
                    <select name="fuel_type" class="w-full border rounded px-3 py-2" required>
                        <option value="">Select Fuel Type</option>
                        <option value="petrol">Petrol</option>
                        <option value="diesel">Diesel</option>
                        <option value="electric">Electric</option>
                        <option value="hybrid">Hybrid</option>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Image</label>
                    <input type="file" name="image" accept="image/*" class="w-full border rounded px-3 py-2">
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Price (MAD)</label>
                    <input type="number" step="0.01" min="0" name="price_mad" class="w-full border rounded px-3 py-2" placeholder="0.00">
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="available" id="available" class="mr-2" checked>
                    <label for="available" class="font-semibold">Available</label>
                </div>
                <div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition">Add Car</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
