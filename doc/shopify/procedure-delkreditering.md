# Delkreditering og refundering: afstemning i Saldi

**Havemøbelland · Procedure for bogholderiet**
**Status:** Udkast til godkendelse hos Havemøbellands bogholder
**Version:** udkast 0.1, oktober 2026 · Udarbejdet af Danosoft

> **Om udkastet.** Proceduren beskriver, hvordan I afstemmer delvise kreditnotaer mod kundens åbne poster og mod refunderinger, både i Shopify og i butikken. Kassekladde, "Udlign åbne poster" og rapporterne er Saldi, som I kender det i dag. Delen om Shopify og de nye valg på kreditnotaen beskriver funktioner, der er ved at blive bygget efter det aftalte design. De endelige detaljer, fx feltnavne, notens ordlyd og navnene på tags, bekræfter vi i overleveringstesten. Konti, der ikke er valgt endnu, står som [mellemregningskonto, aftales med jer]. Alle numre i eksemplerne er fiktive.

## 1. To slags delkreditering

| | Dekort | Antalskreditering |
|---|---|---|
| Hvad krediteres | Et beløb eller en procentdel af én linje. Kunden beholder varen. | Et antal styk af en linje, fx 1 af 2 stole. |
| Lager | Ingen lagerbevægelse. | I vælger pr. linje, om varen lægges på lager igen (på det lager, den blev trukket fra), eller om der ikke sker nogen lagerbevægelse (fx en kasseret vare). |

- 10 % dekort betyder, at 10 % af linjebeløbet krediteres; kunden ender altså med at betale 90 %. Momsen krediteres med fakturaens sats: 10 % af 1.250,00 kr. = 125,00 kr. = 100,00 kr. + 25,00 kr. moms.
- Der kan laves flere delkrediteringer på samme faktura. Saldi holder styr på, hvad der er krediteret, så der samlet ikke krediteres mere, end der er faktureret.

**Nyt i forhold til i dag.** I Saldi i dag giver knappen "Kredit" kun én kreditnota pr. faktura, og en krediteret lagervare kommer altid tilbage på lager, også når kun prisen skulle sættes ned.

## 2. Det ser I i Shopify

Når kreditnotaen har fået sit nummer i Saldi, eller returneringen er afsluttet i kassen, skriver Saldi på Shopify-ordren inden for fem minutter:

- **En dateret note**, som føjes til ordrenoten. Saldi tilføjer kun tekst, så kundens egen note og tidligere noter bliver stående. Fra kontoret: "Kreditnota 1002, 125 kr." Fra butikken: "Returneret i butik Ballerup: kreditnota 5004, 1.250 kr., refunderet i butik", med varenummer, antal og om returneringen er delvis eller fuld.
- **Tags**, foreløbig `saldi-krediteret`; butiksreturneringer får også `saldi-returneret-i-butik`.
- **Et link til kreditnotaen** (PDF), som kun åbner den ene kreditnota. Kreditnotaen viser Shopify-ordrenummeret (fx HML1234), kreditnotanummeret og fakturanummeret.

Refunderinger i Shopify registreres på ordren i Saldi. Mangler noten efter fem minutter, så kontakt Saldi-support, og lav ikke en ny kreditnota.

## 3. To veje til refundering, så der ikke refunderes to gange

Al kreditering sker i Saldi, og kreditnotaen laves altid, før pengene betales tilbage. Saldi flytter aldrig penge i Shopify. Kundeservice refunderer manuelt, når varen er retur og kontrolleret, eller når en dekort er aftalt.

**Vej 1: Returneret og refunderet i butikken.** Sælgeren laver returneringen i kassen med "Retur af webordre" (arbejdsnavn) og betaler kunden tilbage i butikken. Shopify-noten siger "refunderet i butik". Brug altid "Retur af webordre" til en webordre; en almindelig returnering i kassen kommer ikke på Shopify-ordren.
*Regel:* Står der "refunderet i butik", må kundeservice ikke refundere i Shopify.

**Vej 2: Kreditnota, der venter på refundering i Shopify.** Kreditnotaen laves i Saldi fra fakturaen (Debitor → Ordre → "Kredit"). Shopify-noten siger "Kreditnota <nr>, <beløb>".
*Regel:* Kundeservice refunderer præcis kreditnotaens beløb i Shopify og skriver kreditnotanummeret som begrundelse. Én kreditnota giver én refundering.

