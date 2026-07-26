<?php
require_once 'db.php';
session_start();
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit;
}

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: allcars.php');
    exit;
}

// Fetch car models for dropdown
$models = [];
$res = $conn->query("SELECT id, make, model FROM car_models ORDER BY make, model");
while ($row = $res->fetch_assoc()) {
    $models[] = $row;
}

// Fetch car data
$stmt = $conn->prepare("SELECT * FROM cars WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
$car = $result->fetch_assoc();
$stmt->close();

if (!$car) {
    header('Location: allcars.php');
    exit;
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
    $price_mad = isset($_POST['price_mad']) ? floatval($_POST['price_mad']) : floatval($car['price_mad'] ?? 0.00);
    $image_url = $car['image_url'];

    // Ensure cars table has price_mad column
    $colStmt = $conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'cars' AND COLUMN_NAME = 'price_mad'");
    $colStmt->execute();
    $colStmt->bind_result($col_count);
    $colStmt->fetch();
    $colStmt->close();
    if (intval($col_count) === 0) {
        $conn->query("ALTER TABLE cars ADD COLUMN price_mad DECIMAL(10,2) DEFAULT NULL");
    }

    // Handle file upload if a new image is provided
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
        $stmt = $conn->prepare("UPDATE cars SET car_model_id=?, year=?, color=?, mileage=?, transmission=?, fuel_type=?, price_mad=?, available=?, image_url=? WHERE id=?");
        // types: i(car_model_id), i(year), s(color), i(mileage), s(transmission), s(fuel_type), d(price_mad), i(available), s(image_url), i(id)
        $stmt->bind_param("iisissdisi", $car_model_id, $year, $color, $mileage, $transmission, $fuel_type, $price_mad, $available, $image_url, $id);
        if ($stmt->execute()) {
            header("Location: allcars.php");
            exit;
        } else {
            $message = "Failed to update car.";
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
    <title>Edit Car</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex">
    <?php include 'sidebar.php'; ?>
    <main class="flex-1 flex flex-col items-center justify-center p-10">
        <div class="bg-white shadow-lg rounded-lg p-8 w-full max-w-lg">
            <h1 class="text-2xl font-bold mb-6 text-blue-700">Edit Car</h1>
            <?php if ($message): ?>
                <div class="mb-4 text-red-600"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            <form method="post" class="space-y-4" enctype="multipart/form-data" id="carForm">
                <div>
                    <label class="block mb-1 font-semibold">Make & Model</label>
                    <select name="car_model_id" class="w-full border rounded px-3 py-2" required>
                        <option value="">Select Make & Model</option>
                        <?php foreach ($models as $m): ?>
                            <option value="<?php echo $m['id']; ?>" <?php if ($car['car_model_id'] == $m['id']) echo 'selected'; ?>>
                                <?php echo htmlspecialchars($m['make'] . ' ' . $m['model']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Year</label>
                    <input type="number" name="year" class="w-full border rounded px-3 py-2" required value="<?php echo htmlspecialchars($car['year']); ?>">
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Color</label>
                    <input type="text" name="color" class="w-full border rounded px-3 py-2" value="<?php echo htmlspecialchars($car['color']); ?>">
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Mileage</label>
                    <input type="number" name="mileage" class="w-full border rounded px-3 py-2" value="<?php echo htmlspecialchars($car['mileage']); ?>">
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Transmission</label>
                    <select name="transmission" class="w-full border rounded px-3 py-2" required>
                        <option value="">Select Transmission</option>
                        <option value="automatic" <?php if ($car['transmission'] == 'automatic') echo 'selected'; ?>>Automatic</option>
                        <option value="manual" <?php if ($car['transmission'] == 'manual') echo 'selected'; ?>>Manual</option>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Fuel Type</label>
                    <select name="fuel_type" class="w-full border rounded px-3 py-2" required>
                        <option value="">Select Fuel Type</option>
                        <option value="petrol" <?php if ($car['fuel_type'] == 'petrol') echo 'selected'; ?>>Petrol</option>
                        <option value="diesel" <?php if ($car['fuel_type'] == 'diesel') echo 'selected'; ?>>Diesel</option>
                        <option value="electric" <?php if ($car['fuel_type'] == 'electric') echo 'selected'; ?>>Electric</option>
                        <option value="hybrid" <?php if ($car['fuel_type'] == 'hybrid') echo 'selected'; ?>>Hybrid</option>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Image</label>
                    <input type="file" name="image" accept="image/*" class="w-full border rounded px-3 py-2">
                    <div id="imagePreview" class="mt-2">
                        <?php if (!empty($car['image_url'])): ?>
                            <img src="<?php echo htmlspecialchars($car['image_url']); ?>" class="w-32 h-20 object-cover rounded shadow">
                        <?php endif; ?>
                    </div>
                </div>
                <div>
                    <label class="block mb-1 font-semibold">Price (MAD)</label>
                    <input type="number" step="0.01" min="0" name="price_mad" class="w-full border rounded px-3 py-2" value="<?php echo htmlspecialchars($car['price_mad'] ?? ''); ?>">
                </div>
                <div class="flex items-center">
                    <input type="checkbox" name="available" id="available" class="mr-2" <?php if ($car['available']) echo 'checked'; ?>>
                    <label for="available" class="font-semibold">Available</label>
                </div>
                <div>
                    <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 transition">Update Car</button>
                </div>
            </form>
        </div>
    </main>
</body>
</html>
