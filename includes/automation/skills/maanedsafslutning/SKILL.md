---
slug: maanedsafslutning
title: Månedsafslutning
description: Gør bogføringen for en måned færdig, så tallene er til at stole på.
version: 1
audience: bogholder
---

# Månedsafslutning

Månedsafslutningen gør bogføringen for én måned færdig. Du gør det, når bankens
kontoudtog for måneden er kommet, typisk i starten af den næste måned. Det er
bogholderen, der gør arbejdet. Ejeren kan bagefter læse balancen og resultatet.

Når måneden er afsluttet, er alle bilag behandlet, alle kladder bogført, banken
afstemt, og de åbne poster gennemgået.

## Trin

1. **Tøm bilagspuljen.** Se de bilag, der ligger i puljen og endnu ikke er sat på
   en kladdelinje. Sæt hvert bilag på den rigtige linje i kassekladden.
2. **Importér månedens bankposteringer.** Hent kontoudtoget fra netbanken som fil,
   og importér det i en kassekladde med Bankimport. Kontrollér kolonnerne, før du
   klikker Flyt.
3. **Sørg for bilag på alle linjer.** Hver kladdelinje i måneden skal have et bilag.
   Linjer uden bilag skal have et bilag vedhæftet eller en forklaring i teksten.
4. **Simulér og bogfør kladderne.** Klik Simulér for at finde fejl og ubalancer.
   Ret dem, og klik derefter Bogfør. Det gør du selv i kassekladden.
5. **Afstem banken.** Kør Afstem bank under Finans > Rapporter. Røde linjer er
   poster, der ikke stemmer mellem banken og Saldi. Se også forløbet Bankafstemning.
6. **Gennemgå debitorernes åbne poster.** Se hvem der skylder penge, og om
   indbetalinger er udlignet mod de rigtige fakturaer. Se også forløbet Rykkere.
7. **Gennemgå kreditorernes åbne poster.** Kontrollér at betalte fakturaer er
   udlignet, og at der ikke ligger fakturaer, som burde være betalt.
8. **Læs balancen og resultatet.** Kør Balance og Resultat under Finans > Rapporter
   for perioden. Se efter konti med en saldo, der ser forkert ud.
9. **Luk måneden, hvis I gør det.** Når momsen for perioden er indberettet, kan du
   lukke måneden under System > Indstillinger > Regnskabsår > Momsperioder. En lukket
   måned kan ikke bogføres i.

## Hvad Assist kan tjekke

- Hvor mange bilag der ligger i puljen.
- Hvilke kladder der ikke er bogført, og hvor mange linjer der mangler bilag.
- Åbne poster for debitorer og kreditorer.
- Saldobalancen for perioden og om måneden er lukket.

Assist kan foreslå kladdelinjer ud fra et bilag. Forslaget skal godkendes i Saldi,
og det bliver ikke bogført af den grund.

## Hvad kun du kan gøre

- Bogføre kladderne.
- Udligne og rette poster.
- Lukke måneden.

Assist bogfører aldrig, betaler aldrig og lukker aldrig perioder.

```json saldi-steps
[
  {"id": "s1", "title": "Tøm bilagspuljen", "check_tool": "list_documents", "check_args": {"status": "pool"}, "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-vedhaeft-fil-eller-bilag-til-kassekladde"},
  {"id": "s2", "title": "Importér månedens bankposteringer", "check_tool": "list_journals", "check_args": {"status": "open"}, "screen": "finans/kladdeliste.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-bilag-1-import-af-bankposteringer"},
  {"id": "s3", "title": "Sørg for bilag på alle linjer", "check_tool": "get_period_status", "check_args": {"from": "$period_start", "to": "$period_end"}, "screen": "finans/kladdeliste.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-vedhaeft-fil-eller-bilag-til-kassekladde"},
  {"id": "s4", "title": "Simulér og bogfør kladderne", "check_tool": "get_period_status", "check_args": {"from": "$period_start", "to": "$period_end"}, "screen": "finans/kladdeliste.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-simuler-bogfoer-importer-og-udlign-en-kassekladde"},
  {"id": "s5", "title": "Afstem banken", "check_tool": "get_trial_balance", "check_args": {"from": "$period_start", "to": "$period_end"}, "screen": "finans/rapport.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-afstem-bank"},
  {"id": "s6", "title": "Gennemgå debitorernes åbne poster", "check_tool": "list_open_items", "check_args": {"party_type": "debtor", "group_by_party": true}, "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-bilag-3-mere-information-om-aabne-poster"},
  {"id": "s7", "title": "Gennemgå kreditorernes åbne poster", "check_tool": "list_open_items", "check_args": {"party_type": "creditor", "group_by_party": true}, "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-aabne-poster"},
  {"id": "s8", "title": "Læs balancen og resultatet", "check_tool": "get_trial_balance", "check_args": {"from": "$fiscal_year_start", "to": "$period_end"}, "screen": "finans/rapport.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-rapporttype"},
  {"id": "s9", "title": "Luk måneden, hvis I gør det", "check_tool": "get_period_status", "check_args": {"from": "$period_start", "to": "$period_end"}, "screen": "finans/moms_periode.php", "doc": "docs:saldi-supplement-rapporter-udvidet-momsperioder-luk-og-aabn-en-momsperiode"}
]
```
