<?php
session_start();
require_once 'db.php';

// Get filter parameters
$make = isset($_GET['make']) ? $_GET['make'] : '';
$min_year = isset($_GET['min_year']) ? intval($_GET['min_year']) : 0;
$max_year = isset($_GET['max_year']) ? intval($_GET['max_year']) : 9999;
$transmission = isset($_GET['transmission']) ? $_GET['transmission'] : '';
$fuel_type = isset($_GET['fuel_type']) ? $_GET['fuel_type'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the query
$query = "SELECT c.*, cm.make, cm.model 
          FROM cars c 
          JOIN car_models cm ON c.car_model_id = cm.id 
          WHERE 1=1";
$params = [];
$types = "";

if ($make) {
    $query .= " AND cm.make = ?";
    $params[] = $make;
    $types .= "s";
}

if ($min_year > 0) {
    $query .= " AND c.year >= ?";
    $params[] = $min_year;
    $types .= "i";
}

if ($max_year < 9999) {
    $query .= " AND c.year <= ?";
    $params[] = $max_year;
    $types .= "i";
}

if ($transmission) {
    $query .= " AND c.transmission = ?";
    $params[] = $transmission;
    $types .= "s";
}

if ($fuel_type) {
    $query .= " AND c.fuel_type = ?";
    $params[] = $fuel_type;
    $types .= "s";
}

if ($search) {
    $query .= " AND (cm.make LIKE ? OR cm.model LIKE ? OR c.color LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= "sss";
}

$query .= " ORDER BY cm.make, cm.model, c.year DESC";

// Prepare and execute the query
$stmt = $conn->prepare($query);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$cars = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Get unique makes for filter
$makes = $conn->query("SELECT DISTINCT make FROM car_models ORDER BY make")->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cars List | Vortex Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Add Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0a0a0a;
            color: #e5e5e5;
        }
        
        .car-card {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            transform: translateY(0);
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
            border: 1px solid #2a2a2a;
        }
        
        .car-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px -10px rgba(255, 0, 0, 0.3);
            border-color: #ff2a2a;
        }
        
        .filter-section {
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
            border: 1px solid #2a2a2a;
        }

        /* Custom Flatpickr Styles */
        .flatpickr-calendar {
            background: #1a1a1a !important;
            border: 1px solid #2a2a2a !important;
            box-shadow: 0 10px 25px rgba(0,0,0,0.5) !important;
        }

        .flatpickr-day {
            color: #e5e5e5 !important;
            border-color: #2a2a2a !important;
            transition: all 0.3s ease !important;
            position: relative !important;
        }

        .flatpickr-day:hover {
            background: #ff2a2a66 !important;
            border-color: #ff2a2a !important;
            transform: scale(1.1) !important;
            z-index: 1 !important;
        }

        .flatpickr-day.selected {
            background: #ff2a2a !important;
            border-color: #ff2a2a !important;
            color: white !important;
            font-weight: bold !important;
            transform: scale(1.1) !important;
            z-index: 1 !important;
        }

        .flatpickr-day.inRange {
            background: #ff2a2a33 !important;
            border-color: #ff2a2a !important;
            box-shadow: -5px 0 0 #ff2a2a33, 5px 0 0 #ff2a2a33 !important;
        }

        .flatpickr-day.disabled {
            background: #ff2a2a !important;
            border-color: #ff2a2a !important;
            color: #fff !important;
            cursor: not-allowed !important;
            position: relative !important;
            opacity: 1 !important;
        }

        .flatpickr-day.disabled:hover {
            filter: brightness(1.1);
            z-index: 2 !important;
        }

        .flatpickr-day.disabled::before {
            display: none !important;
        }

        .flatpickr-current-month {
            color: #e5e5e5 !important;
            padding: 10px 0 !important;
        }

        .flatpickr-months .flatpickr-month {
            background: #1a1a1a !important;
            color: #e5e5e5 !important;
            fill: #e5e5e5 !important;
        }

        .flatpickr-weekday {
            color: #ff2a2a !important;
            font-weight: 600 !important;
        }

        .flatpickr-day.today {
            border-color: #ff2a2a !important;
            background: #ff2a2a33 !important;
        }

        .flatpickr-day.today:hover,
        .flatpickr-day.today:focus {
            background: #ff2a2a66 !important;
            border-color: #ff2a2a !important;
        }

        /* Custom tooltip styles */
        .date-tooltip {
            position: absolute !important;
            top: -30px !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            background: #ff2a2a !important;
            color: white !important;
            padding: 4px 8px !important;
            border-radius: 4px !important;
            font-size: 12px !important;
            white-space: nowrap !important;
            opacity: 0 !important;
            transition: opacity 0.2s ease !important;
            pointer-events: none !important;
            z-index: 2 !important;
        }

        .date-tooltip::after {
            content: '' !important;
            position: absolute !important;
            bottom: -4px !important;
            left: 50% !important;
            transform: translateX(-50%) !important;
            border-left: 4px solid transparent !important;
            border-right: 4px solid transparent !important;
            border-top: 4px solid #ff2a2a !important;
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Navigation Bar -->
    <nav class="w-full bg-black text-white shadow-lg sticky top-0 z-50 border-b border-gray-900">
        <div class="max-w-7xl mx-auto flex justify-between items-center px-6 py-4">
            <div class="flex items-center space-x-2">
                <i class="fas fa-car text-2xl text-red-600"></i>
                <a href="index.php" class="text-2xl font-bold">VORTEX<span class="text-red-600">RENTALS</span></a>
            </div>
            <div class="hidden md:flex space-x-8 text-lg items-center">
                <a href="index.php" class="nav-link hover:text-red-500 transition">Home</a>
                <a href="carlist.php" class="nav-link hover:text-red-500 transition">Cars List</a>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a href="my_bookings.php" class="nav-link hover:text-red-500 transition">My Bookings</a>
                    <a href="logout.php" class="nav-link hover:text-red-500 transition">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link hover:text-red-500 transition">Login</a>
                    <a href="register.php" class="nav-link hover:text-red-500 transition">Register</a>
                <?php endif; ?>
                <a href="#" class="ml-4 px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-full font-medium transition-all duration-300 transform hover:scale-105">
                    <i class="fas fa-phone-alt mr-2"></i>Contact
                </a>
            </div>
            <button class="md:hidden text-2xl focus:outline-none">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="flex flex-col md:flex-row gap-8">
            <!-- Filters Sidebar -->
            <div class="w-full md:w-64 flex-shrink-0">
                <div class="filter-section rounded-xl p-6 sticky top-24">
                    <h3 class="text-xl font-bold mb-6">Filters</h3>
                    <form method="get" class="space-y-6">
                        <!-- Search -->
                        <div>
                            <label class="block text-sm font-medium mb-2">Search</label>
                            <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>"
                                class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white"
                                placeholder="Make, model, or color">
                        </div>

                        <!-- Make Filter -->
                        <div>
                            <label class="block text-sm font-medium mb-2">Make</label>
                            <select name="make" class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                                <option value="">All Makes</option>
                                <?php foreach ($makes as $m): ?>
                                    <option value="<?php echo htmlspecialchars($m['make']); ?>" 
                                        <?php echo $make === $m['make'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($m['make']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Year Range -->
                        <div>
                            <label class="block text-sm font-medium mb-2">Year Range</label>
                            <div class="grid grid-cols-2 gap-4">
                                <input type="number" name="min_year" value="<?php echo $min_year ?: ''; ?>"
                                    class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white"
                                    placeholder="Min Year">
                                <input type="number" name="max_year" value="<?php echo $max_year < 9999 ? $max_year : ''; ?>"
                                    class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white"
                                    placeholder="Max Year">
                            </div>
                        </div>

                        <!-- Transmission -->
                        <div>
                            <label class="block text-sm font-medium mb-2">Transmission</label>
                            <select name="transmission" class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                                <option value="">All Transmissions</option>
                                <option value="automatic" <?php echo $transmission === 'automatic' ? 'selected' : ''; ?>>Automatic</option>
                                <option value="manual" <?php echo $transmission === 'manual' ? 'selected' : ''; ?>>Manual</option>
                            </select>
                        </div>

                        <!-- Fuel Type -->
                        <div>
                            <label class="block text-sm font-medium mb-2">Fuel Type</label>
                            <select name="fuel_type" class="w-full px-4 py-2 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                                <option value="">All Fuel Types</option>
                                <option value="petrol" <?php echo $fuel_type === 'petrol' ? 'selected' : ''; ?>>Petrol</option>
                                <option value="diesel" <?php echo $fuel_type === 'diesel' ? 'selected' : ''; ?>>Diesel</option>
                                <option value="electric" <?php echo $fuel_type === 'electric' ? 'selected' : ''; ?>>Electric</option>
                                <option value="hybrid" <?php echo $fuel_type === 'hybrid' ? 'selected' : ''; ?>>Hybrid</option>
                            </select>
                        </div>

                        <!-- Filter Buttons -->
                        <div class="flex gap-4">
                            <button type="submit" class="flex-1 px-4 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg transition">
                                Apply Filters
                            </button>
                            <a href="carlist.php" class="px-4 py-2 bg-gray-800 hover:bg-gray-700 text-white rounded-lg transition">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Cars Grid -->
            <div class="flex-1">
                <div class="flex justify-between items-center mb-8">
                    <h2 class="text-2xl font-bold">Available Cars</h2>
                    <span class="text-gray-400"><?php echo count($cars); ?> cars found</span>
                </div>

                <?php if (empty($cars)): ?>
                    <div class="text-center py-12">
                        <i class="fas fa-car text-5xl text-gray-600 mb-4"></i>
                        <h3 class="text-xl text-gray-400 font-medium">No Cars Found</h3>
                        <p class="text-gray-500 mt-2">Try adjusting your filters</p>
                    </div>
                <?php else: ?>
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach ($cars as $car): ?>
                            <div class="car-card rounded-xl overflow-hidden">
                                <?php if (!empty($car['image_url'])): ?>
                                    <div class="relative h-48 overflow-hidden">
                                        <img src="<?php echo htmlspecialchars($car['image_url']); ?>" 
                                             alt="<?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?>" 
                                             class="w-full h-full object-cover transition duration-700 hover:scale-110">
                                        <div class="absolute top-4 right-4 bg-red-600/90 text-white px-3 py-1 rounded-full text-xs font-bold">
                                            <?php echo $car['available'] ? 'AVAILABLE' : 'RENTED'; ?>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="h-48 bg-gray-800 flex items-center justify-center">
                                        <i class="fas fa-car text-5xl text-gray-600"></i>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="p-6">
                                    <div class="flex justify-between items-start mb-3">
                                        <div>
                                            <h3 class="text-xl font-bold text-white"><?php echo htmlspecialchars($car['make']); ?></h3>
                                            <h4 class="text-lg text-red-500 font-medium"><?php echo htmlspecialchars($car['model']); ?></h4>
                                        </div>
                                        <span class="text-red-500 font-bold text-xl"><?php echo isset($car['price_mad']) && $car['price_mad'] !== null ? format_mad($car['price_mad']) : format_mad(rand(80, 250)); ?><span class="text-sm text-gray-400">/day</span></span>
                                    </div>
                                    
                                    <div class="grid grid-cols-2 gap-3 my-4">
                                        <div class="bg-gray-800/50 p-2 rounded-lg">
                                            <div class="text-gray-400 text-xs">YEAR</div>
                                            <div class="text-white font-medium"><?php echo htmlspecialchars($car['year']); ?></div>
                                        </div>
                                        <div class="bg-gray-800/50 p-2 rounded-lg">
                                            <div class="text-gray-400 text-xs">TRANSMISSION</div>
                                            <div class="text-white font-medium"><?php echo ucfirst(htmlspecialchars($car['transmission'])); ?></div>
                                        </div>
                                        <div class="bg-gray-800/50 p-2 rounded-lg">
                                            <div class="text-gray-400 text-xs">FUEL TYPE</div>
                                            <div class="text-white font-medium"><?php echo ucfirst(htmlspecialchars($car['fuel_type'])); ?></div>
                                        </div>
                                        <div class="bg-gray-800/50 p-2 rounded-lg">
                                            <div class="text-gray-400 text-xs">COLOR</div>
                                            <div class="text-white font-medium"><?php echo htmlspecialchars($car['color']); ?></div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex justify-center items-center mt-6">
                                        <button class="px-5 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-full text-sm font-bold transition-all transform hover:scale-105 flex items-center"
                                            onclick="openReserveModal(<?php echo htmlspecialchars(json_encode([
                                                'id' => $car['id'],
                                                'make' => $car['make'],
                                                'model' => $car['model'],
                                                'year' => $car['year'],
                                                'color' => $car['color'],
                                                'image_url' => $car['image_url']
                                            ]), ENT_QUOTES, 'UTF-8'); ?>)">
                                            <i class="fas fa-bolt mr-2"></i> RENT NOW 
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Reserve Modal -->
    <div id="reserveModal" class="fixed inset-0 z-50 flex items-center justify-center hidden" style="background:rgba(0,0,0,0.85)">
        <div class="bg-gradient-to-b from-gray-900 to-black rounded-xl shadow-2xl p-8 max-w-2xl w-full mx-4 border border-gray-800 relative">
            <button onclick="closeReserveModal()" class="absolute top-4 right-4 text-gray-400 hover:text-red-600 text-2xl transition">
                <i class="fas fa-times"></i>
            </button>
            <div id="reserveModalContent"></div>
        </div>
    </div>

    <script>
        function openReserveModal(car) {
            <?php if (!isset($_SESSION['user_id'])): ?>
                window.location.href = 'login.php';
                return;
            <?php endif; ?>
            
            // Fetch booked dates for this car
            fetch(`get_booked_dates.php?car_id=${car.id}`)
                .then(response => response.json())
                .then(bookedDates => {
                    let html = `
                        <div class="flex flex-col md:flex-row gap-6">
                            <div class="flex-1">
                                ${car.image_url ? `
                                <div class="mb-6 rounded-lg overflow-hidden border border-gray-800">
                                    <img src="${car.image_url}" alt="${car.make} ${car.model}" class="w-full h-40 md:h-56 object-cover">
                                </div>
                                ` : `
                                <div class="mb-6 rounded-lg border border-gray-800 bg-gray-900 h-40 md:h-56 flex items-center justify-center text-gray-600">
                                    <i class="fas fa-car text-5xl"></i>
                                </div>
                                `}
                            </div>
                            <div class="flex-1">
                                <h2 class="text-2xl font-bold text-white mb-2">${car.make} ${car.model}</h2>
                                <div class="text-gray-400 font-medium mb-4">${car.year} • ${car.color || 'N/A'}</div>
                                <form method="post" action="index.php" class="space-y-4">
                                    <input type="hidden" name="reserve_car_id" value="${car.id}">
                                    <div>
                                        <label class="block mb-1 font-semibold">Select Dates</label>
                                        <input type="text" id="dateRange" class="w-full border border-gray-700 bg-gray-800 rounded px-3 py-2 text-white" required>
                                        <input type="hidden" name="start_date" id="start_date">
                                        <input type="hidden" name="end_date" id="end_date">
                                    </div>
                                    <div class="bg-gray-800/50 p-4 rounded-lg">
                                        <h3 class="text-sm font-semibold mb-2">Booking Information</h3>
                                        <div class="text-sm text-gray-400">
                                            <p>• Minimum booking duration: 2 days</p>
                                            <p>• Maximum booking duration: 29 days</p>
                                            <p>• Bookings must be made at least 1 day in advance</p>
                                        </div>
                                    </div>
                                    <button type="submit" class="w-full bg-red-600 text-white py-2 rounded hover:bg-red-700 transition font-bold">Submit Booking</button>
                                </form>
                            </div>
                        </div>
                    `;
                    document.getElementById('reserveModalContent').innerHTML = html;
                    
                    // Initialize Flatpickr
                    const fp = flatpickr("#dateRange", {
                        mode: "range",
                        minDate: "today",
                        maxDate: new Date().fp_incr(365), // 1 year from now
                        dateFormat: "Y-m-d",
                        disable: bookedDates,
                        onChange: function(selectedDates, dateStr) {
                            if (selectedDates.length === 2) {
                                document.getElementById('start_date').value = selectedDates[0].toISOString().split('T')[0];
                                document.getElementById('end_date').value = selectedDates[1].toISOString().split('T')[0];
                            }
                        },
                        onReady: function(selectedDates, dateStr, instance) {
                            // Add custom styling for disabled dates
                            const disabledDates = instance.days.querySelectorAll('.flatpickr-disabled');
                            disabledDates.forEach(date => {
                                date.title = 'Already Booked';
                                date.classList.add('bg-red-900/30');
                                
                                // Add custom tooltip
                                const tooltip = document.createElement('div');
                                tooltip.className = 'date-tooltip';
                                tooltip.textContent = 'Already Booked';
                                date.appendChild(tooltip);
                                
                                date.addEventListener('mouseenter', () => {
                                    tooltip.style.opacity = '1';
                                });
                                
                                date.addEventListener('mouseleave', () => {
                                    tooltip.style.opacity = '0';
                                });
                            });
                        }
                    });
                    
                    document.getElementById('reserveModal').classList.remove('hidden');
                    document.body.style.overflow = 'hidden';
                });
        }

        function closeReserveModal() {
            document.getElementById('reserveModal').classList.add('hidden');
            document.body.style.overflow = 'auto';
        }

        // Close modal when clicking outside content
        document.getElementById('reserveModal').addEventListener('click', function(e) {
            if (e.target === this) closeReserveModal();
        });

        // Close with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeReserveModal();
        });
    </script>
</body>
</html> 