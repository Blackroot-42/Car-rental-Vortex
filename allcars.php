<?php
require_once 'db.php';

// Updated SQL to join car_models
$sql = "SELECT cars.*, car_models.make, car_models.model
        FROM cars
        LEFT JOIN car_models ON cars.car_model_id = car_models.id";
$result = $conn->query($sql);

// Prepare car data for JS
$carsData = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $carsData[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Fleet | Vortex Rentals</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #0a0a0a;
            color: #e5e5e5;
        }
        
        .car-row {
            transition: all 0.3s ease;
            background: linear-gradient(145deg, #1a1a1a, #0f0f0f);
        }
        
        .car-row:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px -5px rgba(255, 0, 0, 0.2);
            border-left: 3px solid #ff2a2a;
        }
        
        .modal-overlay {
            background-color: rgba(0, 0, 0, 0.8);
        }
        
        .action-btn {
            transition: all 0.2s ease;
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="min-h-screen flex">
    <?php include 'sidebar.php'; ?>
    
    <main class="flex-1 p-8">
        <div class="bg-gradient-to-b from-gray-900 to-black rounded-xl shadow-2xl p-8 border border-gray-800">
            <!-- Header -->
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 border-b border-gray-800 pb-6">
                <div>
                    <h1 class="text-3xl font-bold text-white mb-2">
                        <span class="text-red-600">VEHICLE</span> FLEET
                    </h1>
                    <p class="text-gray-400">Manage all vehicles in your inventory</p>
                </div>
                <a href="car_add.php" class="mt-4 md:mt-0 px-6 py-3 bg-gradient-to-r from-red-600 to-red-800 hover:from-red-700 hover:to-red-900 text-white rounded-lg font-bold transition-all transform hover:scale-105 flex items-center">
                    <i class="fas fa-plus-circle mr-2"></i> ADD VEHICLE
                </a>
            </div>
            
            <!-- Search and Filters -->
            <div class="mb-8 flex flex-col md:flex-row gap-4">
                <div class="relative flex-1">
                    <input id="searchInput" type="text" placeholder="Search vehicles..." aria-label="Search vehicles" class="w-full pl-10 pr-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-red-600 text-white">
                    <i class="fas fa-search absolute left-3 top-3.5 text-gray-500"></i>
                </div>
                <select id="makeFilter" class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-red-600">
                    <option value="all">All Makes</option>
                    <?php
                    $makes = [];
                    foreach ($carsData as $c) {
                        if (!empty($c['make'])) $makes[] = $c['make'];
                    }
                    $makes = array_unique($makes);
                    sort($makes);
                    foreach ($makes as $make) {
                        echo '<option value="'.htmlspecialchars($make).'">'.htmlspecialchars($make).'</option>';
                    }
                    ?>
                </select>
                <select id="statusFilter" class="bg-gray-800 border border-gray-700 rounded-lg px-4 py-3 text-white focus:outline-none focus:ring-2 focus:ring-red-600">
                    <option value="all">All Status</option>
                    <option value="available">Available</option>
                    <option value="rented">Rented</option>
                    <option value="maintenance">Maintenance</option>
                </select>
            </div> 
            
            <!-- Cars Table -->
            <div class="overflow-x-auto rounded-lg border border-gray-800">
                <table class="min-w-full" id="cars-table">
                    <thead class="bg-gray-800">
                        <tr>
                            <th class="py-4 px-6 text-left text-gray-300 font-medium uppercase tracking-wider">ID</th>
                            <th class="py-4 px-6 text-left text-gray-300 font-medium uppercase tracking-wider">Make</th>
                            <th class="py-4 px-6 text-left text-gray-300 font-medium uppercase tracking-wider">Model</th>
                            <th class="py-4 px-6 text-left text-gray-300 font-medium uppercase tracking-wider">Year</th>
                            <th class="py-4 px-6 text-left text-gray-300 font-medium uppercase tracking-wider">Status</th>
                            <th class="py-4 px-6 text-left text-gray-300 font-medium uppercase tracking-wider">Image</th>
                            <th class="py-4 px-6 text-left text-gray-300 font-medium uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-800">
                    <?php if (!empty($carsData)): ?>
                        <?php foreach ($carsData as $row): ?>
                            <?php $row_status = (isset($row['status']) && $row['status'] !== '') ? $row['status'] : ($row['available'] ? 'Available' : 'Rented'); ?>
                            <tr class="car-row" data-make="<?php echo htmlspecialchars($row['make']); ?>" data-model="<?php echo htmlspecialchars($row['model']); ?>" data-year="<?php echo htmlspecialchars($row['year']); ?>" data-status="<?php echo htmlspecialchars($row_status); ?>" data-color="<?php echo htmlspecialchars($row['color'] ?? ''); ?>" data-transmission="<?php echo htmlspecialchars($row['transmission'] ?? ''); ?>" data-fuel="<?php echo htmlspecialchars($row['fuel_type'] ?? ''); ?>" onclick="showCarModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)">
                                <td class="py-4 px-6"><?php echo htmlspecialchars($row['id']); ?></td>
                                <td class="py-4 px-6 font-medium"><?php echo htmlspecialchars($row['make']); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($row['model']); ?></td>
                                <td class="py-4 px-6"><?php echo htmlspecialchars($row['year']); ?></td>
                                <td class="py-4 px-6">
                                    <?php if ($row['available']): ?>
                                        <span class="px-3 py-1 bg-green-900/50 text-green-400 rounded-full text-sm font-medium">Available</span>
                                    <?php else: ?>
                                        <span class="px-3 py-1 bg-red-900/50 text-red-400 rounded-full text-sm font-medium">Rented</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6">
                                    <?php if (!empty($row['image_url'])): ?>
                                        <div class="w-16 h-12 rounded-md overflow-hidden border border-gray-700">
                                            <img src="<?php echo htmlspecialchars($row['image_url']); ?>" alt="Car Image" class="w-full h-full object-cover">
                                        </div>
                                    <?php else: ?>
                                        <span class="text-gray-500"><i class="fas fa-image"></i></span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-4 px-6" onclick="event.stopPropagation();">
                                    <div class="flex space-x-3">
                                        <a href="car_edit.php?id=<?php echo $row['id']; ?>" class="action-btn text-blue-400 hover:text-blue-300" title="Edit">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="car_delete.php?id=<?php echo $row['id']; ?>" class="action-btn text-red-400 hover:text-red-300" title="Delete" onclick="return confirm('Are you sure you want to delete this vehicle?');">
                                            <i class="fas fa-trash-alt"></i>
                                        </a>
                                        <button class="action-btn text-purple-400 hover:text-purple-300" title="Quick View" onclick="showCarModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-gray-500">
                                <i class="fas fa-car-crash text-3xl mb-3"></i>
                                <p>No vehicles found in inventory</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr id="no-results" class="hidden">
                        <td colspan="7" class="py-8 text-center text-gray-500">
                            <i class="fas fa-search-minus text-3xl mb-3"></i>
                            <p>No vehicles match your search or filter</p>
                        </td>
                    </tr>
                    </tbody> 
                </table>
            </div>
            
            <!-- Pagination -->
            <div class="mt-8 flex justify-between items-center">
                <div class="text-gray-400">
                    Showing <span class="text-white" id="showingFrom">0</span> to <span class="text-white" id="showingTo">0</span> of <span class="text-white" id="totalEntries"><?php echo count($carsData); ?></span> entries
                </div> 
                <div class="flex space-x-2">
                    <button class="px-4 py-2 bg-gray-800 rounded-lg text-gray-400 hover:bg-gray-700 hover:text-white">
                        <i class="fas fa-angle-left"></i>
                    </button>
                    <button class="px-4 py-2 bg-red-600 rounded-lg text-white">1</button>
                    <button class="px-4 py-2 bg-gray-800 rounded-lg text-gray-400 hover:bg-gray-700 hover:text-white">2</button>
                    <button class="px-4 py-2 bg-gray-800 rounded-lg text-gray-400 hover:bg-gray-700 hover:text-white">3</button>
                    <button class="px-4 py-2 bg-gray-800 rounded-lg text-gray-400 hover:bg-gray-700 hover:text-white">
                        <i class="fas fa-angle-right"></i>
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Modal -->
        <div id="carModal" class="fixed inset-0 z-50 flex items-center justify-center hidden modal-overlay">
            <div class="bg-gradient-to-b from-gray-900 to-black rounded-xl shadow-2xl p-8 max-w-2xl w-full mx-4 border border-gray-800 relative">
                <button onclick="closeCarModal()" class="absolute top-4 right-4 text-gray-400 hover:text-red-600 text-2xl transition">
                    <i class="fas fa-times"></i>
                </button>
                <div id="carModalContent" class="flex flex-col md:flex-row gap-6"></div>
            </div>
        </div>
    </main>
    
    <script>
    function showCarModal(car) {
        let html = `
            <div class="flex-1">
                ${car.image_url ? `
                <div class="mb-6 rounded-lg overflow-hidden border border-gray-800">
                    <img src="${car.image_url}" alt="${car.make} ${car.model}" class="w-full h-48 md:h-64 object-cover">
                </div>
                ` : `
                <div class="mb-6 rounded-lg border border-gray-800 bg-gray-900 h-48 md:h-64 flex items-center justify-center text-gray-600">
                    <i class="fas fa-car text-5xl"></i>
                </div>
                `}
            </div>
            <div class="flex-1">
                <h2 class="text-2xl font-bold text-white mb-2">${car.make} ${car.model}</h2>
                <div class="text-red-500 font-medium mb-6">${car.year} • ${car.color || 'N/A'}</div>
                
                <div class="grid grid-cols-2 gap-4 mb-6">
                    <div class="bg-gray-800/50 p-3 rounded-lg">
                        <div class="text-gray-400 text-sm">MILEAGE</div>
                        <div class="text-white font-medium">${car.mileage || 'N/A'} km</div>
                    </div>
                    <div class="bg-gray-800/50 p-3 rounded-lg">
                        <div class="text-gray-400 text-sm">TRANSMISSION</div>
                        <div class="text-white font-medium">${car.transmission || 'N/A'}</div>
                    </div>
                    <div class="bg-gray-800/50 p-3 rounded-lg">
                        <div class="text-gray-400 text-sm">FUEL TYPE</div>
                        <div class="text-white font-medium">${car.fuel_type || 'N/A'}</div>
                    </div>
                    <div class="bg-gray-800/50 p-3 rounded-lg">
                        <div class="text-gray-400 text-sm">STATUS</div>
                        <div class="${car.available == 1 ? 'text-green-400' : 'text-red-400'} font-medium">
                            ${car.available == 1 ? 'Available' : 'Not Available'}
                        </div>
                    </div>
                </div>
                
                <div class="mt-4 text-gray-400 text-sm">
                    <div class="mb-1">Added: ${car.created_at}</div>
                    ${car.updated_at ? `<div>Last updated: ${car.updated_at}</div>` : ''}
                </div>
            </div>
        `;
        document.getElementById('carModalContent').innerHTML = html;
        document.getElementById('carModal').classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    }
    
    function closeCarModal() {
        document.getElementById('carModal').classList.add('hidden');
        document.body.style.overflow = 'auto';
    }
    
    // Close modal when clicking outside content
    document.getElementById('carModal').addEventListener('click', function(e) {
        if (e.target === this) closeCarModal();
    });
    
    // Close with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeCarModal();
    });

    // ----------------------
    // Client-side filtering
    // ----------------------
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('searchInput');
        const makeFilter = document.getElementById('makeFilter');
        const statusFilter = document.getElementById('statusFilter');
        const rows = Array.from(document.querySelectorAll('#cars-table tbody tr.car-row'));
        const noResultsRow = document.getElementById('no-results');
        const showingFrom = document.getElementById('showingFrom');
        const showingTo = document.getElementById('showingTo');
        const totalEntries = document.getElementById('totalEntries');

        function normalize(s){ return String(s||'').trim().toLowerCase(); }

        function filterCars() {
            const q = normalize(searchInput.value);
            const make = makeFilter.value;
            const status = statusFilter.value;
            let visibleCount = 0;

            rows.forEach(r => {
                const makeVal = normalize(r.dataset.make);
                const modelVal = normalize(r.dataset.model);
                const yearVal = normalize(r.dataset.year);
                const colorVal = normalize(r.dataset.color);
                const transVal = normalize(r.dataset.transmission);
                const fuelVal = normalize(r.dataset.fuel);
                const statusVal = normalize(r.dataset.status);

                let matchSearch = true;
                if (q) {
                    const hay = [makeVal, modelVal, yearVal, colorVal, transVal, fuelVal].join(' ');
                    matchSearch = hay.indexOf(q) !== -1;
                }

                let matchMake = (make === 'all' || normalize(make) === makeVal);
                let matchStatus = (status === 'all' || normalize(status) === statusVal);

                if (matchSearch && matchMake && matchStatus) {
                    r.style.display = '';
                    visibleCount++;
                } else {
                    r.style.display = 'none';
                }
            });

            if (visibleCount === 0) {
                noResultsRow.classList.remove('hidden');
            } else {
                noResultsRow.classList.add('hidden');
            }

            showingFrom.textContent = visibleCount > 0 ? 1 : 0;
            showingTo.textContent = visibleCount;
            totalEntries.textContent = rows.length;
        }

        // debounce for search
        let debounce;
        searchInput.addEventListener('input', function(){ clearTimeout(debounce); debounce=setTimeout(filterCars,250);});
        makeFilter.addEventListener('change', filterCars);
        statusFilter.addEventListener('change', filterCars);

        // run initially
        filterCars();
    });
    </script>
</body>
</html>