**Annullering af en webordre.** En webordre er faktureret i Saldi, så snart den kommer ind. Skal den annulleres, laves kreditnotaen for hele ordren først i Saldi (vej 2), og derefter annullerer og refunderer kundeservice i Shopify. Shopify refunderer som standard hele beløbet, når en ordre annulleres.

Shopify lægger som standard varerne på lager igen, når der refunderes eller annulleres. Fjern fluebenet ved "Restock items"; lageret styres fra Saldi.

## 4. Afstemning trin for trin

Med integrationen bogføres en betalt webordre som betalt med kundens betalingsmetode, så den ikke står som åben post. Derfor bogføres en kreditnota på en webordre på en mellemregningskonto og afstemmes dér og ikke i "Åbne poster".

| Situation | Åben post på kunden? | Her afstemmer I |
|---|---|---|
| Webordre, refunderet i Shopify (vej 2) | Nej | [mellemregningskonto, aftales med jer] mod refunderingen hos betalingsudbyderen |
| Webordre, refunderet i butikken (vej 1) | Nej | Kassen: returneringen indgår i butikkens dagsafslutning |
| Faktura med åben post, fx med betalingsfrist | Ja | "Åbne poster" og "Udlign åbne poster" |

Kreditnotaer fra Debitor → Ordre får nummer i samme serie som fakturaer. En returnering i kassen får et bonnummer fra kassesystemets egen nummerserie; det er det nummer, Shopify-noten viser som kreditnotanummer. Overskriften på en ordre ("krediteret på KN nr", "kreditering af ordre nr") viser ordrenumre, så brug kreditnotanummeret fra kreditnotaen eller Shopify-noten.

### Eksempel A: 10 % dekort, refunderet i Shopify

Webordre HML1234, faktura 1001: et havebord til 1.250,00 kr. inkl. moms, betalt med kort i Shopify. Bordet har en transportskade, I aftaler 10 % dekort, og kunden beholder bordet.

1. Find faktura 1001 under Debitor → Ordre (søg på HML1234 eller fakturanummeret), og tryk "Kredit".
2. Krediter kun bordets linje, og vælg dekort 10 %. Saldi viser 125,00 kr. (100,00 + 25,00 moms). Lageret ændres ikke.
3. Tryk "Kreditér". Viser Saldi knappen "Modtag", så tryk på den først. Kreditnota 1002 bogføres: debet salg 100,00, debet salgsmoms 25,00, kredit [mellemregningskonto] 125,00.
4. Inden for fem minutter står "Kreditnota 1002, 125 kr." med dato, tag og link på HML1234.
5. Kundeservice refunderer 125,00 kr. i Shopify med begrundelsen "Kreditnota 1002".
6. Når refunderingen ses hos betalingsudbyderen, tjekker bogholderen beløbet og laver en linje i Kassekladde: Bilagstekst "Refundering kreditnota 1002, HML1234"; D/K "F", Debet [mellemregningskonto]; D/K "F", Kredit den konto, refunderingen er betalt fra (kortkontoen eller banken); Fakturanr. 1002; Beløb 125,00; ingen moms. Tryk "Bogfør".

| Efter trin | Åbne poster på kunden | [mellemregningskonto] for kreditnota 1002 |
|---|---:|---:|
| Faktura 1001 er bogført | 0,00 | 0,00 |
| 3: kreditnota 1002 er bogført | 0,00 | 125,00 i kredit (venter på refundering) |
| 6: refunderingen er bogført | 0,00 | 0,00 |

### Eksempel B: 1 af 2 stole retur i butikken, lagt på lager, refunderet i butikken

Webordre HML1240, faktura 1003: 2 havestole à 1.250,00 kr. = 2.500,00 kr. inkl. moms, betalt i Shopify. Kunden returnerer den ene stol i butikken i Ballerup.

