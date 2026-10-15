---
slug: aarsafslutning
title: Årsafslutning
description: Gør regnskabsåret færdigt i Saldi, så revisor kan lave årsrapporten, og det nye år starter med de rigtige primotal.
version: 1
audience: bogholder
---

# Årsafslutning

Årsafslutningen gør et helt regnskabsår færdigt. Du starter, når året er slut og
den sidste måned er afsluttet. Mange firmaer laver selve årsrapporten sammen med
en revisor. Saldis del er at gøre tallene klar, lave det nye regnskabsår og føre
saldiene over som primotal.

Det er bogholderen, der gør arbejdet. Ejeren og revisor læser rapporterne.

## Trin

1. **Opret det nye regnskabsår i tide.** Der kan kun bogføres i et regnskabsår, der
   er oprettet og aktivt. Opret det nye år under System > Indstillinger > Regnskabsår
   med beskrivelse, startmåned og slutmåned.
2. **Bogfør årets sidste kladder.** Alle kladder med datoer i det gamle år skal være
   bogført. Lav månedsafslutningen for årets sidste måned.
3. **Afstem banken pr. årets sidste dag.** Bankens saldo i Saldi skal være den samme
   som på kontoudtoget. Se forløbet Bankafstemning.
4. **Gennemgå debitorernes åbne poster.** Kontrollér at alle tilgodehavender er
   rigtige. Tal med revisor om fakturaer, der ikke bliver betalt.
5. **Gennemgå kreditorernes åbne poster.** Kontrollér at leverandørgælden stemmer med
   leverandørernes kontoudtog.
6. **Afslut momsen for årets sidste periode.** Lav momsafregningen for den sidste
   periode. Se forløbet Momsafregning.
7. **Opgør lageret, hvis I har lager.** Kør Lagerstatus pr. årets sidste dag under
   Lager > Rapporter. Giv revisor tallet for den samlede lagerværdi.
8. **Lav rapporterne til revisor.** Kør Balance, Resultat og eventuelt Regnskab Basis
   for hele året under Finans > Rapporter. Revisor kan også bede om en SAF-T-fil.
9. **Bogfør revisors efterposteringer.** Får du rettelser fra revisor, så bogfør dem i
   en kassekladde med en dato i det gamle år.
10. **Kontrollér primotallene i det nye år.** Klik på det nye regnskabsårs ID under
    System > Indstillinger > Regnskabsår. Statuskontiernes slutsaldi føres over som
    primotal. Årets resultat skal overføres til den rigtige egenkapitalkonto. Klik
    Gem/opdatér. Gør det igen, hvis du bogfører mere i det gamle år.
11. **Luk det gamle år for bogføring.** Luk årets måneder under Momsperioder, når
    revisor er færdig. Så kan der ikke bogføres i året ved en fejl.
12. **Tag en sikkerhedskopi.** Lav en sikkerhedskopi under System > Backup/Gendan, og
    gem den på din egen computer.

## Hvad Assist kan tjekke

- Hvilke regnskabsår der findes, og hvilket der er aktivt.
- Ikke-bogførte kladder og linjer uden bilag i året.
- Åbne poster for debitorer og kreditorer.
- Saldobalancen for året og momsen for den sidste periode.
- Om årets måneder er lukket.

## Hvad kun du kan gøre

- Oprette regnskabsåret og overføre primotal.
- Bogføre, også revisors efterposteringer.
- Indberette moms og lukke perioder.
- Indsende årsrapporten, normalt via revisor.

Assist bogfører aldrig, lukker aldrig perioder og indberetter aldrig.

```json saldi-steps
[
  {"id": "s1", "title": "Opret det nye regnskabsår i tide", "check_tool": "get_company_context", "check_args": {}, "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-regnskabsaar-2"},
  {"id": "s2", "title": "Bogfør årets sidste kladder", "check_tool": "get_period_status", "check_args": {"from": "$fiscal_year_start", "to": "$period_end"}, "screen": "finans/kladdeliste.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-simuler-bogfoer-importer-og-udlign-en-kassekladde"},
  {"id": "s3", "title": "Afstem banken pr. årets sidste dag", "check_tool": "get_trial_balance", "check_args": {"from": "$fiscal_year_start", "to": "$period_end"}, "screen": "finans/rapport.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-afstem-bank"},
  {"id": "s4", "title": "Gennemgå debitorernes åbne poster", "check_tool": "list_open_items", "check_args": {"party_type": "debtor", "group_by_party": true}, "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-bilag-3-mere-information-om-aabne-poster"},
  {"id": "s5", "title": "Gennemgå kreditorernes åbne poster", "check_tool": "list_open_items", "check_args": {"party_type": "creditor", "group_by_party": true}, "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-aabne-poster"},
  {"id": "s6", "title": "Afslut momsen for årets sidste periode", "check_tool": "get_vat_summary", "check_args": {"from": "$period_start", "to": "$period_end"}, "screen": "finans/rapport.php", "doc": "docs:saldi-supplement-rapporter-udvidet-momsafstemning"},
  {"id": "s7", "title": "Opgør lageret, hvis I har lager", "doc": "docs:saldi-supplement-lager-udvidet-lagerstatus-pr-dato"},
  {"id": "s8", "title": "Lav rapporterne til revisor", "check_tool": "get_trial_balance", "check_args": {"from": "$fiscal_year_start", "to": "$period_end"}, "screen": "finans/rapport.php", "doc": "docs:saldi-supplement-rapporter-udvidet-regnskab-basis"},
  {"id": "s9", "title": "Bogfør revisors efterposteringer", "check_tool": "list_journals", "check_args": {"status": "open"}, "screen": "finans/kladdeliste.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-bilag-2-uddybning-af-kassekladdens-indhold"},
  {"id": "s10", "title": "Kontrollér primotallene i det nye år", "check_tool": "get_company_context", "check_args": {}, "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-bilag-5-statuskonto"},
  {"id": "s11", "title": "Luk det gamle år for bogføring", "check_tool": "get_period_status", "check_args": {"from": "$fiscal_year_start", "to": "$period_end"}, "screen": "finans/moms_periode.php", "doc": "docs:saldi-supplement-rapporter-udvidet-momsperioder-luk-og-aabn-en-momsperiode"},
  {"id": "s12", "title": "Tag en sikkerhedskopi", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-backup-gendan"}
]
```
