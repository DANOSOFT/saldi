---
slug: momsafregning
title: Momsafregning
description: Gør momsen for en periode klar, afstem den, og gør tallene klar til indberetning.
version: 1
audience: bogholder
---

# Momsafregning

Momsafregningen opgør, hvor meget moms virksomheden skal betale eller have igen for
en periode. Perioden er en måned, et kvartal eller et halvår, alt efter hvad
Skattestyrelsen har givet virksomheden. Du gør det, når perioden er slut og
bogføringen for den er færdig, og før fristen.

Det er bogholderen, der gør arbejdet. Selve indberetningen sker på skat.dk og er
altid din egen handling.

## Trin

1. **Find perioden og fristen.** Se hvilken momsperiode der skal afregnes, og
   hvornår fristen er. Tjek fristen på skat.dk, hvis en helligdag kan flytte den.
2. **Gør bogføringen færdig.** Alle kladder med datoer i perioden skal være
   bogført. Momsen tæller kun med, når linjerne er bogført.
3. **Sørg for bilag til momslinjer.** Linjer på konti med moms skal have et bilag.
   Uden bilag kan fradraget for købsmoms ikke dokumenteres.
4. **Kør Momsafstemning.** Vælg Momsafstemning under Finans > Rapporter, og vælg
   perioden. Rapporten viser rækker, hvor bogført moms afviger fra beregnet moms.
   Ret fejl, før du går videre.
5. **Læs momsangivelsen.** Vælg Momsangivelse under Finans > Rapporter for perioden.
   Den viser salgsmoms, købsmoms og det samlede beløb. Vælg Momsrubrikker (A/B/C),
   hvis du handler med udlandet.
6. **Tjek EU-salg, hvis I har det.** Sælger I til private i andre EU-lande, så kør
   OSS B2C EU-salg. Sælger I varer momsfrit til firmaer i EU, så tjek
   listeangivelsen.
7. **Indberet på skat.dk.** Overfør tallene fra momsangivelsen til TastSelv Erhverv,
   og indberet. Det gør du selv.
8. **Bogfør afregningen og betalingen.** Bogfør overførslen fra momskontiene og
   betalingen af momsen i en kassekladde, som I plejer. Spørg jeres revisor, hvis
   I er i tvivl om kontiene.
9. **Luk perioden.** Luk månederne under System > Indstillinger > Regnskabsår >
   Momsperioder. Så kan ingen bogføre i en periode, der er indberettet.

## Hvad Assist kan tjekke

- Hvilken periode der er den aktuelle, og hvor mange dage der er til fristen.
- Ikke-bogførte kladder og linjer uden bilag i perioden.
- Salgsmoms, købsmoms og beløbet at betale ud fra de bogførte posteringer.
- Om månederne i perioden er lukket.

Assists momstal er beregnet ud fra bogførte posteringer. Ikke-bogførte kladder
tæller ikke med. Rapporterne i Saldi er det, du indberetter ud fra.

## Hvad kun du kan gøre

- Bogføre kladder og rette fejl.
- Indberette momsen på skat.dk og betale den.
- Lukke momsperioden.

Assist indberetter aldrig moms, betaler aldrig og lukker aldrig perioder.

```json saldi-steps
[
  {"id": "s1", "title": "Find perioden og fristen", "check_tool": "get_daily_overview", "check_args": {}, "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-momsindstillinger"},
  {"id": "s2", "title": "Gør bogføringen færdig", "check_tool": "get_period_status", "check_args": {"from": "$period_start", "to": "$period_end"}, "screen": "finans/kladdeliste.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-simuler-bogfoer-importer-og-udlign-en-kassekladde"},
  {"id": "s3", "title": "Sørg for bilag til momslinjer", "check_tool": "get_period_status", "check_args": {"from": "$period_start", "to": "$period_end"}, "screen": "finans/kladdeliste.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-vedhaeft-fil-eller-bilag-til-kassekladde"},
  {"id": "s4", "title": "Kør Momsafstemning", "check_tool": "get_vat_summary", "check_args": {"from": "$period_start", "to": "$period_end"}, "screen": "finans/rapport.php", "doc": "docs:saldi-supplement-rapporter-udvidet-momsafstemning"},
  {"id": "s5", "title": "Læs momsangivelsen", "check_tool": "get_vat_summary", "check_args": {"from": "$period_start", "to": "$period_end"}, "screen": "finans/rapport.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-rapporttype"},
  {"id": "s6", "title": "Tjek EU-salg, hvis I har det", "screen": "finans/rapport.php", "doc": "docs:saldi-supplement-rapporter-udvidet-oss-b2c-eu-salg"},
  {"id": "s7", "title": "Indberet på skat.dk"},
  {"id": "s8", "title": "Bogfør afregningen og betalingen", "check_tool": "list_journals", "check_args": {"status": "open"}, "screen": "finans/kladdeliste.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-simuler-bogfoer-importer-og-udlign-en-kassekladde"},
  {"id": "s9", "title": "Luk perioden", "check_tool": "get_period_status", "check_args": {"from": "$period_start", "to": "$period_end"}, "screen": "finans/moms_periode.php", "doc": "docs:saldi-supplement-rapporter-udvidet-momsperioder-luk-og-aabn-en-momsperiode"}
]
```
