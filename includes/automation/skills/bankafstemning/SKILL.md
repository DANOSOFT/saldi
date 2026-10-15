---
slug: bankafstemning
title: Bankafstemning
description: Kontrollér at det, der er bogført på bankkontoen i Saldi, stemmer med bankens kontoudtog.
version: 1
audience: bogholder
---

# Bankafstemning

Bankafstemning betyder, at du sammenligner bankkontoen i Saldi med bankens
kontoudtog. Når de stemmer, ved du, at alle ind- og udbetalinger er bogført én gang
og på den rigtige dato. Du gør det mindst en gang om måneden og altid før
momsafregning og årsafslutning.

Det er bogholderen, der gør arbejdet. Saldi matcher posterne ud fra dato og beløb
og viser forskellene.

## Trin

1. **Hent kontoudtoget fra netbanken.** Eksportér kontoudtoget for perioden som fil,
   fx CSV eller MT940. Gem filen, så du kan bruge den igen i trin 5.
2. **Importér bankposteringerne i en kassekladde.** Åbn eller opret en kassekladde,
   og klik Importér. Vælg filen under Importer bankposteringer. Angiv bankens
   kontonummer i Posteringskonto, klik Vis, og klik Flyt, når det ser rigtigt ud.
   Tjek for dubletter, så den samme fil ikke importeres to gange.
3. **Konter og udlign linjerne.** Skriv modkonto på hver linje. Indbetalinger fra
   kunder og betalinger til leverandører udligner du mod de åbne poster med Udlign.
   Sæt bilag på linjerne.
4. **Simulér og bogfør kladden.** Klik Simulér for at kontrollere balance, valuta og
   moms. Ret fejl, og klik Bogfør.
5. **Kør Afstem bank.** Gå til Finans > Rapporter, og vælg Afstem bank. Vælg filen
   med kontoudtoget, klik Hent, vælg kategori for hver kolonne, skriv bankkontoen i
   Posteringskonto, og klik Afstem.
6. **Ret forskellene.** Venstre side er banken, højre side er Saldi. Sorte linjer
   stemmer. Røde linjer stemmer ikke. En post, der kun står i banken, er ikke
   bogført i Saldi. En post, der kun står i Saldi, findes ikke i banken. De grønne
   pile flytter poster op og ned inden for samme dato.
7. **Sammenlign saldoen.** Bankkontoens saldo i Saldi skal være den samme som
   saldoen på kontoudtoget på periodens sidste dag. Er der en forskel, så find den,
   før du går videre.

## Hvad Assist kan tjekke

- Om der ligger ikke-bogførte kladder, og hvor mange linjer der mangler.
- Linjer uden bilag i perioden.
- Bankkontoens saldo i Saldi ud fra saldobalancen.

Assist kan ikke se bankens kontoudtog. Sammenligningen med banken laver du i Afstem
bank. Assist kan foreslå kontering af en bankpostering. Forslaget skal godkendes, og
du bogfører selv.

## Hvad kun du kan gøre

- Importere kontoudtoget og køre Afstem bank.
- Udligne og bogføre.
- Rette fejl i allerede bogførte poster.

Assist bogfører aldrig og betaler aldrig.

```json saldi-steps
[
  {"id": "s1", "title": "Hent kontoudtoget fra netbanken", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-bilag-1-import-af-bankposteringer"},
  {"id": "s2", "title": "Importér bankposteringerne i en kassekladde", "check_tool": "list_journals", "check_args": {"status": "open"}, "screen": "finans/kladdeliste.php", "doc": "docs:saldi-supplement-import-ubl-loen-csv-importer-data-til-en-kassekladde-overblik"},
  {"id": "s3", "title": "Konter og udlign linjerne", "check_tool": "get_period_status", "check_args": {"from": "$period_start", "to": "$period_end"}, "screen": "finans/kladdeliste.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-bilag-2-uddybning-af-kassekladdens-indhold"},
  {"id": "s4", "title": "Simulér og bogfør kladden", "check_tool": "get_period_status", "check_args": {"from": "$period_start", "to": "$period_end"}, "screen": "finans/kladdeliste.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-simuler-bogfoer-importer-og-udlign-en-kassekladde"},
  {"id": "s5", "title": "Kør Afstem bank", "screen": "finans/rapport.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-afstem-bank"},
  {"id": "s6", "title": "Ret forskellene", "check_tool": "get_period_status", "check_args": {"from": "$period_start", "to": "$period_end"}, "screen": "finans/rapport.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-afstem-bank"},
  {"id": "s7", "title": "Sammenlign saldoen", "check_tool": "get_trial_balance", "check_args": {"from": "$fiscal_year_start", "to": "$period_end"}, "screen": "finans/rapport.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-rapporttype"}
]
```
