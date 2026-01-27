<!DOCTYPE html>
<html lang="da">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ansøgninger 2024-2025 - Rotary Danmarks Hjælpefond</title>
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
                <span class="text-gray-900">Ansøgninger 2024-2025</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <main class="max-w-7xl mx-auto px-4 py-12">
        <div class="bg-white rounded-lg shadow-lg p-8">
            <h1 class="text-3xl font-bold text-gray-900 mb-8">Ansøgninger 2024-2025</h1>

            <!-- Summary Box -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-blue-50 rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-blue-900 mb-2">Modtaget ansøgninger for</h3>
                    <p class="text-2xl font-bold text-blue-700">kr. 934.265,00</p>
                </div>
                <div class="bg-green-50 rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-green-900 mb-2">Bevilget</h3>
                    <p class="text-2xl font-bold text-green-700">kr. 740.415,00</p>
                </div>
                <div class="bg-gray-50 rounded-lg p-6">
                    <h3 class="text-sm font-semibold text-gray-900 mb-2">Rest til projekter 2023-24</h3>
                    <p class="text-2xl font-bold text-gray-700">kr. 465.735,00</p>
                </div>
            </div>

            <!-- Applications Table -->
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Reference</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Projekt</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Lokation</th>
                            <th class="px-4 py-3 text-right text-sm font-semibold text-gray-900">Beløb</th>
                            <th class="px-4 py-3 text-left text-sm font-semibold text-gray-900">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php
                        $entries = [
                            ['RDH 01-2024-25', 'Kondomeriet', 'Holstebro', '15.870,00'],
                            ['RDH 02-2024-25', 'Nye modtager lokaliteter og opfriskning af de nuværende hos Headspace Aabenraa', 'Aabenrå', '40.000,00'],
                            ['RDH 03-2024-25', 'Tornsbjerggård Bofællesskab for udviklingshæmmede', 'Odder', '5.000,00'],
                            ['RDH 04-2024-25', 'Telt til KFUM og KFUK i Brædstrup', 'Brædstrup', '4.500,00'],
                            ['RDH -05-2024-25', 'Bålhytte i Brejning', 'Vejle Nord', 'Lukket'],
                            ['RDH -06-2024-25', 'Bålhytte i Brejning', 'Vejle Nord', '15.000,00'],
                            ['RDH 07-.2024-25', 'Forårskoncert', 'Humlebæk Nivå', 'Se RDH 13 2024-2025'],
                            ['RDH 08-2024-25', 'Samtalerum Kirkens Korshær', 'Horsens Vestre', '40.000,00'],
                            ['RDH 09-2024-25', 'Kulturdag Ukr.flygtninge', 'Horsens Vitus Bering', '15.000,00'],
                            ['RDH 10-2024-25', 'Mobile spejle til Give GF', 'Give', '18.750,00'],
                            ['RDH 11-2024-25', 'Panna House 2024', 'København Rotaract', '25.000,00'],
                            ['RDH 12-2024-25', 'TEST', 'TEST', '-'],
                            ['RDH 13-2024-25', 'Øresund Håndbold Klub', 'Humlebæk Nivå', '-'],
                            ['RDH 14-2024-25', '', 'Langeland', '-'],
                            ['RDH 15-2024-25', '', 'Langeland', '-'],
                            ['RDH 16-2024-25', '', 'Langeland', '-'],
                            ['RDH 17-2024-25', '45 PC til skoler i Ghana', 'Langeland', '15.000,00'],
                            ['RDH 18-2024-25', 'Etablerinbg af vandtapning', 'Svendborg', '10.420,00'],
                            ['RDH 19-2024-25', 'Støtte til Palliativ Team', 'Odense Carolinekilde', '25.000,00'],
                            ['RDH 20-2024-25', 'Ribe Drengekor', 'Ribe', '7.500,00'],
                            ['test', '', '0', '-'],
                            ['RDH 21-2024-25', 'Børnelejr for udsatte børn', 'Odder', '6.500,00'],
                            ['RDH 22 2024-25', 'Tilskud til kystsikrinbg', 'Præstø', '10.000,00'],
                            ['RDH 23 2024-25', 'Undervsningsbygning inGhana', 'Thomas .o., Nielsen', '40.000,00'],
                            ['RDH 24 2023-25', 'Støtte til boksering', 'Odder', '5.000,00'],
                            ['RDH 25 2024-25', 'Julehjælp', 'Nørre Alslev', '5.000,00'],
                            ['RDH 26 2024-25', 'Støtte tilk mKørestols ladcykel', 'Rødekro', '20.000,00'],
                            ['RDH 27 2024-25', 'Veteranprojekt 2024-25', 'Haderslev Hertug Hans', '25.000,00'],
                            ['RDH 28-2024-25', 'Sanserum mtrilGeorgien', 'Stege', '23.000,00'],
                            ['20.000', 'Children home i Kenya', 'Pandrup', '20.000,00'],
                            ['RDH 30 2024-25', 'Køb af el,klaver', 'Ringkøbing', '6.000,00'],
                            ['RDH 31 2025-25', 'Køb baf cykeltøj til unge', 'Herning City', '18.000,00'],
                            ['RDH 32 2024-25', 'Støtte til Veteranhuset i Holstebro', 'Holstebro', '19.000,00'],
                            ['RDH 33 2024-25', 'Støtte til udsatte familier', 'Holstebro', '3.000,00'],
                            ['RDH 34 2024-2025', 'SolcelleanlægSri Lanka', 'Rønde', '40.000,00'],
                            ['RDH 35 2024-25', 'Boccia anlæg i Litauen', 'Dianalund Stenlille', '26.250,00'],
                            ['RDH 36 - 2024-25', 'Indretrning af Underv.lokale', 'Ebeltoft', '5.000,00'],
                            ['RDH 37 -2024-25', 'Indretrning af Underv.lokale', 'E-club', '10.000,00'],
                            ['RDH 38 - 2024-25', 'Børns trivsel i friv.center', 'Odder', '8.000,00'],
                            ['RDH 39-2024-25', 'Julehjælp', 'Odder', '5.000,00'],
                            ['RDH 40-2024-25', 'Julehjælp', 'Viborg-Asmild', '5.000,00'],
                            ['RDH 41 -2024-25', 'Støtte til int. RYLA', 'Roskilde Østre', '-'],
                            ['RDH 42 2024-25', 'Julehjælp', 'Amager Strand', '5.000,00'],
                            ['RDH 43 2024-25', 'Julehjælp', 'Christianhavn-Slotsholmen', '-'],
                            ['RDH 44-2024-25', 'Støtte til Børne telefonen', 'Nykøbing Falster', '-'],
                            ['RDH 45 -2024-25', 'Støtte til Børneterlefonern', 'Nykøbing Falster', '-'],
                            ['RDH 46 2024-25', 'Støtte til Ladestander', 'Videbæk', '-'],
                            ['RDH 47 2024-25', 'Julehjælp', 'Skælskør', '5.000,00'],
                            ['RDH 48 2024-25', 'Julehjælp', 'Haslev', '5.000,00'],
                            ['RDH 49 2024-25', 'Ovne til Ukraine', 'Aalborg-Nørresundby', '40.000,00'],
                            ['RDH 50 2024-25', 'Lægebil til Uganda', 'Ry RK', '40.000,00'],
                            ['RDH 51 2024-25', 'Fragt til Ukraine', 'Hørsholm RK', '14.000,00'],
                            ['RDH 52 2024-25', 'Julehjælp', 'Hjortespring', '5.000,00'],
                            ['RDH 53 2024-25', 'Foreningsliv ukrainere', 'Roskilde Østre', '-'],
                            ['RDH 54 2024-25', 'Forfatterkursus for unge', 'Forfatterkursus', '30.000,00'],
                            ['RDH 55 2024-25', 'Shelter og bålplads', 'Maribo-Rødby RK', '12.500,00'],
                            ['RDH 56 2024-25', 'Skatebord udstyr', 'Juelsminde RK', '5.000,00'],
                            ['RDH 57 2024-25', 'Julehjælp', 'Gentofte RK', '5.000,00'],
                            ['RDH 58 2024-25', 'Det store julemandsløb', 'Dyrehaven RK', '-'],
                            ['RDH 59 2024-25', 'Flygtninge i Uganda', 'Viborg-Asmild RK', '11.500,00'],
                            ['RDH 60 2024-25', 'Foreningsliv ukrainere', 'Roskilde Østre RK', '7.500,00'],
                            ['RDH 61 2024-25', 'Headspace Roskilde', 'Roskilde Syd RK', '-'],
                            ['RDH 62 2024-25', 'Computerudstyr Ghana', 'Københavns RK', '40.000,00'],
                            ['RDH 63 2024-25', 'Vandprojekt Tanzania', 'Bjerringbro RK', '10.000,00'],
                            ['RDH 64 2024-25', 'Julehjælp', 'Lemvig RK', '5.000,00'],
                            ['RDH 65 2024-25', 'Bålsted', 'Brædstrup RK', '-'],
                            ['RDH 66 2024-25', 'Bålsted spejdere', 'Brædstrup RK', '10.625,00'],
                            ['RDH 68 2024-25', 'Nye måtter judoklub', 'Køge Nord', '-'],
                            ['#REFERENCE!', 'Øjenoperationer i Nigeria', 'Aarhus RK', '-'],
                            ['RDH 69 2024-25', 'Biler til Ukraine', 'Aarhus RK', '-'],
                            ['RDH 70 2024-25', 'Natkameraer', 'Aarhus RK', '-'],
                            ['RDH 71 2024-25', 'Short term lejr', 'Slagelse-Antvorskov', '-']
                        ];

                        foreach ($entries as $entry) {
                            echo "<tr class='hover:bg-gray-50'>
                                <td class='px-4 py-4 text-sm font-medium text-gray-900'>{$entry[0]}</td>
                                <td class='px-4 py-4 text-sm text-gray-900'>{$entry[1]}</td>
                                <td class='px-4 py-4 text-sm text-gray-500'>{$entry[2]}</td>
                                <td class='px-4 py-4 text-sm text-right text-gray-900'>" . 
                                (is_numeric(str_replace([',', '.'], '', $entry[3])) ? "kr. {$entry[3]}" : $entry[3]) . 
                                "</td>
                                <td class='px-4 py-4 text-sm text-green-600'>Aktiv</td>
                            </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
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