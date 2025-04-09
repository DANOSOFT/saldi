<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rotary Danmarks Hjælpefond</title>
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <h1 class="text-2xl font-bold text-blue-700">Rotary Danmarks Hjælpefond</h1>
                </div>
                <div class="flex items-center">
                    <img src="logo.jpg" alt="Rotary Logo" class="h-12"/>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="bg-blue-800 text-white">
        <div class="max-w-7xl mx-auto px-4 py-16">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 items-center">
                <div>
                    <h2 class="text-4xl font-bold mb-4">Gør en forskel i verden</h2>
                    <p class="text-xl mb-8">Sammen skaber vi positive forandringer gennem humanitære projekter</p>
                    <a href="supportUs.php" class="cursor-pointer"><button class="bg-yellow-500 text-blue-900 px-6 py-3 rounded-lg font-semibold hover:bg-yellow-400 transition cursor-pointer">
                        Støt nu
                    </button></a>
                </div>
                <div class="rounded-lg overflow-hidden shadow-xl">
                    <img src="img2.jpg" alt="Impact Image" class="w-full"/>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="max-w-7xl mx-auto px-4 py-12">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
            <!-- Menu Items -->
            <div class="space-y-4">
                <a href="history.php" class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition">
                    <h3 class="text-lg font-semibold text-blue-700">Hjælpefondens historie</h3>
                </a>
                <a href="rop.php" class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition">
                    <h3 class="text-lg font-semibold text-blue-700">Forretningsorden</h3>
                </a>
                <a href="collab.php" class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition">
                    <h3 class="text-lg font-semibold text-blue-700">Samarbejdsaftale</h3>
                </a>
            </div>

            <!-- Application Section -->
            <div class="space-y-4">
                <a href="application.php" class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition">
                    <h3 class="text-lg font-semibold text-blue-700">Ansøgning for projekttilskud</h3>
                </a>
                <a href="yearlyProjects.php" class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition">
                    <h3 class="text-lg font-semibold text-blue-700">Årets ansøgninger</h3>
                </a>
            </div>

            <!-- Tax Section -->
            <div class="space-y-4">
                <a href="tax.php" class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition">
                    <h3 class="text-lg font-semibold text-blue-700">Skattefradrag ved støtte</h3>
                </a>
                <a href="collectionApproval.php" class="block p-4 bg-white rounded-lg shadow hover:shadow-md transition">
                    <h3 class="text-lg font-semibold text-blue-700">Indsamlingstilladelse</h3>
                </a>
            </div>
        </div>
    </div>

    <!-- Testimonial Section -->
    <div class="bg-gray-100 py-12">
        <div class="max-w-7xl mx-auto px-4">
            <div class="bg-white rounded-lg shadow-lg p-8">
                <div class="flex flex-col items-center text-center">
                    <img src="/api/placeholder/120/120" alt="Testimonial" class="rounded-full mb-4"/>
                    <p class="text-xl text-gray-600 mb-4">"Gracias Rotary"</p>
                    <p class="text-gray-500">- Fra vores projekter i Latinamerika</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-blue-900 text-white py-8">
        <div class="max-w-7xl mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
                <div>
                    <h3 class="text-xl font-bold mb-4">Rotary Danmarks Hjælpefond</h3>
                    <p>Sammen gør vi en forskel</p>
                </div>
                <div class="text-right">
                    <p>© 2025 Rotary Danmarks Hjælpefond</p>
                    <p>Alle rettigheder forbeholdes</p>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>