# Integration Oversigt - Custom Audio

## Hvad gør integrationen?

Integrationen forbinder jeres **Custom Audio webshop** med **Saldi regnskabssystem**, så data automatisk synkroniseres mellem de to systemer.

---

## Hvad bliver synkroniseret?

### 1. Ordreimport (Webshop → Saldi)

Når en kunde laver en ordre på jeres webshop, bliver ordren automatisk:
- Importeret til Saldi
- Oprettet som en ordre med alle kundeoplysninger
- Faktureret automatisk * kund hvis dette ønskes

**Hvad bliver overført:**

**Kundeoplysninger:**
- Firmanavn, adresse, postnummer, by, land
- Telefon, email
- CVR nummer (hvis tilgængelig)
- Kontaktperson

**Leveringsoplysninger:**
- Leveringsfirmanavn
- Leveringsadresse
- Leveringspostnummer, by, land
- Leveringskontakt

**Ordreoplysninger:**
- Ordrenummer fra webshop
- Ordredato
- Ordrestatus
- Betalingsmetode
- Betalings-ID (transaktionsnummer)
- Kunde kommentarer

**Produkter:**
- Varenummer
- Produktbeskrivelse
- Antal
- Pris (inkl. rabatter)
- Variant information

**Fragt:**
- Fragtmetode
- Fragtpris (automatisk beregnet med moms)

**Priser og moms:**
- Netto sum (beregnes automatisk)
- Moms sum (beregnes automatisk)
- Momsats

**Hvilke ordrer importeres?**
- Kun komplette ordrer (betalte ordrer)
- Ordrer fra de sidste 3 dage (inkl. i dag)
- Ordrer der ikke allerede er importeret

---

### 2. Lageropdatering (Saldi → Webshop)

Når lagerbeholdningen ændres i Saldi, opdateres den automatisk på webshoppen.

**Hvordan virker det?**
- Saldi sender den nye lagerbeholdning
- Systemet sammenligner med nuværende lager på webshop
- Kun hvis der er en forskel, opdateres webshoppen

---

### 3. Prisopdatering (Saldi → Webshop)

Når priser ændres i Saldi, opdateres de automatisk på webshoppen.

**Hvad bliver opdateret?**
- Salgspris (beregnes som Saldi pris × 1.25)
- Rabatpris (hvis der er rabat)
- Indkøbspris
- Vægt/fragt
- Stregkode
- Detailpris

---

### 4. Indkøbsprisopdatering (Saldi → Webshop)

Når indkøbsprisen ændres i Saldi, opdateres den automatisk på webshoppen.

---

## Standardværdier

Følgende værdier er sat som standard og kan tilpasses:

**Ordreindstillinger:**
- Reference: "Webshop"
- Betalingsbetingelse: "Kreditkort"
- Betalingsdage: (skal defineres)
- Gruppe: 1
- Afdeling: 7
- Valutakurs: 100%

**Produktindstillinger:**
- Lager: (skal defineres)
- Varegruppe for fragt: (skal defineres)
- Varegruppe for kommentarer: 0

---

## Hvordan aktiveres integrationen?

Integrationen kører automatisk, når den aktiveres fra Saldi:

1. **Ordreimport** - Kører automatisk og henter nye ordrer
2. **Lageropdatering** - Kører når lager ændres i Saldi
3. **Prisopdatering** - Kører når priser ændres i Saldi
4. **Indkøbsprisopdatering** - Kører når indkøbspris ændres i Saldi

---

## Vigtige noter

**Automatisk fakturering:**
- Alle importerede ordrer faktureres automatisk i Saldi
- Betalingen registreres som POS betaling

**Duplikatkontrol:**
- Systemet husker hvilke ordrer der allerede er importeret
- Samme ordre importeres ikke to gange

**Fejlhåndtering:**
- Hvis en ordre ikke kan importeres, logges fejlen
- Systemet fortsætter med næste ordre

**Tidsperiode:**
- Ordreimport henter ordrer fra de sidste 3 dage
- Dette kan justeres efter behov

---

## Hvad skal konfigureres?

Følgende skal defineres før integrationen kan bruges:

1. **API adgang** - API URL og nøgle fra Custom Audio webshop
2. **Lager** - Hvilket lager produkter skal tilknyttes
3. **Varegruppe** - Varegruppe for fragt
4. **Betalingsdage** - Standard betalingsdage for ordrer
5. **Site ID** - Hvis webshoppen har flere sites

---

## Support

Hvis der opstår problemer eller spørgsmål til integrationen, kontakt support med:
- Beskrivelse af problemet
- Ordrenummer (hvis relevant)
- Tidsstempel for hvornår problemet opstod

---

**Dokument version:** 1.0  
**Dato:** 2025-11-21 
**Kunde:** Custom Audio

