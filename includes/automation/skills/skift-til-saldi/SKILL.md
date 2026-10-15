---
slug: skift-til-saldi
title: Skift til Saldi
description: Sæt et nyt regnskab op i Saldi og flyt saldi, kunder og åbne poster over fra det gamle system.
version: 1
audience: alle
---

# Skift til Saldi

Dette forløb er for firmaer, der flytter deres bogføring til Saldi fra et andet
system. Det er nemmest at skifte ved starten af et regnskabsår. Så er
åbningsbalancen det gamle års slutbalance.

Ejeren sørger for firmaoplysninger og brugere. Bogholderen sætter kontoplan og moms
op og flytter tallene. Spørg gerne revisor om åbningsbalancen.

## Trin

1. **Udfyld stamdata.** Skriv firmaets navn, adresse og CVR-nummer under
   System > Indstillinger > Stamdata. Oplysningerne kommer på tilbud, fakturaer og
   rykkere.
2. **Opret regnskabsåret.** Opret det første regnskabsår under System >
   Indstillinger > Regnskabsår med startmåned og slutmåned. Der kan kun bogføres i et
   aktivt regnskabsår.
3. **Læg kontoplanen ind.** Brug standardkontoplanen, eller indlæs jeres egen under
   System > Indstillinger > Diverse > Import & eksport. En kontoplan kan kun indlæses
   i et regnskabsår uden bogføringer. Gør det derfor først.
4. **Sæt momsen op.** Angiv momssatser og momskonti under System > Indstillinger >
   Moms. Har I kunder uden moms, så opret en momssats på 0 %.
5. **Opret debitor- og kreditorgrupper.** Opret grupperne under System >
   Indstillinger > Debitor-/kreditorgrupper med momsgruppe og samlekonto. Grupperne
   kræver, at momsen er sat op.
6. **Opret brugere.** Opret en bruger til hver person under System > Indstillinger >
   Brugere, og giv kun de rettigheder, personen har brug for.
7. **Flyt kunder og leverandører.** Kunder kan indlæses fra en CSV-fil under Import &
   eksport. Leverandører opretter du under Kreditorer > Konti, eller når de kommer
   med en e-faktura.
8. **Skriv åbningsbalancen ind.** Klik på regnskabsårets ID under Regnskabsår, og
   skriv primotallene for statuskontiene. Brug det gamle systems slutbalance. Debet
   og kredit skal gå op.
9. **Flyt de åbne poster.** Ubetalte fakturaer fra det gamle system bogføres i en
   kassekladde på de enkelte kunder og leverandører med fakturanummer og forfaldsdato.
   Aftal modkontoen med revisor, så saldiene ikke kommer med to gange.
10. **Sæt formularer og bilag op.** Tilpas faktura og rykkere under System >
    Indstillinger > Formularer. Tjek bilagshåndteringen, så I kan sende bilag til
    puljen.
11. **Lav den første bankimport og afstemning.** Importér de første bankposteringer
    og kør Afstem bank. Se forløbet Bankafstemning.
12. **Tag en sikkerhedskopi.** Lav en sikkerhedskopi under System > Backup/Gendan, når
    opsætningen er færdig.

## Hvad Assist kan tjekke

- Firmaets navn, CVR-nummer og regnskabsår.
- Saldobalancen, også for åbningsbalancen.
- Åbne poster for kunder og leverandører efter overflytningen.
- Ikke-bogførte kladder.

Assist kan forklare skærmene undervejs og pege på det rigtige afsnit i manualen.

## Hvad kun du kan gøre

- Indlæse kontoplan og kunder.
- Skrive primotal og bogføre de åbne poster.
- Oprette brugere og give rettigheder.

Assist bogfører aldrig og ændrer aldrig rettigheder.

```json saldi-steps
[
  {"id": "s1", "title": "Udfyld stamdata", "check_tool": "get_company_context", "check_args": {}, "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-stamdata"},
  {"id": "s2", "title": "Opret regnskabsåret", "check_tool": "get_company_context", "check_args": {}, "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-regnskabsaar-2"},
  {"id": "s3", "title": "Læg kontoplanen ind", "check_tool": "get_trial_balance", "check_args": {"from": "$fiscal_year_start", "to": "$today", "include_zero": true}, "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-import-eksport"},
  {"id": "s4", "title": "Sæt momsen op", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-momsindstillinger"},
  {"id": "s5", "title": "Opret debitor- og kreditorgrupper", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-debitor-kreditorgrupper"},
  {"id": "s6", "title": "Opret brugere", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-brugere"},
  {"id": "s7", "title": "Flyt kunder og leverandører", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-import-eksport"},
  {"id": "s8", "title": "Skriv åbningsbalancen ind", "check_tool": "get_trial_balance", "check_args": {"from": "$fiscal_year_start", "to": "$fiscal_year_start"}, "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-regnskabsaar-2"},
  {"id": "s9", "title": "Flyt de åbne poster", "check_tool": "list_open_items", "check_args": {"party_type": "debtor", "group_by_party": true}, "screen": "finans/kladdeliste.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-bilag-2-uddybning-af-kassekladdens-indhold"},
  {"id": "s10", "title": "Sæt formularer og bilag op", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-formularer"},
  {"id": "s11", "title": "Lav den første bankimport og afstemning", "check_tool": "list_journals", "check_args": {"status": "open"}, "screen": "finans/kladdeliste.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-bilag-1-import-af-bankposteringer"},
  {"id": "s12", "title": "Tag en sikkerhedskopi", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-backup-gendan"}
]
```
