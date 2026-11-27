# Optional installation settings in includes/connect.php

`includes/connect.php` belongs to one installation (it is not in git; `index/install.php` writes it). Besides the
database login it can set the optional settings below. Each one is off when it is not there, and the installation then
works as before. Set them at the top level of the file, with a literal value, as in the examples:
`includes/installSettings.php` also reads them from the file where a request needs them before connect.php is included
or after it was included inside a function.

## `$allowedLedgers`: the ledgers this installation opens

```php
$allowedLedgers = array('saldi_2');
```

The database names (`regnskab.db` in the master database) that this installation may open. Every other ledger is
refused before it is opened, so none of its updates (`tjek4opdat()`, `includes/betweenUpdates.php`) run and nothing is
written to it:

- the login page answers with text 8235 ("Regnskabet kan ikke åbnes i denne installation."), and a master-admin login
  is refused the same way unless the master database is in the list;
- a session whose row in `online` names another ledger is refused with HTTP 403 (`includes/online.php`), so the
  admin pages and "Åbn regnskab" need the master database in the list too (see below);
- Glemt adgangskode sends no temporary password and no user list for another ledger, and its list of ledgers for an
  e-mail names only listed ones; an invitation to another ledger is not valid;
- `db_connect()` refuses any database other than the master database and the listed ones, so the REST and SOAP APIs, the
  POS, mySale, the booking pages, the command-line tools (`--db=` and `--all`) and everything else that opens a
  ledger stop there. The loops of `tools/notification_digest.php`, `tools/stripe_arm_ledgers.php` and
  `tools/stripe_charge_due.php --all` visit only the listed ledgers;
- no ledger is created, as a new ledger's database is not in the list: `create_regnskab()` (`admin/opret.php`,
  `api/signup_api.php`) answers `not_allowed`, and the signup pages (`signup/*.php`, through
  `includes/signupIncludes/signupBootstrap.php`) answer HTTP 403, before the master database is read or written;
- the master database is connected for its own work (the `regnskab` registry, the sessions in `online`, the settings)
  also when it is not listed, but it is the working ledger of a request, a job or an API call only when it is listed.
  Every place that takes the ledger from a request, a token, a link, a session row or `--db=` checks it with
  `saldi_ledger_guard()` or `saldi_ledger_allowed()` before it is opened or anything is written for it: the
  single-ledger command-line jobs (`tools/stripe_charge_due.php`, `tools/reminder_due.php`,
  `tools/stripe_invoice_pdf.php`, `tools/stripe_print_link.php`, `tools/stripe_arm_ledgers.php --db`,
  `tools/notification_digest.php --db`, `admin/stripe_setup.php`) end with exit code 1, the Stripe pages and webhook
  (`?r=`), the REST login, refresh and tokens, SOAP, `api/*`, booking and mySale answer HTTP 403;
- Indlæs sikkerhedskopi (`admin/restore.php`) restores only a SALDI backup (`.sdat` with its `backup.info`): an `.sql`
  file and the move from MySQL are refused with text 8236, as their import does not go through `db_connect()`.

On MySQL, `db_connect()` opens the server without a database; `db_select_database()`, which selects it there, follows
the same rule, and the login, session, forgot-password and invitation checks above refuse another ledger as well.

Listing the master database lets the master admin log in, and the admin pages work on the master database, which holds
the row of every ledger. Of those that act on a ledger, "Åbn regnskab", Slet regnskab (`admin/slet_regnskab.php`), the
save and Beregn of `admin/vis_regnskaber.php`, Opret regnskab, and every page that opens a ledger through `db_connect()`
follow the list. The pages that change the master database itself (the admin settings, the admin panel, the license
manager, the admin users) act for every ledger in it, as without the setting. List the master database only
in an installation where those pages are meant to be used.

The helpers are in `includes/ledgerAllowList.php`; `saldi_ledger_allowed($db)` answers for one database.

## Session cookie per installation

Two installations on one host (`/saldi` and `/saldi_test`, say) share PHP's default session cookie `PHPSESSID` (path `/`) in a browser, and with it the row in the master table `online` that tells which ledger the session works on. Give the folder its own name in the web server's configuration, e.g. `php_value session.name SALDITEST` in an Apache `<Directory>` block (mod_php) or `session.name` in a `.user.ini` (PHP-FPM). Changing the name logs everybody out of that installation once.

## `$sharedWithLive`: ledgers that another installation serves too

```php
$sharedWithLive = true;
```

For an installation whose ledgers another installation opens too, through the same master database, and reads
`brugere.rettigheder` there position by position, without roles. With the setting:

- the rights string stored for a user with a role holds read as no access (`'0'`), and write as `'1'`
  (`perm_legacy_string_stored()` in `includes/permissions.php`); a reader that does not know roles then opens only the
  positions the role gives as write;
- in this installation a request takes the user's string from the role (`perm_session_rights()` in
  `includes/online.php`, `index/login.php`, the REST module check and the SOAP login), and so do the daily summary
  (`tools/notification_digest.php`) and the assistant's record reads (`saldi_assist_rights()` in
  `includes/assist/RecordAuth.php`), so read stays read here; the checks that read the stored string themselves ask
  for write (`'1'`) only (`finans/provisionsrapport.php`, the booking user);
- at each login, a stored string of a user with a role that still holds a read position is rendered again
  (`perm_store_shared_rights()`, the end of `includes/betweenUpdates.php`), in one transaction that locks the roles
  and then the users in the order role administration does, so an assignment or a change of a role's permissions at
  the same time is kept.

The setting decides only what is stored in `brugere.rettigheder`: it cannot restrict a page of the other installation
that does not check that string itself.

Without the setting a read level is stored as `'2'`, as before. When it is switched off again, save the users' roles
(or the roles) once, so their strings hold `'2'` again.