1. Sælgeren vælger "Retur af webordre" i kassen, finder HML1240, vælger 1 stk., og at stolen lægges på lager. Stolen kommer tilbage på det lager, den blev trukket fra.
2. Sælgeren betaler 1.250,00 kr. tilbage (kontant eller retur på kortet) og afslutter returneringen. Den får bonnummer 5004 fra kassen.
3. Returneringen bogføres med kassen: debet salg 1.000,00, debet salgsmoms 250,00, kredit kassens kontant- eller kortkonto 1.250,00. Det sker med det samme eller ved dagsafslutningen, alt efter om "Bogfør hver handel." er slået til.
4. Inden for fem minutter står butiksnoten ("... kreditnota 5004, 1.250 kr., ... refunderet i butik") på HML1240, også hvis kassen først bogføres senere.
5. Kundeservice gør intet i Shopify. Bogholderen afstemmer returneringen i butikkens dagsafslutning som et negativt beløb på den betalingsform, kunden fik pengene tilbage på.

Åbne poster på kunden og [mellemregningskonto] er 0,00 hele vejen. Lageret: −2 stole ved salget, +1 stol på det oprindelige lager ved returneringen.

### Eksempel C: kreditnota, der ikke er refunderet endnu

**C1. Webordre.** Eksempel A efter trin 4, hvor kundeservice ikke har refunderet. Shopify viser noten, men hverken Shopify eller Saldi viser en refundering på ordren. Under Finans → Rapporter → Kontokort for [mellemregningskonto] står kreditnota 1002 med 125,00 i kredit uden modpost. Saldi giver ikke alarm her; det fanger den faste kontrol (afsnit 5).

**C2. Faktura med åben post.** Faktura 2001 på 1.250,00 kr. er sendt med betalingsfrist og betalt. Kreditnota 2002 er en dekort på 125,00 kr.

1. Før kreditnotaen er kundens åbne poster 0,00. Når kreditnota 2002 er bogført, står −125,00 åben med teksten "Kreditnota - 2002", og kunden står i kredit: under "Kun konti i kredit" i Debitor → Rapporter → "Åbne poster" og som åben post i "Kontokort". Saldi udligner ikke en delkreditering automatisk.
2. Når I betaler de 125,00 kr. tilbage fra banken, laver I en linje i Kassekladde: D/K "D", Debet kundenummeret; D/K "F", Kredit bankkontoen; Fakturanr. 2002 (kreditnotaens nummer, ikke fakturaens); Beløb 125,00. Ved "Bogfør" udligner Saldi linjen med kreditnotaen, fordi nummer og beløb passer (og projektet er det samme, hvis I bruger projekter). Åbne poster: 0,00. Skriv kun ét nummer i Fakturanr. pr. linje. Står kreditnotaen stadig åben, så udlign i hånden som i D, trin 3.

### Eksempel D: faktura med åben post, som ikke er betalt endnu

Samme faktura 2001 og kreditnota 2002, men kunden har ikke betalt.

1. Efter kreditnota 2002 står 1.250,00 ("Faktura - 2001") og −125,00 ("Kreditnota - 2002") åbne: kunden skylder 1.125,00. Saldi udligner dem ikke automatisk, og en faktura kan ikke deles op i "Udlign åbne poster".
2. Kunden betaler 1.125,00. Kassekladde: D/K "F", Debet bankkontoen; D/K "D", Kredit kundenummeret; Fakturanr. 2001; Beløb 1.125,00. Betalingen bliver en åben post på −1.125,00, fordi beløbet ikke svarer til fakturaen. Saldo 0,00, men tre poster står åbne.
3. Åbn kundens "Kontokort" (Debitor → Rapporter), og klik på et åbent beløb. I "Udlign åbne poster" sætter I flueben ved de to andre poster og trykker "Opdater". Når "Difference" er 0,00, vises knappen "Udlign"; tryk på den. Alle tre poster er udlignet.

"Udlign alle" i "Åbne poster" udligner alle kunder på siden, hvis poster går i nul. Brug den kun, når I har set posterne igennem.

## 5. Afvigelser, og hvad I gør

Vi foreslår en fast kontrol, fx ugentligt: [mellemregningskonto] for kreditnotaer uden modpost, og "Kun konti i kredit" i "Åbne poster".

