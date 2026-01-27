<?php
// -------- shop/pbs_tilmelding.php----------lap 3.8.4 ----- 2019.10.10----------
// LICENS
//
// Dette program er fri software. Du kan gendistribuere det og / eller
// modificere det under betingelserne i GNU General Public License (GPL)
// som er udgivet af "The Free Software Foundation", enten i version 2
// af denne licens eller en senere version, efter eget valg.
// Fra og med version 3.2.2 dog under iagttagelse af følgende:
// 
// Programmet må ikke uden forudgående skriftlig aftale anvendes
// i konkurrence med saldi.dk ApS eller anden rettighedshaver til programmet.
//
// Dette program er udgivet med haab om at det vil vaere til gavn,
// men UDEN NOGEN FORM FOR REKLAMATIONSRET ELLER GARANTI. Se
// GNU General Public Licensen for flere detaljer.
//
// En dansk oversaettelse af licensen kan laeses her:
// http://www.saldi.dk/dok/GNU_GPL_v2.html
//
// Copyright (c) 2003-2019 saldi.dk ApS
// ----------------------------------------------------------------------

@session_start();
$s_id=session_id();
$vareId=NULL;

include("../includes/connect.php");
include("../includes/std_func.php");

$regnskab=if_isset($_GET['regnskab']);

$svar=logon($s_id,$regnskab,$brugernavn,$password,$sqhost,$squser,$sqpass,$sqdb);

print "<!DOCTYPE html>
<html lang=\"da\">
<head>
    <meta http-equiv=\"content-type\" content=\"text/html; charset=utf-8\" />
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>PBS tilmelding - Rotary Danmarks Hjælpefond</title>
    <script src=\"https://cdn.tailwindcss.com\"></script>
</head>";

