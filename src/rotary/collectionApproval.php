<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Indsamling - Rotary Danmarks Hjælpefond</title>
    <script src="https://unpkg.com/@tailwindcss/browser@4"></script>
</head>
<body class="bg-gray-50">
    <!-- Navigation -->
    <nav class="bg-white shadow-lg">
        <div class="max-w-7xl mx-auto px-4">
            <div class="flex justify-between items-center py-4">
                <div class="flex items-center">
                    <a href="index.php" class="text-2xl font-bold text-blue-700">Rotary Danmarks Hjælpefond</a>
                </div>
                <div class="flex items-center">
                    <img src="logo.jpg" alt="Rotary Logo" class="h-12"/>
                </div>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <div class="bg-gray-100 border-b">
        <div class="max-w-7xl mx-auto px-4 py-3">
            <div class="flex items-center space-x-2 text-sm text-gray-600">
                <a href="index.php" class="hover:text-blue-700">Forside</a>
                <span>›</span>
                <span class="text-gray-900">Indsamling</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-4xl mx-auto px-4 py-12">
        <article class="bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-8">Indsamling</h1>
            
            <!-- Important Notice -->
            <div class="bg-red-50 border-l-4 border-red-500 p-6 mb-8">
                <p class="text-red-900 font-semibold">OBS: Det er vigtigt at denne ansøgning er indsendt inden indsamlingen begynder.</p>
            </div>

            <!-- Bank Details -->
            <div class="bg-blue-50 border-l-4 border-blue-500 p-6 mb-8">
                <p class="text-blue-900">Det/de indsamlede beløb skal indsættes på konto <span class="font-semibold">3574 10845963</span>, og mærkes med projektnavn.</p>
                <p class="text-blue-900 mt-4">Beløbet vil efter projektets afslutning kunne udbetales til projektet.</p>
            </div>

            <!-- Main Information -->
            <div class="prose max-w-none space-y-6">
                <p>Før jeres klub starter en offentlig indsamling, skal I have en indsamlingstilladelse. Det gælder også projekter som Ønsketræet.</p>
                
                <p>Denne tilladelse ansøges normalt hos Civilstyrelsen og koster kr. 1.100,- pr. projekt. Derudover kræves et regnskab, der er godkendt af en autoriseret revisor. Dette gælder for indsamlinger over kr. 50.000,-.</p>

                <div class="bg-gray-50 p-6 rounded-lg">
                    <h2 class="text-xl font-bold mb-4">Hjælpefondens indsamlingstilladelse</h2>
                    <p>For at hjælpe klubber og distrikter har Rotary Danmarks Hjælpefond fået en generel treårig indsamlingstilladelse.</p>
                    <p class="mt-4">Det betyder, at I i stedet for at ansøge Civilstyrelsen om tilladelse, omkostningsfrit kan anmelde jeres indsamling til Hjælpefonden.</p>
                </div>

                <div class="flex justify-center my-8">
                    <a href="https://rdhj.dk/login?id=2" target="_blank" class="bg-blue-700 text-white px-8 py-4 rounded-lg hover:bg-blue-800 transition-colors">
                        Ansøg om indsamlingstilladelse
                    </a>
                </div>

                <div class="bg-yellow-50 border-l-4 border-yellow-500 p-6">
                    <h3 class="font-bold mb-4">Vigtig information om skattefradrag</h3>
                    <ul class="list-disc pl-6 space-y-2">
                        <li>Direkte indbetalinger kan give skattefradrag mellem kr. 200,- og 18.300,- (2024)</li>
                        <li>CPR- eller CVR nummer og indsamlingsnavn skal oplyses ved indbetaling</li>
                        <li>Beløb med skattefradrag kan ikke tilbagebetales</li>
                        <li>Beløb kan flyttes til andet godkendt projekt</li>
                    </ul>
                </div>
            </div>
        </article>
    </main>

    <!-- Footer -->
    <footer class="bg-blue-900 text-white py-8 mt-12">
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