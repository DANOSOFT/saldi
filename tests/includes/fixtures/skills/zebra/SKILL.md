---
slug: zebra
title: Årsafslutning
description: Gør regnskabet klar til afslutning.
version: 2
audience: ejer
---

# Årsafslutning

Tjek først perioden. Bogføring er altid en menneskelig handling.

```json saldi-steps
[{"id":"s1","title":"Tjek perioden","check_tool":"get_period_status","check_args":{"from":"$fiscal_year_start","to":"$today"},"screen":"finans/kladdeliste.php","doc":"docs:perioden"}]
```
