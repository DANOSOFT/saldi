---
slug: rykkere
title: Rykkere
description: Find de kunder, der ikke har betalt til tiden, og send dem en rykker.
version: 1
audience: bogholder
---

# Rykkere

En rykker er et brev til en kunde, der ikke har betalt en faktura til tiden. Saldi
har tre rykkertrin. Efter tredje rykker kan sagen sendes til inkasso. Du gør det
typisk en eller to gange om måneden, efter at indbetalingerne er bogført.

Det er bogholderen, der gør arbejdet. Saldi sender aldrig rykkere af sig selv. Du
starter altid forløbet. Rykkerne findes under Debitor > Rapporter > Åbne poster.

## Trin

1. **Tjek opsætningen første gang.** Under System > Indstillinger > Diverse >
   Rykkerrelaterede valg sætter du dagene mellem rykkertrinnene og kontonummeret på
   jeres inkassoadvokat. Klik Gem/opdatér.
2. **Tjek gebyr og rente.** Gebyr og rente hentes fra formularerne Rykker 1, 2 og 3
   under System > Indstillinger > Formularer. Gebyrvaren må ikke være lagerført.
   Er felterne tomme, kommer der hverken gebyr eller rente på rykkeren.
3. **Bogfør indbetalingerne først.** Bogfør bankens indbetalinger og udlign dem mod
   fakturaerne. Ellers rykker du kunder, der allerede har betalt.
4. **Find de forfaldne fakturaer.** Åbn Debitor > Rapporter, og klik Åbne poster.
   Forfaldne beløb står med rødt. Sæt flueben ud for de kunder, der skal rykkes.
5. **Dan rykkerne.** Klik Opret rykker for de valgte kunder. Ryk alle kører hele
   kørslen for alle kunder, men kun når ingen kunder er afmærket. Rykkerne lægges
   under Åbne rykkere. Intet bliver sendt endnu.
6. **Gennemse og ret.** Klik på rykkerens løbenummer for at åbne den. Du kan rette
   modtager, e-mail og tekst. Beløbene følger fakturaerne og kan ikke rettes her.
7. **Udskriv eller mail rykkeren.** Marker rykkerne, og klik Udskriv. Er Send pr.
   mail sat på rykkeren, sendes den som mail, når du bekræfter.
8. **Bogfør rykkeren.** Marker rykkerne under Åbne rykkere, og klik Bogfør. Først
   nu kommer gebyr og renter ind i regnskabet. Der beregnes ikke moms af dem.
9. **Dan næste trin eller afslut.** Betaler kunden ikke, så marker rykkeren under
   Bogførte rykkere, og klik Ny rykker. Har kunden betalt, så klik Afslut.
10. **Send til inkasso, hvis det er nødvendigt.** Efter tredje rykker kan du åbne
    den bogførte rykker og klikke Inkasso. Saldi følger ikke op på sagen bagefter.

## Hvad Assist kan tjekke

- Hvilke kunder der har forfaldne poster, hvor meget, og hvor gamle de er.
- Om der ligger ikke-bogførte kladder, der kan indeholde indbetalinger.
- Hvilke rykkerforslag fra Assist der venter på din godkendelse.

Assist kan foreslå en rykker for udvalgte fakturaer. Forslaget skal godkendes i
Saldi. Det danner kun rykkeren. Den bliver ikke sendt eller bogført.

## Hvad kun du kan gøre

- Godkende, udskrive og sende rykkere.
- Bogføre gebyr og renter.
- Danne næste trin, afslutte og sende sager til inkasso.

Assist sender aldrig rykkere og bogfører aldrig.

```json saldi-steps
[
  {"id": "s1", "title": "Tjek opsætningen første gang", "doc": "docs:saldi-supplement-rykkere-inkasso-foer-du-sender-den-foerste-rykker"},
  {"id": "s2", "title": "Tjek gebyr og rente", "doc": "docs:saldi-supplement-rykkere-inkasso-opsaetning-af-rykkergebyr-og-rente"},
  {"id": "s3", "title": "Bogfør indbetalingerne først", "check_tool": "list_journals", "check_args": {"status": "open"}, "screen": "finans/kladdeliste.php", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-simuler-bogfoer-importer-og-udlign-en-kassekladde"},
  {"id": "s4", "title": "Find de forfaldne fakturaer", "check_tool": "list_open_items", "check_args": {"party_type": "debtor", "overdue_only": true, "group_by_party": true}, "doc": "docs:saldi-supplement-rykkere-inkasso-find-de-forfaldne-fakturaer"},
  {"id": "s5", "title": "Dan rykkerne", "check_tool": "list_inbox_items", "check_args": {"status": "open", "kind": "reminder.draft"}, "doc": "docs:saldi-supplement-rykkere-inkasso-opret-rykker-til-udvalgte-kunder"},
  {"id": "s6", "title": "Gennemse og ret", "doc": "docs:saldi-supplement-rykkere-inkasso-ret-indholdet-paa-en-rykker"},
  {"id": "s7", "title": "Udskriv eller mail rykkeren", "doc": "docs:saldi-supplement-rykkere-inkasso-udskriv-eller-mail-rykkeren"},
  {"id": "s8", "title": "Bogfør rykkeren", "doc": "docs:saldi-supplement-rykkere-inkasso-bogfoer-rykkeren"},
  {"id": "s9", "title": "Dan næste trin eller afslut", "check_tool": "list_open_items", "check_args": {"party_type": "debtor", "overdue_only": true, "group_by_party": true}, "doc": "docs:saldi-supplement-rykkere-inkasso-dan-naeste-rykkertrin"},
  {"id": "s10", "title": "Send til inkasso, hvis det er nødvendigt", "doc": "docs:saldi-supplement-rykkere-inkasso-send-sagen-til-inkasso"}
]
```
