---
slug: ny-kunde-eller-leverandoer
title: Ny kunde eller leverandør
description: Opret en ny kunde (debitor) eller leverandør (kreditor) rigtigt første gang.
version: 1
audience: alle
---

# Ny kunde eller leverandør

En kunde hedder en debitor i Saldi, og en leverandør hedder en kreditor. Du opretter
en ny, første gang du skal fakturere en kunde eller bogføre en regning fra en
leverandør. Alle med adgang til Debitor eller Kreditorer kan gøre det.

Det tager et par minutter. Det vigtigste er at undgå dubletter og at vælge den
rigtige gruppe, fordi gruppen styrer moms og bogføring.

## Trin

1. **Søg efter den først.** Søg på navn, kontonummer eller CVR-nummer på
   debitorlisten eller kreditorlisten. Findes den allerede, så brug den. To konti for
   den samme kunde giver rod i de åbne poster.
2. **Tjek at gruppen findes.** Hver kunde og leverandør hører til en gruppe under
   System > Indstillinger > Debitor-/kreditorgrupper. Gruppen bestemmer momsgruppe,
   samlekonto, valuta og sprog. Skal kunden ikke betale moms, skal gruppen have en
   momssats på 0 %.
3. **Opret kunden.** Gå til Debitor > Konti, og klik Ny. Skriv CVR-nummeret, så
   udfylder Saldi firmaoplysningerne. Vælg kundetype Privat eller Erhverv, og vælg
   gruppen. Kundenummeret tildeles automatisk, hvis du ikke vælger et selv. Klik
   Gem/opdatér.
4. **Tilføj kontaktperson og ekstra e-mails.** Når kunden er gemt, kan du tilføje en
   kontaktperson med Ny. Skal rykkere også til en anden adresse, så tilføj en ekstra
   e-mail af typen Rykker.
5. **Opret leverandøren.** Gå til Kreditorer > Konti, og klik Ny. Skriv CVR-nummeret,
   og vælg gruppen. Leverandørnummeret tildeles automatisk. To leverandører kan ikke
   have samme nummer. Klik Gem/opdatér.
6. **Skriv bankoplysninger selv.** Bankoplysninger på en leverandør skriver du altid
   selv ud fra en kilde, du stoler på. Ændrer en leverandør sine bankoplysninger, så
   ring og bekræft det, før du betaler.
7. **Godkend forslag fra Assist.** Har Assist foreslået en ny kunde eller leverandør,
   ligger forslaget i Opgaver. Læs det, og godkend eller afvis det dér.

## Hvad Assist kan tjekke

- Om kunden eller leverandøren allerede findes, når du giver et navn, et
  kontonummer eller et CVR-nummer.
- Hvilke forslag om nye kunder og leverandører der venter på godkendelse.

Assist kan foreslå en ny kunde eller leverandør ud fra en faktura eller et bilag.
Forslaget indeholder aldrig bankoplysninger.

## Hvad kun du kan gøre

- Godkende oprettelsen i Saldi.
- Skrive og ændre bankoplysninger.
- Lukke eller slette en kunde eller leverandør.

Assist ændrer aldrig bankoplysninger og sletter aldrig kunder eller leverandører.

```json saldi-steps
[
  {"id": "s1", "title": "Søg efter den først", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-debitorkonti"},
  {"id": "s2", "title": "Tjek at gruppen findes", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-debitor-kreditorgrupper"},
  {"id": "s3", "title": "Opret kunden", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-opret-ny-debitorkonto"},
  {"id": "s4", "title": "Tilføj kontaktperson og ekstra e-mails", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-tilfoej-kontaktperson-til-debitorkonti"},
  {"id": "s5", "title": "Opret leverandøren", "doc": "docs:saldi-regnskab-brugervejledning-juni-2026-opret-ny-kreditor-konto"},
  {"id": "s6", "title": "Skriv bankoplysninger selv"},
  {"id": "s7", "title": "Godkend forslag fra Assist", "check_tool": "list_inbox_items", "check_args": {"status": "open", "kind": "party.create"}}
]
```