if ($tilmeld=(if_isset($_POST['tilmeld']))) {

    include("../includes/ordrefunc.php");

    $belob=db_escape_string(trim(if_isset($_POST['belob'])));
    $vareId=db_escape_string(trim(if_isset($_POST['vareId'])));
    $interval=db_escape_string(trim(if_isset($_POST['interval'])));
    $bank_navn=db_escape_string(trim(if_isset($_POST['bank_navn'])));
    $bank_reg=db_escape_string(trim(if_isset($_POST['bank_reg'])));
    $bank_konto=db_escape_string(trim(if_isset($_POST['bank_konto'])));
    $kontakt=db_escape_string(trim(if_isset($_POST['kontakt'])));
    $cvrnr=db_escape_string(trim(if_isset($_POST['cvrnr'])));
    $firmanavn=db_escape_string(trim(if_isset($_POST['firmanavn'])));
    $addr1=db_escape_string(trim(if_isset($_POST['addr1'])));
    $addr2=db_escape_string(trim(if_isset($_POST['addr2'])));
    $postnr=db_escape_string(trim(if_isset($_POST['postnr'])));
    $bynavn=db_escape_string(trim(if_isset($_POST['bynavn'])));
    $email=db_escape_string(trim(if_isset($_POST['email'])));
    $tlf=db_escape_string(trim(if_isset($_POST['tlf'])));

    $cvrnr=str_replace("-","",$cvrnr);
    $cvrnr=str_replace(" ","",$cvrnr);
    $alert=tjek($belob,$bank_navn,$bank_reg,$bank_konto,$kontakt,$cvrnr,$firmanavn,$addr1,$postnr,$bynavn,$email,$tlf,$vareId);
    if ($alert=='OK') {
        $kontonr=1000;
        $x=0;
        $ktonr=array();
        $q=db_select("select * from adresser where art='D' and kontonr >='1000' order by kontonr",__FILE__ . " linje " . __LINE__);
        while($r=db_fetch_array($q)) {
            $ktonr[$x]=$r['kontonr'];
            $x++;
        }
        while (in_array($kontonr,$ktonr)){
            $kontonr++;
        }
        $gruppe=1;
        if (!$firmanavn) {
            $firmanavn=$kontakt;
            $kontakt=NULL;
            $kontotype='privat';
        } else {
            $kontotype='erhverv';
        }
        $art='D';
        $qtxt="insert into adresser(kontonr,firmanavn,addr1,addr2,postnr,bynavn,email,cvrnr,tlf,kontakt,gruppe,kontotype,art,bank_navn,bank_reg,bank_konto,pbs,pbs_nr) values ('$kontonr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$email','$cvrnr','$tlf','$kontakt','$gruppe','$kontotype','$art','$bank_navn','$bank_reg','$bank_konto','on','')";
        db_modify($qtxt,__FILE__ . " linje " . __LINE__);
        $qtxt="select id from adresser where kontonr='$kontonr' and art = 'D'";
        $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
        $konto_id=$r['id'];
        if ($konto_id) {
            if ($kontakt) {
                $qtxt="insert into ansatte(konto_id, navn) values ('$konto_id', '$kontakt')";
                db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
            }
            $qtxt="select max(ordrenr) as ordrenr from ordrer where art='DO'";
            $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
            $ordrenr=$r['ordrenr']+1;
            $ordredate=date("Y-m-d");
            $status=2;
            $art='DO';
            $qtxt="insert into ordrer(konto_id,kontonr,ordrenr,firmanavn,addr1,addr2,postnr,bynavn,email,kontakt,art,status,udskriv_til,ordredate,cvrnr) values ('$konto_id','$kontonr','$ordrenr','$firmanavn','$addr1','$addr2','$postnr','$bynavn','$email','$kontakt','$art','$status','PBS','$ordredate','$cvrnr')";
            db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
            $r=db_fetch_array(db_select("select max(id) as id from ordrer where konto_id='$konto_id' and art = '$art'",__FILE__ . " linje " . __LINE__));
            $ordre_id=$r['id'];
            $txt="Tilmeldt PBS, betalingsinterval: $interval, beløb: $belob";
            $txt=db_escape_string($txt);
            $qtxt="insert into ordrelinjer(ordre_id,beskrivelse,posnr) values ('$ordre_id','$txt','1')";
            db_modify($qtxt,__FILE__ . " linje " . __LINE__); 
            if ($vareId) {
                $amount=usdecimal($belob);
                $qtxt="select * from varer where id = '$vareId'";
                $r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__));
                opret_ordrelinje($ordre_id,$vareId,$r['varenr'],1,$r['beskrivelse'],$amount,'0',100,'DO',$r['momsfri'],'2','0','0','','','');
            }
        } 
        $txt="Tak for din tilmelding";
        print "<body class=\"bg-gray-50\">";
        print "<div class=\"max-w-7xl mx-auto px-4 py-12 sm:px-6 lg:px-8\">";
        print "<div class=\"bg-green-100 border-l-4 border-green-500 p-4 mb-6\">";
        print "<p class=\"text-green-700\">$txt</p>";
        print "</div>";
        print "<a href=\"javascript:history.back()\" class=\"text-blue-600 hover:text-blue-800\">Tilbage til forsiden</a>";
        print "</div></body>";
        exit;
    } else {
        print "<body class=\"bg-gray-50\" onLoad=\"javascript:alert('$alert')\">";
        $alert=NULL;
    }
}
?>

