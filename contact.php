<?php
session_start();
require_once 'db.php';

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone_number = $_POST['phone_number'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message_text = $_POST['message'] ?? '';
    $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
    
    if ($name && $email && $phone_number && $subject && $message_text) {
        // Store message in the database
        $stmt = $conn->prepare("INSERT INTO messages (user_id, name, email, phone_number, subject, message) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $user_id, $name, $email, $phone_number, $subject, $message_text);
        if ($stmt->execute()) {
            $message = "Thank you for your message! We'll get back to you soon.";
        } else {
            $message = "Failed to send your message. Please try again later.";
        }
        $stmt->close();
    } else {
        $message = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us | Vortex Rentals</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0a0a0a;
            color: #e5e5e5;
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

    <!-- Contact Section -->
    <div class="flex-1 py-16 md:py-20 bg-gradient-to-b from-black to-gray-900">
        <div class="max-w-7xl mx-auto px-4">
            <div class="text-center mb-16 fade-in">
                <h2 class="text-3xl md:text-4xl font-bold mb-4">
                    <span class="text-white">GET IN</span> <span class="text-red-600">TOUCH</span>
                </h2>
                <div class="w-24 h-1 bg-red-600 mx-auto mb-4"></div>
                <p class="text-gray-400 max-w-2xl mx-auto">We're here to help and answer any questions you might have</p>
            </div>

            <?php if ($message): ?>
                <div class="max-w-2xl mx-auto mb-8 px-6 py-3 rounded-lg font-semibold text-lg <?php echo strpos($message, 'Thank you') !== false ? 'bg-green-600 text-white' : 'bg-red-600 text-white'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-12 max-w-6xl mx-auto">
                <!-- Contact Information -->
                <div class="space-y-8 slide-up">
                    <div class="bg-gradient-to-b from-gray-900 to-black p-6 rounded-xl border border-gray-800">
                        <h3 class="text-xl font-bold text-white mb-4">Contact Information</h3>
                        <div class="space-y-4">
                            <div class="flex items-start">
                                <i class="fas fa-map-marker-alt mt-1 mr-4 text-red-600"></i>
                                <div>
                                    <h4 class="font-semibold text-white">Our Location</h4>
                                    <p class="text-gray-400">123 Performance Ave, Motor City</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-phone-alt mt-1 mr-4 text-red-600"></i>
                                <div>
                                    <h4 class="font-semibold text-white">Phone Number</h4>
                                    <p class="text-gray-400">(555) 123-4567</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-envelope mt-1 mr-4 text-red-600"></i>
                                <div>
                                    <h4 class="font-semibold text-white">Email Address</h4>
                                    <p class="text-gray-400">contact@vortexrentals.com</p>
                                </div>
                            </div>
                            <div class="flex items-start">
                                <i class="fas fa-clock mt-1 mr-4 text-red-600"></i>
                                <div>
                                    <h4 class="font-semibold text-white">Business Hours</h4>
                                    <p class="text-gray-400">Mon - Fri: 9:00 AM - 6:00 PM</p>
                                    <p class="text-gray-400">Sat: 10:00 AM - 4:00 PM</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Google Maps -->
                    <div class="bg-gradient-to-b from-gray-900 to-black p-6 rounded-xl border border-gray-800">
                        <h3 class="text-xl font-bold text-white mb-4">Our Location</h3>
                        <div class="aspect-w-16 aspect-h-9">
                            <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d2264.5215524681003!2d-5.0003303242843025!3d34.03428457316381!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0xd9f8b0044884ed9%3A0x8b3bd4fed0276b7a!2sEcole%20ETEC%20FES!5e1!3m2!1sfr!2sma!4v1749851745818!5m2!1sfr!2sma" 
                                class="w-full h-[300px] rounded-lg border border-gray-700" 
                                style="border:0;" 
                                allowfullscreen="" 
                                loading="lazy" 
                                referrerpolicy="no-referrer-when-downgrade">
                            </iframe>
                        </div>
                    </div>

                    <div class="bg-gradient-to-b from-gray-900 to-black p-6 rounded-xl border border-gray-800">
                        <h3 class="text-xl font-bold text-white mb-4">Follow Us</h3>
                        <div class="flex space-x-4">
                            <a href="#" class="w-12 h-12 rounded-full bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center transition">
                                <i class="fab fa-facebook-f"></i>
                            </a>
                            <a href="#" class="w-12 h-12 rounded-full bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center transition">
                                <i class="fab fa-twitter"></i>
                            </a>
                            <a href="#" class="w-12 h-12 rounded-full bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center transition">
                                <i class="fab fa-instagram"></i>
                            </a>
                            <a href="#" class="w-12 h-12 rounded-full bg-gray-800 hover:bg-red-600 text-gray-300 hover:text-white flex items-center justify-center transition">
                                <i class="fab fa-linkedin-in"></i>
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Contact Form -->
                <div class="bg-gradient-to-b from-gray-900 to-black p-8 rounded-xl border border-gray-800 slide-up animate-delay-100">
                    <h3 class="text-xl font-bold text-white mb-6">Send Us a Message</h3>
                    <form method="post" class="space-y-6">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-400 mb-2">Your Name</label>
                            <input type="text" id="name" name="name" required
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        </div>
                        <div>
                            <label for="email" class="block text-sm font-medium text-gray-400 mb-2">Email Address</label>
                            <input type="email" id="email" name="email" required
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        </div>
                        <div>
                            <label for="phone_number" class="block text-sm font-medium text-gray-400 mb-2">Phone Number</label>
                            <input type="text" id="phone_number" name="phone_number" required
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        <div>
                            <label for="subject" class="block text-sm font-medium text-gray-400 mb-2">Subject</label>
                            <input type="text" id="subject" name="subject" required
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                        </div>
                        <div>
                            <label for="message" class="block text-sm font-medium text-gray-400 mb-2">Message</label>
                            <textarea id="message" name="message" rows="5" required
                                class="w-full px-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white"></textarea>
                        </div>
                        <button type="submit"
                            class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-3 px-6 rounded-lg transition-all duration-300 transform hover:scale-105">
                            Send Message
                        </button>
                    </form>
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

    <script>
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
    </script>
</body>
</html>