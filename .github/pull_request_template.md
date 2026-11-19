## Merge Danger
<!-- Required: the Guardrails check fails without it. The door map is tools/guardrails/lib/doors.mjs;
`node tools/guardrails/check.mjs --station` shows the station and door for your branch. -->
**Door:** one-way | two-way  <!-- one-way: a revert does not take back what it leaves (rows, a number, a schema change, money, a mail, deleted data) -->
**Blast Radius:** <!-- one page, one feature, every tenant ... -->
**Undo:** <!-- revert, or revert plus the data fix -->
**After merge:** none  <!-- or each step someone must take after the merge or the deploy, and who -->

## What are the changes about?
Provide a brief explanation of the changes you have made

## How I verified this
<!-- Required: the Guardrails check fails without it. What you did and what you saw:
the page and the steps in a local tenant, and the tests you ran (and that they ran).
For a change users see, fixtures alone are not enough. -->

## Have you checked the following? 
- [ ] I have updated relevant documentation at https://github.com/DANOSOFT/saldi/wiki
- [ ] I have checked that i am not linking to any external resources such as external style- or JavaScript-files, that could compromise stability
- [ ] I have read and understood the developer guidelines  
      https://docs.google.com/document/d/1GOmomtvKf21OV2VWNOIPweDi4gJHpMCK5qXzrPrypUc/edit?usp=sharing
- [ ] `node tools/guardrails/check.mjs` passes, or every finding has an approved exception