<body class="bg-gray-50">
    <!-- Header -->
    <header class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4 flex justify-between items-center">
            <a href="/" class="text-2xl font-bold text-blue-700">Rotary Danmarks Hjælpefond</a>
            <img src="/path/to/rotary-logo.png" alt="Rotary Logo" class="h-10">
        </div>
    </header>

    <!-- Navigation -->
    <nav class="bg-white">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex space-x-8 py-2">
                <a href="/" class="text-gray-600 hover:text-blue-700">Forside</a>
                <span class="text-gray-600">›</span>
                <a href="#" class="text-gray-600 hover:text-blue-700">Hjælp os – Støt os</a>
            </div>
        </div>
    </nav>

    <!-- Main content -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <h1 class="text-3xl font-bold mb-8">Tilmelding til Betalingsservice</h1>

        <?php if (!$alert): ?>
        <!-- Info Box -->
        <div class="border-l-4 border-blue-600 bg-blue-50 p-4 mb-8">
            <p class="text-gray-700">
                Hjælpefonden støtter de mange fremragende klubprojekter i ind- og udland, men må normalt stoppe støtten inden Rotaryåret udløber. Kassen er tom flere måneder før, så vi håber, at I vil hjælpe os med et bidrag, således at vi kan fortsætte og intensivere vores støtte.
            </p>
        </div>

        <!-- Form -->
        <div class="bg-white shadow-md rounded-md p-6 mb-8">
            <form name="pbs_tilmelding" action="pbs_tilmelding.php?regnskab=<?php echo $regnskab; ?>" method="post">
                <div class="mb-6">
                    <h2 class="text-xl font-semibold mb-4">Udfyld personlige oplysninger</h2>
                    <p class="mb-4 font-medium">Tilmelding til fast støtte via betalingsservice</p>
                    
                    <div class="mb-4">
                        <label class="block mb-2 font-medium" for="belob">Beløb: *</label>
                        <div class="flex items-center">
                            <input class="border rounded-md p-2 w-32" type="text" id="belob" name="belob" value="<?php echo $belob; ?>">
                            <span class="ml-2 text-gray-500">Kr. (min. 200 kr.)</span>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block mb-2 font-medium">Hvor tit vil du støtte: *</label>
                        <div class="flex space-x-4">
                            <label class="inline-flex items-center">
                                <input type="radio" name="interval" value="maaned" <?php echo $interval=='maaned'?'checked':''; ?> class="mr-2">
                                <span>Måned</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="interval" value="kvartal" <?php echo (!$interval || $interval=='kvartal')?'checked':''; ?> class="mr-2">
                                <span>Kvartal</span>
                            </label>
                            <label class="inline-flex items-center">
                                <input type="radio" name="interval" value="aar" <?php echo $interval=='aar'?'checked':''; ?> class="mr-2">
                                <span>År</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block mb-2 font-medium" for="vareId">Hvilket projekt vil du støtte:</label>
                        <select class="border rounded-md p-2 w-full" id="vareId" name="vareId">
                            <?php
                            $x=0;
                            $vare_id=array();
                            $qtxt="select id,beskrivelse from varer where publiceret='on' and lukket !='on' order by beskrivelse";
                            $q=db_select($qtxt,__FILE__ . " linje " . __LINE__);
                            while ($r=db_fetch_array($q)) {
                                $vare_id[$x]=$r['id'];
                                $beskrivelse[$x]=$r['beskrivelse'];
                                $x++;
                            }
                            
                            for ($x=0;$x<count($vare_id);$x++) {
                                if ($vare_id[$x]==$vareId) 
                                    echo "<option value=\"$vare_id[$x]\" selected>$beskrivelse[$x]</option>\n";
                            }
                            for ($x=0;$x<count($vare_id);$x++) {
                                if ($vare_id[$x]!=$vareId) 
                                    echo "<option value=\"$vare_id[$x]\">$beskrivelse[$x]</option>\n";
                            }
                            ?>
                        </select>
                    </div>
                </div>
                
                <div class="mb-6">
                    <h2 class="text-xl font-semibold mb-4">Bankoplysninger</h2>
                    
                    <div class="mb-4">
                        <label class="block mb-2 font-medium" for="bank_navn">Pengeinstitut: *</label>
                        <input class="border rounded-md p-2 w-full" type="text" id="bank_navn" name="bank_navn" value="<?php echo $bank_navn; ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block mb-2 font-medium" for="bank_reg">Reg. nr.: *</label>
                        <input class="border rounded-md p-2 w-full" type="text" id="bank_reg" name="bank_reg" value="<?php echo $bank_reg; ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block mb-2 font-medium" for="bank_konto">Konto nr.: *</label>
                        <input class="border rounded-md p-2 w-full" type="text" id="bank_konto" name="bank_konto" value="<?php echo $bank_konto; ?>">
                    </div>
                </div>
                
                <div class="mb-6">
                    <h2 class="text-xl font-semibold mb-4">Person -/firmaoplysninger</h2>
                    
                    <div class="mb-4">
                        <label class="block mb-2 font-medium" for="kontakt">Fulde navn: (Kontakt v. firma) *</label>
                        <input class="border rounded-md p-2 w-full" type="text" id="kontakt" name="kontakt" value="<?php echo $kontakt; ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block mb-2 font-medium" for="cvrnr">CPR/CVR nummer: * +++</label>
                        <input class="border rounded-md p-2 w-full" type="text" id="cvrnr" name="cvrnr" value="<?php echo $cvrnr; ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block mb-2 font-medium" for="firmanavn">Firmanavn:</label>
                        <input class="border rounded-md p-2 w-full" type="text" id="firmanavn" name="firmanavn" value="<?php echo $firmanavn; ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block mb-2 font-medium" for="addr1">Adresse: *</label>
                        <input class="border rounded-md p-2 w-full" type="text" id="addr1" name="addr1" value="<?php echo $addr1; ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block mb-2 font-medium" for="addr2">Adresse 2:</label>
                        <input class="border rounded-md p-2 w-full" type="text" id="addr2" name="addr2" value="<?php echo $addr2; ?>">
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="mb-4">
                            <label class="block mb-2 font-medium" for="postnr">Post nr.: *</label>
                            <input class="border rounded-md p-2 w-full" type="text" id="postnr" name="postnr" value="<?php echo $postnr; ?>">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block mb-2 font-medium" for="bynavn">By: *</label>
                            <input class="border rounded-md p-2 w-full" type="text" id="bynavn" name="bynavn" value="<?php echo $bynavn; ?>">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block mb-2 font-medium" for="email">Email: *</label>
                        <input class="border rounded-md p-2 w-full" type="text" id="email" name="email" value="<?php echo $email; ?>">
                    </div>
                    
                    <div class="mb-4">
                        <label class="block mb-2 font-medium" for="tlf">Tlf:</label>
                        <input class="border rounded-md p-2 w-full" type="text" id="tlf" name="tlf" value="<?php echo $tlf; ?>">
                    </div>
                </div>
                
                <div class="mt-6">
                    <input class="w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-md cursor-pointer" type="submit" name="tilmeld" value="Tilmeld betalingsservice"/>
                </div>
            </form>
        </div>
        
        <!-- Tax benefits info -->
        <div class="mb-8">
            <h2 class="text-xl font-semibold mb-4">Skattefradrag</h2>
            <ul class="list-disc pl-5 space-y-2">
                <li>Alle frivillige bidrag over 200,- er fradragsberettigede op til 19.000,- pr. person pr. år (2025)</li>
                <li>Du får automatisk dit fradrag ved oplyst CPR/CVR-nummer</li>
                <li>For hver 100,- kr. du donerer, får du ca. 26,- kr. fra Skat</li>
                <li>Vi indberetter til skat én gang om året (januar)</li>
            </ul>
        </div>
        
        <!-- Payment options grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <!-- Recurring payment -->
            <div class="border rounded-md p-6 bg-white">
                <h2 class="text-lg font-semibold mb-4">Løbende støtte</h2>
                <p class="mb-2">Trækkes over betalingsservice</p>
                <p class="mb-2">Automatisk indberetning til Skat</p>
                <p class="mb-4">Betal med Dankort, Mastercard, Visa eller MobilePay</p>
                <a href="#" class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center font-bold py-3 px-4 rounded-md">Start løbende støtte</a>
            </div>
            
            <!-- One-time payment -->
            <div class="border rounded-md p-6 bg-white">
                <h2 class="text-lg font-semibold mb-4">Engangsstøtte</h2>
                <p class="mb-2">Støt med et enkelt beløb</p>
                <p class="mb-2">Felter markeret med * skal udfyldes</p>
                <p class="mb-4">Betal med Dankort, Mastercard, Visa eller MobilePay</p>
                <a href="#" class="block w-full bg-blue-600 hover:bg-blue-700 text-white text-center font-bold py-3 px-4 rounded-md">Giv engangsstøtte</a>
            </div>
        </div>
        
        <!-- Alternative payment methods -->
        <div class="border-l-4 border-yellow-500 bg-yellow-50 p-4 mb-8">
            <h2 class="font-semibold mb-2">Alternative betalingsmuligheder</h2>
            <p class="mb-2">Kontonr.: 3574 10845963 (Danske Bank)</p>
            <p class="mb-2">MobilePay: 34 101</p>
            <p class="text-sm text-gray-600">Bemærk: Ved direkte bankoverførsel eller MobilePay kan der ikke ydes skattefradrag</p>
        </div>
        
        <!-- Note -->
        <div class="bg-gray-100 p-4 mb-8 text-sm">
            <p>BEMÆRK: Din PC/MAC skal acceptere cookies for at betalingen kan gå igennem.</p>
        </div>
        
        <p class="text-gray-600">Ovenstående oplysninger anvendes til registrering hos Skat og Nets</p>
        <?php endif; ?>
    </main>
    
    <!-- Footer -->
    <footer class="bg-blue-700 text-white py-8">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col md:flex-row justify-between">
            <div class="mb-4 md:mb-0">
                <h2 class="text-xl font-bold">Rotary Danmarks Hjælpefond</h2>
                <p class="mt-2">Sammen gør vi en forskel</p>
            </div>
            <div class="text-sm">
                <p>© 2025 Rotary Danmarks Hjælpefond</p>
                <p>Alle rettigheder forbeholdes</p>
            </div>
        </div>
    </footer>
