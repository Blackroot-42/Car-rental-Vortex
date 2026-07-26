<?php
session_start();
require_once 'db.php';

// Fetch 3 random featured cars with image
$featured = [];
$res = $conn->query("SELECT cars.*, car_models.make, car_models.model FROM cars LEFT JOIN car_models ON cars.car_model_id = car_models.id WHERE image_url IS NOT NULL AND image_url != '' LIMIT 3");
while ($row = $res->fetch_assoc()) {
    $featured[] = $row;
}

// Handle booking form submission
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reserve_car_id'])) {
    if (!isset($_SESSION['user_id'])) {
        $message = "Please login to book a car.";
    } else {
        $car_id = intval($_POST['reserve_car_id']);
        $user_id = $_SESSION['user_id'];
        $start_date = $_POST['start_date'] ?? '';
        $end_date = $_POST['end_date'] ?? '';
        
        if ($car_id && $start_date && $end_date) {
            // Validate dates
            $today = date('Y-m-d');
            if ($start_date < $today) {
                $message = "Cannot book dates in the past.";
            } elseif ($end_date < $start_date) {
                $message = "End date must be after start date.";
            } else {
                // Check for existing bookings
                $stmt = $conn->prepare("SELECT id FROM bookings WHERE car_id = ? AND status != 'cancelled' AND 
                    ((start_date <= ? AND end_date >= ?) OR 
                     (start_date <= ? AND end_date >= ?) OR 
                     (start_date >= ? AND end_date <= ?))");
                $stmt->bind_param("issssss", $car_id, $end_date, $start_date, $end_date, $start_date, $start_date, $end_date);
                $stmt->execute();
                $result = $stmt->get_result();
                
                if ($result->num_rows > 0) {
                    $message = "This car is already booked for the selected dates.";
                } else {
                    // Get user's full name
                    $stmt = $conn->prepare("SELECT full_name FROM users WHERE id = ?");
                    $stmt->bind_param("i", $user_id);
                    $stmt->execute();
                    $user = $stmt->get_result()->fetch_assoc();
                    
                    $stmt = $conn->prepare("INSERT INTO bookings (car_id, user_id, user_name, start_date, end_date, status) VALUES (?, ?, ?, ?, ?, 'pending')");
                    $stmt->bind_param("iisss", $car_id, $user_id, $user['full_name'], $start_date, $end_date);
                    if ($stmt->execute()) {
                        $message = "Booking request submitted!";
                    } else {
                        $message = "Failed to reserve car.";
                    }
                }
                $stmt->close();
            }
        } else {
            $message = "Please fill in all fields.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vortex Rentals | Premium Car Rental</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Add this in the head section after other CSS links -->
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
        
        .fade-in {
            animation: fadeIn 1s ease-in forwards;
            opacity: 0;
        }
        
        @keyframes fadeIn {
            to { opacity: 1; }
        }
        
        .slide-up {
            animation: slideUp 0.8s cubic-bezier(0.175, 0.885, 0.32, 1.275) forwards;
            opacity: 0;
            transform: translateY(30px);
        }
        
        @keyframes slideUp {
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .pulse {
            animation: pulse 2s infinite cubic-bezier(0.4, 0, 0.6, 1);
        }
        
        @keyframes pulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }
        
        .glow {
            text-shadow: 0 0 10px rgba(255, 0, 0, 0.7);
        }
        
        .nav-link {
            position: relative;
        }
        
        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -4px;
            left: 0;
            background-color: #ff2a2a;
            transition: width 0.3s ease;
        }
        
        .nav-link:hover::after {
            width: 100%;
        }
        
        .animate-delay-100 {
            animation-delay: 0.1s;
        }
        
        .animate-delay-200 {
            animation-delay: 0.2s;
        }
        
        .animate-delay-300 {
            animation-delay: 0.3s;
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
<body class="min-h-screen flex flex-col">
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
                    <a href="profile.php" class="nav-link hover:text-red-500 transition">Profile</a>
                    <a href="logout.php" class="nav-link hover:text-red-500 transition">Logout</a>
                <?php else: ?>
                    <a href="login.php" class="nav-link hover:text-red-500 transition">Login</a>
                    <a href="register.php" class="nav-link hover:text-red-500 transition">Register</a>
                <?php endif; ?>
                <a href="contact.php" class="ml-4 px-6 py-2 bg-red-600 hover:bg-red-700 text-white rounded-full font-medium transition-all duration-300 transform hover:scale-105">
                    <i class="fas fa-phone-alt mr-2"></i>Contact
                </a>
            </div>
            <button class="md:hidden text-2xl focus:outline-none">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="relative bg-black py-20 md:py-28 text-white overflow-hidden">
        <div class="absolute inset-0 z-0">
            <div class="absolute inset-0 bg-gradient-to-r from-black via-transparent to-black"></div>
            <div class="absolute inset-0 bg-[url('https://images.unsplash.com/photo-1503376780353-7e6692767b70?ixlib=rb-1.2.1&auto=format&fit=crop&w=1350&q=80')] bg-cover bg-center opacity-30"></div>
        </div>
        
        <div class="max-w-7xl mx-auto px-6 relative z-10 text-center">
            <h1 class="text-4xl md:text-6xl font-bold mb-6 leading-tight slide-up">
                UNLEASH <span class="text-red-600 glow">THE BEAST</span>
            </h1>
            <p class="text-xl md:text-2xl text-gray-300 mb-8 max-w-3xl mx-auto slide-up animate-delay-100">
                Premium performance vehicles for those who demand power and style
            </p>
            <div class="flex flex-col sm:flex-row justify-center gap-4 slide-up animate-delay-200">
                <a href="carlist.php" class="px-8 py-4 bg-red-600 hover:bg-red-700 text-white rounded-full font-bold text-lg transition-all transform hover:scale-105 shadow-lg pulse">
                    EXPLORE FLEET <i class="fas fa-arrow-right ml-2"></i>
                </a>
                <a href="Crar edit for website.mp4" class="px-8 py-4 bg-transparent border-2 border-red-600 hover:bg-red-900/30 text-white rounded-full font-bold text-lg transition-all transform hover:scale-105">
                    <i class="fas fa-play mr-2"></i> WATCH VIDEO
                </a>
            </div>
        </div>
    </div>

    <!-- Featured Cars Section -->
    <div class="flex-1 py-16 md:py-20 bg-gradient-to-b from-black to-gray-900">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-16 fade-in">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">
                    <span class="text-white">OUR</span> <span class="text-red-600">FLAGSHIP</span> <span class="text-white">MODELS</span>
                </h2>
                <div class="w-24 h-1 bg-red-600 mx-auto mb-4"></div>
                <p class="text-gray-400 max-w-2xl mx-auto">Experience the pinnacle of automotive engineering</p>
            </div>
            
            <!-- Show booking message -->
            <?php if ($message): ?>
                <div class="fixed top-6 left-1/2 transform -translate-x-1/2 z-50 px-6 py-3 rounded-lg font-semibold text-lg <?php echo strpos($message, 'submitted') !== false ? 'bg-green-600 text-white' : 'bg-red-600 text-white'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-10">
                <?php foreach ($featured as $index => $car): ?>
                <div class="car-card rounded-xl overflow-hidden slide-up" style="animation-delay: <?php echo $index * 0.2 + 0.3; ?>s">
                    <?php if (!empty($car['image_url'])): ?>
                        <div class="relative h-64 overflow-hidden">
                            <img src="<?php echo htmlspecialchars($car['image_url']); ?>" 
                                 alt="<?php echo htmlspecialchars($car['make'] . ' ' . $car['model']); ?>" 
                                 class="w-full h-full object-cover transition duration-700 hover:scale-110">
                            <div class="absolute top-4 right-4 bg-red-600/90 text-white px-3 py-1 rounded-full text-xs font-bold">
                                <?php echo $car['available'] ? 'AVAILABLE NOW' : 'SOLD OUT'; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <div class="p-6">
                        <div class="flex justify-between items-start mb-3">
                            <div>
                                <h3 class="text-xl font-bold text-white"><?php echo htmlspecialchars($car['make']); ?></h3>
                                <h4 class="text-lg text-red-500 font-medium"><?php echo htmlspecialchars($car['model']); ?></h4>
                            </div>
                            <span class="text-red-500 font-bold text-xl"><?php echo format_mad(rand(80, 250)); ?><span class="text-sm text-gray-400">/day</span></span>
                        </div>
                        
                        <div class="grid grid-cols-2 gap-3 my-4">
                            <div class="bg-gray-800/50 p-2 rounded-lg">
                                <div class="text-gray-400 text-xs">YEAR</div>
                                <div class="text-white font-medium"><?php echo htmlspecialchars($car['year']); ?></div>
                            </div>
                            <div class="bg-gray-800/50 p-2 rounded-lg">
                                <div class="text-gray-400 text-xs">ENGINE</div>
                                <div class="text-white font-medium"><?php echo htmlspecialchars(rand(2, 6) . '.' . rand(0,9) . 'L V' . rand(4,8)); ?></div>
                            </div>
                            <div class="bg-gray-800/50 p-2 rounded-lg">
                                <div class="text-gray-400 text-xs">POWER</div>
                                <div class="text-white font-medium"><?php echo htmlspecialchars(rand(300, 700)); ?>HP</div>
                            </div>
                            <div class="bg-gray-800/50 p-2 rounded-lg">
                                <div class="text-gray-400 text-xs">0-60 MPH</div>
                                <div class="text-white font-medium"><?php echo htmlspecialchars(rand(2, 5) . '.' . rand(0,9)); ?>s</div>
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
                
                <?php if (empty($featured)): ?>
                <div class="col-span-3 text-center py-12 fade-in">
                    <i class="fas fa-car-crash text-5xl text-gray-600 mb-4"></i>
                    <h3 class="text-xl text-gray-400 font-medium">NO VEHICLES CURRENTLY AVAILABLE</h3>
                    <p class="text-gray-500 mt-2">Please check back later for our premium selection</p>
                    <a href="allcars.php" class="inline-block mt-4 px-6 py-2.5 bg-red-600 hover:bg-red-700 text-white rounded-full transition-all">VIEW INVENTORY</a>
                </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-16 fade-in animate-delay-300">
                <a href="carlist.php" class="inline-flex items-center px-8 py-3.5 border border-transparent text-base font-bold rounded-full shadow-sm text-white bg-gradient-to-r from-red-600 to-red-800 hover:from-red-700 hover:to-red-900 transition-all transform hover:scale-105">
                    VIEW FULL INVENTORY
                    <i class="fas fa-arrow-right ml-3"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <div class="py-16 md:py-20 bg-black">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-16 fade-in">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">
                    <span class="text-white">WHY</span> <span class="text-red-600">CHOOSE</span> <span class="text-white">US</span>
                </h2>
                <div class="w-24 h-1 bg-red-600 mx-auto mb-4"></div>
                <p class="text-gray-400 max-w-2xl mx-auto">We redefine the car rental experience</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-gradient-to-b from-gray-900 to-black p-6 rounded-xl border border-gray-800 hover:border-red-600/50 transition-all slide-up">
                    <div class="w-16 h-16 bg-red-600/20 rounded-full flex items-center justify-center mb-6 text-red-500 border border-red-600/30">
                        <i class="fas fa-gem text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">PREMIUM FLEET</h3>
                    <p class="text-gray-400">Only the finest performance and luxury vehicles, meticulously maintained to perfection.</p>
                </div>
                
                <div class="bg-gradient-to-b from-gray-900 to-black p-6 rounded-xl border border-gray-800 hover:border-red-600/50 transition-all slide-up animate-delay-100">
                    <div class="w-16 h-16 bg-red-600/20 rounded-full flex items-center justify-center mb-6 text-red-500 border border-red-600/30">
                        <i class="fas fa-shield-alt text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">FULL PROTECTION</h3>
                    <p class="text-gray-400">Comprehensive insurance coverage and 24/7 roadside assistance for complete peace of mind.</p>
                </div>
                
                <div class="bg-gradient-to-b from-gray-900 to-black p-6 rounded-xl border border-gray-800 hover:border-red-600/50 transition-all slide-up animate-delay-200">
                    <div class="w-16 h-16 bg-red-600/20 rounded-full flex items-center justify-center mb-6 text-red-500 border border-red-600/30">
                        <i class="fas fa-tachometer-alt text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-bold text-white mb-3">EXPRESS SERVICE</h3>
                    <p class="text-gray-400">Fast-track rental process with digital paperwork and priority vehicle preparation.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Testimonials Section -->
    <div class="py-16 md:py-20 bg-gradient-to-b from-gray-900 to-black">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-16 fade-in">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">
                    <span class="text-white">CLIENT</span> <span class="text-red-600">TESTIMONIALS</span>
                </h2>
                <div class="w-24 h-1 bg-red-600 mx-auto mb-4"></div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="bg-black p-6 rounded-xl border border-gray-800 hover:border-red-600/30 transition-all slide-up">
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 mr-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <span class="text-gray-500 text-sm">2 days ago</span>
                    </div>
                    <p class="text-gray-300 mb-6 italic">"The Ferrari 488 was an absolute dream to drive. Vortex made the rental process seamless and the car was in showroom condition!"</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-red-600/20 flex items-center justify-center text-red-500 font-bold mr-3 border border-red-600/30">MC</div>
                        <div>
                            <h4 class="font-bold text-white">Michael C.</h4>
                            <p class="text-gray-500 text-sm">Weekend Rental</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-black p-6 rounded-xl border border-gray-800 hover:border-red-600/30 transition-all slide-up animate-delay-100">
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 mr-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star-half-alt"></i>
                        </div>
                        <span class="text-gray-500 text-sm">1 week ago</span>
                    </div>
                    <p class="text-gray-300 mb-6 italic">"Impeccable service and an incredible selection of vehicles. The Lamborghini Huracan exceeded all my expectations."</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-red-600/20 flex items-center justify-center text-red-500 font-bold mr-3 border border-red-600/30">SL</div>
                        <div>
                            <h4 class="font-bold text-white">Sarah L.</h4>
                            <p class="text-gray-500 text-sm">Birthday Celebration</p>
                        </div>
                    </div>
                </div>
                
                <div class="bg-black p-6 rounded-xl border border-gray-800 hover:border-red-600/30 transition-all slide-up animate-delay-200">
                    <div class="flex items-center mb-4">
                        <div class="text-yellow-400 mr-2">
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                            <i class="fas fa-star"></i>
                        </div>
                        <span class="text-gray-500 text-sm">3 weeks ago</span>
                    </div>
                    <p class="text-gray-300 mb-6 italic">"For my business trip, the Mercedes S-Class was perfect. Professional service and the car was flawless. Will definitely use again."</p>
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-red-600/20 flex items-center justify-center text-red-500 font-bold mr-3 border border-red-600/30">DR</div>
                        <div>
                            <h4 class="font-bold text-white">David R.</h4>
                            <p class="text-gray-500 text-sm">Corporate Client</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-black pt-16 pb-8 border-t border-gray-900">
        <div class="max-w-7xl mx-auto px-6">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-12">
                <div>
                    <h3 class="text-xl font-bold mb-6 flex items-center">
                        <i class="fas fa-car mr-3 text-red-600"></i> 
                        <span class="text-white">VORTEX</span><span class="text-red-600">RENTALS</span>
                    </h3>
                    <p class="text-gray-400 mb-6">Premium performance and luxury vehicle rentals for the discerning driver.</p>
                    <div class="flex space-x-4">
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center transition">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center transition">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center transition">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="#" class="w-10 h-10 rounded-full bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center transition">
                            <i class="fab fa-youtube"></i>
                        </a>
                    </div>
                </div>
                
                <div>
                    <h4 class="font-bold text-lg mb-6 text-white">QUICK LINKS</h4>
                    <ul class="space-y-3">
                        <li><a href="index.php" class="text-gray-400 hover:text-red-500 transition">Home</a></li>
                        <li><a href="allcars.php" class="text-gray-400 hover:text-red-500 transition">Our Fleet</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-red-500 transition">Special Offers</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-red-500 transition">Locations</a></li>
                        <li><a href="#" class="text-gray-400 hover:text-red-500 transition">FAQ</a></li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-bold text-lg mb-6 text-white">CONTACT US</h4>
                    <ul class="space-y-3">
                        <li class="flex items-start text-gray-400">
                            <i class="fas fa-map-marker-alt mt-1 mr-3 text-red-600"></i>
                            <span>123 Performance Ave, Motor City</span>
                        </li>
                        <li class="flex items-center text-gray-400">
                            <i class="fas fa-phone-alt mr-3 text-red-600"></i>
                            <span>(555) 123-4567</span>
                        </li>
                        <li class="flex items-center text-gray-400">
                            <i class="fas fa-envelope mr-3 text-red-600"></i>
                            <span>contact@vortexrentals.com</span>
                        </li>
                    </ul>
                </div>
                
                <div>
                    <h4 class="font-bold text-lg mb-6 text-white">NEWSLETTER</h4>
                    <p class="text-gray-400 mb-4">Subscribe for exclusive offers and updates</p>
                    <form class="flex">
                        <input type="email" placeholder="Your email" class="px-4 py-3 bg-gray-800 text-white rounded-l-lg focus:outline-none focus:ring-2 focus:ring-red-600 w-full border border-gray-700">
                        <button type="submit" class="px-4 py-3 bg-red-600 hover:bg-red-700 text-white rounded-r-lg transition">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="border-t border-gray-800 pt-8 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-500 text-sm mb-4 md:mb-0">© 2023 VORTEX RENTALS. ALL RIGHTS RESERVED.</p>
                <div class="flex space-x-6">
                    <a href="#" class="text-gray-500 hover:text-red-500 text-sm transition">Privacy Policy</a>
                    <a href="admin_login.php" class="text-gray-500 hover:text-red-500 text-sm transition">Admin</a>
                    <a href="#" class="text-gray-500 hover:text-red-500 text-sm transition">Terms of Service</a>
                    <a href="#" class="text-gray-500 hover:text-red-500 text-sm transition">Sitemap</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Back to Top Button -->
    <button id="backToTop" class="fixed bottom-8 right-8 bg-red-600 text-white w-12 h-12 rounded-full shadow-xl flex items-center justify-center transition-all opacity-0 invisible hover:bg-red-700">
        <i class="fas fa-arrow-up"></i>
    </button>

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
        // Back to Top Button
        const backToTopButton = document.getElementById('backToTop');
        
        window.addEventListener('scroll', () => {
            if (window.pageYOffset > 300) {
                backToTopButton.classList.remove('opacity-0', 'invisible');
                backToTopButton.classList.add('opacity-100', 'visible');
            } else {
                backToTopButton.classList.remove('opacity-100', 'visible');
                backToTopButton.classList.add('opacity-0', 'invisible');
            }
        });
        
        backToTopButton.addEventListener('click', () => {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
        
        // Initialize animations when elements come into view
        const animateOnScroll = () => {
            const elements = document.querySelectorAll('.fade-in, .slide-up');
            
            elements.forEach(element => {
                const elementPosition = element.getBoundingClientRect().top;
                const screenPosition = window.innerHeight / 1.3;
                
                if (elementPosition < screenPosition) {
                    element.style.animationPlayState = 'running';
                }
            });
        };
        
        // Set initial state
        document.querySelectorAll('.fade-in, .slide-up').forEach(el => {
            el.style.animationPlayState = 'paused';
        });
        
        window.addEventListener('scroll', animateOnScroll);
        window.addEventListener('load', animateOnScroll);
        
        // Mobile menu toggle
        document.querySelector('button.md\\:hidden').addEventListener('click', () => {
            alert('Mobile menu would open here. Implement a proper mobile menu toggle as needed.');
        });
        
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
                                <form method="post" class="space-y-4">
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
                                            <p>• Minimum booking duration: 1 day</p>
                                            <p>• Maximum booking duration: 30 days</p>
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