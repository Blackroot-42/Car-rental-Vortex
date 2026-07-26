<?php
if (session_status() == PHP_SESSION_NONE) session_start();
$display_name = $_SESSION['admin_username'] ?? null;
if (!$display_name && isset($_SESSION['admin_id'])) {
    require_once 'db.php';
    $stmt = $conn->prepare("SELECT username FROM admin WHERE id = ?");
    $stmt->bind_param("i", $_SESSION['admin_id']);
    $stmt->execute();
    $stmt->bind_result($db_user);
    $stmt->fetch();
    $stmt->close();
    $display_name = $db_user ?? 'Admin User';
}
$display_name = $display_name ?? 'Admin User';
?>
<aside class="w-64 bg-gradient-to-b from-gray-900 to-black text-white flex flex-col min-h-screen border-r border-gray-800 shadow-xl">
    <!-- Logo/Header -->
    <div class="p-6 text-2xl font-bold border-b border-gray-800 flex items-center space-x-2">
        <i class="fas fa-car text-red-600"></i>
        <span>VORTEX</span>
        <span class="text-red-600">ADMIN</span>
    </div>
    
    <!-- Navigation -->
    <nav class="flex-1 p-4 space-y-1">
        <!-- Dashboard -->
        <a href="dashboard.php" class="group flex items-center px-4 py-3 rounded-lg transition-all hover:bg-gray-800 hover:shadow-md">
            <div class="w-8 h-8 flex items-center justify-center mr-3 bg-red-600/20 rounded-lg group-hover:bg-red-600 transition-all">
                <i class="fas fa-tachometer-alt text-red-500 group-hover:text-white"></i>
            </div>
            <span class="font-medium">Dashboard</span>
            <i class="fas fa-chevron-right ml-auto text-xs text-gray-400 group-hover:text-red-500"></i>
        </a>
        
        <!-- All Cars -->
        <a href="allcars.php" class="group flex items-center px-4 py-3 rounded-lg transition-all hover:bg-gray-800 hover:shadow-md">
            <div class="w-8 h-8 flex items-center justify-center mr-3 bg-red-600/20 rounded-lg group-hover:bg-red-600 transition-all">
                <i class="fas fa-car text-red-500 group-hover:text-white"></i>
            </div>
            <span class="font-medium">All Cars</span>
            <i class="fas fa-chevron-right ml-auto text-xs text-gray-400 group-hover:text-red-500"></i>
        </a>
        
        <!-- Bookings -->
        <a href="bookings.php" class="group flex items-center px-4 py-3 rounded-lg transition-all hover:bg-gray-800 hover:shadow-md">
            <div class="w-8 h-8 flex items-center justify-center mr-3 bg-red-600/20 rounded-lg group-hover:bg-red-600 transition-all">
                <i class="fas fa-calendar-check text-red-500 group-hover:text-white"></i>
            </div>
            <span class="font-medium">Bookings</span>
            <i class="fas fa-chevron-right ml-auto text-xs text-gray-400 group-hover:text-red-500"></i>
        </a>
        
        <!-- Reserve (Public Reservation Page) -->
        <a href="reserve.php" class="group flex items-center px-4 py-3 rounded-lg transition-all hover:bg-gray-800 hover:shadow-md">
            <div class="w-8 h-8 flex items-center justify-center mr-3 bg-red-600/20 rounded-lg group-hover:bg-red-600 transition-all">
                <i class="fas fa-calendar-plus text-red-500 group-hover:text-white"></i>
            </div>
            <span class="font-medium">Reserve</span>
            <i class="fas fa-chevron-right ml-auto text-xs text-gray-400 group-hover:text-red-500"></i>
        </a>
        
        <!-- Customers -->
        <a href="customers.php" class="group flex items-center px-4 py-3 rounded-lg transition-all hover:bg-gray-800 hover:shadow-md">
            <div class="w-8 h-8 flex items-center justify-center mr-3 bg-red-600/20 rounded-lg group-hover:bg-red-600 transition-all">
                <i class="fas fa-users text-red-500 group-hover:text-white"></i>
            </div>
            <span class="font-medium">Customers</span>
            <i class="fas fa-chevron-right ml-auto text-xs text-gray-400 group-hover:text-red-500"></i>
        </a>
        
        <!-- Reports -->
        <a href="reports.php" class="group flex items-center px-4 py-3 rounded-lg transition-all hover:bg-gray-800 hover:shadow-md">
            <div class="w-8 h-8 flex items-center justify-center mr-3 bg-red-600/20 rounded-lg group-hover:bg-red-600 transition-all">
                <i class="fas fa-chart-pie text-red-500 group-hover:text-white"></i>
            </div>
            <span class="font-medium">Reports</span>
            <i class="fas fa-chevron-right ml-auto text-xs text-gray-400 group-hover:text-red-500"></i>
        </a>
        
        <!-- Messages -->
        <a href="messages.php" class="group flex items-center px-4 py-3 rounded-lg transition-all hover:bg-gray-800 hover:shadow-md">
            <div class="w-8 h-8 flex items-center justify-center mr-3 bg-red-600/20 rounded-lg group-hover:bg-red-600 transition-all">
                <i class="fas fa-envelope text-red-500 group-hover:text-white"></i>
            </div>
            <span class="font-medium">Messages</span>
            <i class="fas fa-chevron-right ml-auto text-xs text-gray-400 group-hover:text-red-500"></i>
        </a>
        
        <!-- Settings -->
        <a href="settings.php" class="group flex items-center px-4 py-3 rounded-lg transition-all hover:bg-gray-800 hover:shadow-md">
            <div class="w-8 h-8 flex items-center justify-center mr-3 bg-red-600/20 rounded-lg group-hover:bg-red-600 transition-all">
                <i class="fas fa-cog text-red-500 group-hover:text-white"></i>
            </div>
            <span class="font-medium">Settings</span>
            <i class="fas fa-chevron-right ml-auto text-xs text-gray-400 group-hover:text-red-500"></i>
        </a>
    </nav>
    
    <!-- Footer/Logout -->
    <div class="p-4 border-t border-gray-800">
        <div class="flex items-center mb-4 px-2">
            <div class="w-10 h-10 rounded-full bg-red-600/20 border border-red-600/30 flex items-center justify-center mr-3">
                <i class="fas fa-user-shield text-red-500"></i>
            </div>
            <div>
                <div class="font-medium"><?php echo htmlspecialchars($display_name); ?></div>
                <div class="text-xs text-gray-400">Super Administrator</div>
            </div>
        </div>
        <form method="post" action="logout.php">
            <button type="submit" class="w-full flex items-center justify-center py-2.5 bg-gradient-to-r from-red-600 to-red-800 hover:from-red-700 hover:to-red-900 text-white rounded-lg font-medium transition-all transform hover:scale-[1.02] shadow hover:shadow-md">
                <i class="fas fa-sign-out-alt mr-2"></i> Logout
            </button>
        </form>
    </div>
</aside>