</body>
</html>

<?php
function logon($s_id,$regnskab,$brugernavn,$password,$sqhost,$squser,$sqpass,$sqdb) {
    $password=md5($password);
    $unixtime=date("U");
    include("../includes/db_query.php");
    include ("../includes/connect.php");
    $qtxt="select * from regnskab where regnskab = '". db_escape_string($regnskab) ."'";
    if ($r=db_fetch_array(db_select($qtxt,__FILE__ . " linje " . __LINE__))) {
        if ($db = trim($r['db'])) {
            $connection = db_connect ($sqhost,$squser,$sqpass,$db);
            if ($connection) {
                $r=db_fetch_array(db_select("select id from brugere where brugernavn='PBS_TILMELDING'",__FILE__ . " linje " . __LINE__));
                if ($r['id']) {
                    $return='0'.chr(9).$s_id;
                } else {
                    $return="1".chr(9)."Username or password error";
                }    
            } else $return="1".chr(9)."Connection to database failed";
        } else $return="1".chr(9)."Unknown financial report";
    } else return $return="1".chr(9)."Unknown financial report";
    return ($return);
}

function tjek($belob,$bank_navn,$bank_reg,$bank_konto,$kontakt,$cvrnr,$firmanavn,$addr1,$postnr,$bynavn,$email,$tlf) {
    $alert='OK';
    if (!$belob || $belob<200) $alert="Beløb skal være min. 200,-"; 
    if (!$bank_navn) $alert="Pengeinstitut navn ikke angivet";
    if (!$bank_reg) $alert="Reg nr. navn ikke angivet";
    if (!$bank_konto) $alert="Konto nr. navn ikke angivet";
    if (!$kontakt && $firmanavn) $alert="Kontakt ikke angivet";
    elseif (!$kontakt) $alert="Navn ikke angivet";
    if (!$cvrnr && $firmanavn) $alert="Cvr nr. ikke angivet";
    if (strlen($cvrnr)!='10') {
        if ($firmanavn) $alert="Cvr nr. skal bestå af 10 cifre";
        else $alert="Cpr nr. skal bestå af 10 cifre";
    }
    elseif (!$cvrnr) $alert="Cpr nr. ikke angivet";
    if (!$addr1) $alert="Adresse ikke angivet"; 
    if (!$postnr) $alert="Postnr ikke angivet"; 
    if (!$bynavn) $alert="By ikke angivet"; 
    if (!$email) $alert="email ikke angivet"; 
    return ("$alert");
}
?>