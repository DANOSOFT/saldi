<?php
@session_start();
$s_id = session_id();
$webservice = 'on';

// Default values
$regnskab = isset($_GET['regnskab']) ? $_GET['regnskab'] : '';
$metode = isset($_GET['metode']) ? $_GET['metode'] : 'kort';
$valg = isset($_GET['valg']) ? $_GET['valg'] : 'donation';
$fakturanr = isset($_GET['fakturanr']) ? $_GET['fakturanr'] : '';
$belob = '';
$interval = 'kvartal';

// Form handling would go here
if (isset($_POST['tilmeld'])) {
    // Process form submission
    // This is where your form processing logic would go
}
?>

<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rotary Danmarks Hjælpefond - <?php echo $metode == 'PBS' ? 'PBS tilmelding' : 'Kortbetaling'; ?></title>
    <link href="https://cdn.tailwindcss.com" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <header class="bg-white py-4 border-b">
        <div class="container mx-auto px-4 flex justify-between items-center">
            <a href="/" class="text-blue-700 font-bold text-2xl">Rotary Danmarks Hjælpefond</a>
            <img src="/rotary-logo.png" alt="Rotary Logo" class="h-12">
        </div>
    </header>

    <nav class="bg-white py-2 border-b">
        <div class="container mx-auto px-4">
            <ul class="flex space-x-4">
                <li><a href="/" class="text-gray-600 hover:text-blue-700">Forside</a></li>
                <li><span class="text-gray-600">/</span></li>
                <li><a href="#" class="text-gray-600 hover:text-blue-700">Hjælp os — Støt os</a></li>
            </ul>
        </div>
    </nav>

    <main class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto bg-white rounded-lg shadow p-8">
            <h1 class="text-3xl font-bold mb-6">Hjælp os — Støt os</h1>

            <div class="bg-blue-50 border-l-4 border-blue-500 p-4 mb-8">
                <p class="text-gray-700">
                    Hjælpefonden støtter de mange fremragende klubprojekter i ind- og udland, men må normalt stoppe
                    støtten inden Rotaryåret udløber. Kassen er tom flere måneder før, så vi håber, at I vil hjælpe os med et
                    bidrag, således at vi kan fortsætte og intensivere vores støtte.
                </p>
            </div>

            <section class="mb-8">
                <h2 class="text-xl font-bold mb-4">Skattefradrag</h2>
                <ul class="list-disc pl-6 space-y-2">
                    <li>Alle frivillige bidrag over 200,- er fradragsberettigede op til 19.000,- pr. person pr. år (2025)</li>
                    <li>Du får automatisk dit fradrag ved oplyst CPR/CVR-nummer</li>
                    <li>For hver 100,- kr. du donerer, får du ca. 26,- kr. fra Skat</li>
                    <li>Vi indberetter til skat én gang om året (januar)</li>
                </ul>
            </section>

            <div class="grid md:grid-cols-2 gap-6 mb-8">
                <div class="border rounded-lg p-6">
                    <h3 class="text-lg font-bold mb-4">Løbende støtte</h3>
                    <ul class="space-y-2 mb-4">
                        <li>Trækkes over betalingsservice</li>
                        <li>Automatisk indberetning til Skat</li>
                        <li>Betal med Dankort, Mastercard, Visa eller MobilePay</li>
                    </ul>
                    <a href="#" class="block text-center bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg">Start løbende støtte</a>
                </div>

                <div class="border rounded-lg p-6">
                    <h3 class="text-lg font-bold mb-4">Engangsstøtte</h3>
                    <ul class="space-y-2 mb-4">
                        <li>Støt med et enkelt beløb</li>
                        <li>Felter markeret med * skal udfyldes</li>
                        <li>Betal med Dankort, Mastercard, Visa eller MobilePay</li>
                    </ul>
                    <a href="#" class="block text-center bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg">Giv engangsstøtte</a>
                </div>
            </div>

            <!-- Donation form starts here -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?regnskab=<?php echo $regnskab; ?>" method="post" class="border rounded-lg p-6">
                <h2 class="text-xl font-bold mb-4">Udfyld personlige oplysninger</h2>
                <p class="font-bold mb-4">
                    <?php echo $metode == 'PBS' ? 'Tilmelding til fast støtte via betalingsservice' : 'Tilmelding til støtte en gang'; ?>
                </p>

                <div class="grid md:grid-cols-2 gap-4 mb-4">
                    <div class="col-span-2 md:col-span-1">
                        <label for="belob" class="block mb-1">Beløb: *</label>
                        <div class="flex items-center">
                            <input type="text" id="belob" name="belob" value="<?php echo $belob; ?>" class="border rounded px-3 py-2 w-32">
                            <span class="ml-2 text-gray-500">Kr. (min. 50 kr.)</span>
                        </div>
                    </div>

                    <?php if ($metode != 'kort'): ?>
                    <div class="col-span-2 md:col-span-1">
                        <label class="block mb-1">Hvor tit vil du støtte: *</label>
                        <div class="flex space-x-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="interval" value="maaned" <?php echo $interval == 'maaned' ? 'checked' : ''; ?> class="mr-1">
                                <span>måned</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="interval" value="kvartal" <?php echo $interval == 'kvartal' ? 'checked' : ''; ?> class="mr-1">
                                <span>kvartal</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="interval" value="aar" <?php echo $interval == 'aar' ? 'checked' : ''; ?> class="mr-1">
                                <span>år</span>
                            </label>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div class="col-span-2">
                        <label for="vare_id" class="block mb-1">Hvilket projekt vil du støtte</label>
                        <select id="vare_id" name="vare_id" class="border rounded px-3 py-2 w-full">
                            <option value="1">Rent vand til Afrika</option>
                            <option value="2">Skolebøger til børn i Asien</option>
                            <option value="3">Nødhjælp til katastroferamte områder</option>
                            <option value="4">Generel støtte til Rotary Danmarks Hjælpefond</option>
                        </select>
                    </div>
                </div>

                <?php if ($metode != 'kort'): ?>
                <h3 class="font-bold mb-4 mt-6">Bankoplysninger</h3>
                <div class="grid md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="bank_navn" class="block mb-1">Pengeinstitut: *</label>
                        <input type="text" id="bank_navn" name="bank_navn" class="border rounded px-3 py-2 w-full">
                    </div>
                    <div>
                        <label for="bank_reg" class="block mb-1">Reg. nr.: *</label>
                        <input type="text" id="bank_reg" name="bank_reg" class="border rounded px-3 py-2 w-full">
                    </div>
                    <div>
                        <label for="bank_konto" class="block mb-1">Konto nr.: *</label>
                        <input type="text" id="bank_konto" name="bank_konto" class="border rounded px-3 py-2 w-full">
                    </div>
                </div>
                <?php endif; ?>

                <h3 class="font-bold mb-4 mt-6">Person -/firmaoplysninger</h3>
                <div class="grid md:grid-cols-2 gap-4">
                    <div>
                        <label for="kontakt" class="block mb-1">Fulde navn: (Kontakt v. firma) *</label>
                        <input type="text" id="kontakt" name="kontakt" class="border rounded px-3 py-2 w-full">
                    </div>
                    <div>
                        <label for="cvrnr" class="block mb-1">CPR/CVR nummer:</label>
                        <input type="text" id="cvrnr" name="cvrnr" class="border rounded px-3 py-2 w-full">
                    </div>
                    <div>
                        <label for="firmanavn" class="block mb-1">Firmanavn:</label>
                        <input type="text" id="firmanavn" name="firmanavn" class="border rounded px-3 py-2 w-full">
                    </div>
                    <div>
                        <label for="addr1" class="block mb-1">Adresse: *</label>
                        <input type="text" id="addr1" name="addr1" class="border rounded px-3 py-2 w-full">
                    </div>
                    <div>
                        <label for="addr2" class="block mb-1">Adresse 2:</label>
                        <input type="text" id="addr2" name="addr2" class="border rounded px-3 py-2 w-full">
                    </div>
                    <div>
                        <label for="postnr" class="block mb-1">Post nr.: *</label>
                        <input type="text" id="postnr" name="postnr" class="border rounded px-3 py-2 w-full">
                    </div>
                    <div>
                        <label for="bynavn" class="block mb-1">By: *</label>
                        <input type="text" id="bynavn" name="bynavn" class="border rounded px-3 py-2 w-full">
                    </div>
                    <div>
                        <label for="email" class="block mb-1">Email: *</label>
                        <input type="email" id="email" name="email" class="border rounded px-3 py-2 w-full">
                    </div>
                    <div>
                        <label for="tlf" class="block mb-1">Tlf:</label>
                        <input type="text" id="tlf" name="tlf" class="border rounded px-3 py-2 w-full">
                    </div>
                </div>

                <div class="mt-6">
                    <button type="submit" name="tilmeld" value="<?php echo $metode != 'kort' ? 'Tilmeld betalingsservice' : 'Gå til kortbetaling'; ?>" class="bg-blue-600 hover:bg-blue-700 text-white py-3 px-6 rounded-lg w-full">
                        <?php echo $metode != 'kort' ? 'Tilmeld betalingsservice' : 'Gå til kortbetaling'; ?>
                    </button>
                </div>
            </form>

            <div class="mt-8 bg-yellow-50 border-l-4 border-yellow-500 p-4">
                <h3 class="font-bold mb-2">Alternative betalingsmuligheder</h3>
                <p class="mb-2">Kontonr.: 3574 10845963 (Danske Bank)</p>
                <p class="mb-2">MobilePay: 34 101</p>
                <p class="text-gray-600">Bemærk: Ved direkte bankoverførsel eller MobilePay kan der ikke ydes skattefradrag</p>
            </div>

            <div class="mt-4 bg-gray-100 p-4 text-gray-600">
                <p>BEMÆRK: Din PC/MAC skal acceptere cookies for at betalingen kan gå igennem.</p>
            </div>
        </div>
    </main>

    <footer class="bg-blue-900 text-white py-8 mt-8">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div>
                    <h2 class="text-xl font-bold mb-2">Rotary Danmarks Hjælpefond</h2>
                    <p>Sammen gør vi en forskel</p>
                </div>
                <div class="mt-4 md:mt-0 text-right">
                    <p>© 2025 Rotary Danmarks Hjælpefond</p>
                    <p>Alle rettigheder forbeholdes</p>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>