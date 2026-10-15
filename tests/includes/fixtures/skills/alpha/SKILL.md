---
slug: alpha
title: Afstemning
description: Afstem den valgte periode.
version: 1
audience: bogholder
---

# Afstemning

```text
Dette er ikke trinene.
```

```json saldi-steps
[{"id":"s1","title":"Se kladder","check_tool":"list_journals","check_args":{},"screen":"finans/kladdeliste.php"},{"id":"s2","title":"Afstem perioden","check_tool":"get_period_status","check_args":{"from":"$period_start","to":"$period_end"}}]
```