**Kreditnota uden refundering.** *Opdages:* ikke automatisk, men i den faste kontrol. *Gør:* Tjek Shopify-ordren. Mangler refunderingen, beder I kundeservice refundere. Er der refunderet, men ikke bogført, så bogfør som i eksempel A, trin 6.

**Refundering uden kreditnota.** *Opdages:* automatisk. Saldi opretter en fejl i Shopify-fejllisten og giver alarm til Saldi-support, og I får en daglig oversigt, når der har været fejl. Det gælder også en ordre, der annulleres i Shopify uden en kreditnota i Saldi; så laves en kreditnota for hele ordren. En refundering, der står som afventende eller fejlet, tæller ikke som refunderet. *Gør:* Tjek først, om ordren allerede er refunderet i butikken; så er det en dobbelt refundering (se nedenfor), og der laves ingen ny kreditnota. Ellers laver I kreditnotaen for det refunderede beløb, afstemmer som i eksempel A og lukker fejlen med "Markér som løst".

**Beløbet passer ikke.** *Opdages:* manuelt, når I sammenligner refunderingen (på ordren i Saldi eller i udbetalingen) med kreditnotaen. Typisk er fragten refunderet, men ikke krediteret. *Gør:* Er der refunderet for meget efter aftale med kunden, laves en ekstra kreditnota for forskellen; er det sket ved en fejl, så gør som under "Refunderet begge steder". Er der refunderet for lidt, refunderer kundeservice resten. En bogført kreditnota kan ikke rettes, kun modposteres med et nyt bilag. Indtil da står forskellen på [mellemregningskonto].

**Refunderet begge steder.** *Opdages:* manuelt. Kontrollér for hver butiksreturnering af en webordre (tag `saldi-returneret-i-butik`), at Shopify-ordren ikke også har en refundering af samme beløb. *Gør:* Kontakt kunden. Bogfør den ekstra udbetaling som et tilgodehavende på kunden: linje i Kassekladde med D/K "D", Debet kundenummeret; D/K "F", Kredit den konto, pengene er betalt fra; Fakturanr. kreditnotanummeret; ingen moms. Så står beløbet som åben post, indtil kunden har betalt tilbage.

## 6. Godkendelse

Proceduren skal godkendes af jeres bogholder før overleveringstesten. Indtil da bruger vi udkastet i testen, hvor vi gennemgår proceduren med jeres bogholderi på den færdige løsning.

## 7. Det har vi brug for fra jer

1. **Mellemregningskonto.** Hvilken konto skal kreditnotaer på webordrer bogføres på, indtil refunderingen er afstemt? Svar gerne inden overleveringstesten; ellers bruger vi en midlertidig konto i testen.
2. **Refunderinger hos betalingsudbyderen.** Trækkes refunderinger fra i udbetalingerne, eller kommer de som særskilte træk? Det afgør kreditkontoen i eksempel A, trin 6.
3. **Gavekort og tilgodebeviser.** Refunderer I nogensinde som gavekort, tilgodebevis eller butikskredit ("Store credit" i Shopify) i stedet for penge, i Shopify eller i butikken? Det er ikke beskrevet endnu.
4. **Indstillinger og salg på kredit.** Vi læser selv jeres indstillinger i Saldi før testen og retter proceduren til dem. Proceduren forudsætter, at "Omgående bogføring af salgsordrer" er slået til. Sender I fakturaer med betalingsfrist (så gælder eksempel C2 og D)? Sælger I på konto i butikkerne (så tilføjer vi et eksempel)?
5. **Afdeling.** Skal en kreditnota på en webordre bogføres på webshoppens afdeling og en butiksreturnering på butikkens? Skal dekort og reklamationer på en særlig afdeling?
6. **Fragt.** Skal fragten med på kreditnotaen, når kundeservice refunderer fragt i Shopify?
7. **Lager ved butiksreturnering.** Skal en vare fra en webordre, der returneres i butikken, lægges på butikkens lager eller på det lager, den blev sendt fra? Proceduren følger i dag det sidste (eksempel B).
8. **Opfølgning og godkendelse.** Hvem hos jer godkender proceduren, og hvornår (afsnit 6)? Hvem modtager den daglige oversigt over fejl? Hvem tager den faste kontrol i afsnit 5, og hvor ofte?
