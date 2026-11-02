# Installation of SALDI

## Client Requirements

Web browser with cookies and JavaScript enabled, supporting pop-up windows.

Tested browsers: Google Chrome / Chromium, Vivaldi.
> Browsers from Microsoft are generally buggy and not recommended.

---

<details>
<summary><strong>Option A — Standard Installation (Linux + Apache + PostgreSQL)</strong></summary>

```
System requirements for server:
----------------------
Linux or other Unix-like system with web server with PHP support
as well as a PostgreSQL or MySQLi/MariaDB database server. PostgreSQL is recommended
as a database server.

In newer versions of PostgreSQL, the database administrator must have the right
'trust', which is set in the configuration file pg_hba.conf, for example as follows:

 local all postgres trust

If it is not set, there may be problems with loading the security
copy.

It is not recommended to use other database servers, as it is likely that there
errors will occur when upgrading where there has been a change in the database model.


The installation itself:
---------------------
0. We assume you have already initialized git. Then, Clone it from github to your root directory without adding in into extra directory.

e.g www/

www$ clone https://github.com/DANOSOFT/saldi.git

or www/html/

www/html$ git clone https://github.com/DANOSOFT/saldi.git

Note: if you don't need the extra directory that comes with the clonning,
i.)
Go to your working directory and run these commands:
	$ git remote add origin https://github.com/DANOSOFT/saldi.git
	$ git fetch origin
	$ git checkout -t origin/master



1. Optional: Saldi shares its runtime files through the group www-data. To
 use a separate group instead, create the group balances:

 sudo groupadd balances

2. Optional, only together with step 1: add the web user www-data to the
 balances group. Saldi uses balances only while www-data is a member of it.

 sudo usermod -a -G balances www-data

 Restart the web server (and PHP-FPM, if used) afterwards so it picks up
 the group. Add any other account that runs Saldi jobs (cron, CLI) the
 same way; it needs a new login session.

3. Manually create these directories inside the root of saldi: 'temp' and 'logolib'.
e.g saldi/loglib etc.

4. Change the rights to the 'includes', 'logolib' and 'temp' directories, so
 the web server user (the visitor) has access to write in
 these. Use the group balances if you did steps 1 and 2, otherwise
 www-data:

 sudo chgrp -R balances /var/www/html/saldi/      (or: www-data)
 sudo chmod 2770 /var/www/html/saldi/{includes,logolib,temp}

 or if you are down in the Saldi catalog itself:

 sudo chmod 2770 includes logolib temp

 2770 gives the owner and the group full access and other users none; the
 leading 2 (setgid) makes new files and directories inherit the group.
 Every account that reads or writes these directories, the web server
 included, must be the owner or a member of the group. Avoid 775, which
 lets every local user list and enter these directories, and 777, which
 also lets them replace files; under 777 Saldi falls back to plain writes
 without sharing them.
 See doc/runtime-permissions.md for CLI accounts and for migrating an
 existing installation.

 In the includes directory the file connect.php is created, so after
 creation, it can be changed to 555. In the catalog logolib
 are logos that are put up placed, while that too
 can be changed to 555 if you do not have (multiple) logos,
 which must be posted. However, it is important that there is write access
 to the temp directory as this is where log files and backups are
 is printed out.

5. Make sure that both the web server and the database server are running. Look in
 the documentation for these to see how.

6. Open your browser.

7. Specify the address of the web server and the directory under the web server
 hierarchy where you copied the Balances files to. For example:

	#domain/saldi

       # http://localhost/saldi

8. It may be that the browser complains that the page is trying to
 open pop-up windows. You must accept this. If not, it takes you to the installation page.

9. In the newly opened popup window, select database server and character set as well
 database administrator, password for this, username are specified
 and password for the Balances administrator. There is instruction text
 at each point that pops up when the cursor is moved over the field.
 both the database and the database's administrator and acceptor.

 Click the Install button when you have filled in all the fields.

10. Then Saldi is installed, which can be seen from the header on it
 page that appears, where the Next button is clicked.

11. Now you will be asked to login with the information you provided
 when creating the database:

 saldi [ Database Name ]
 Username [ SALDI administrator username ]
 Password [ SALDI administrator password ]

 You can always log in with the same information later to
 manage accounts.

12. Now the Administration menu for Balances appears. Here you choose
 "Create saldi".

13. On the "Create account" page, enter the name of the new account,
 username for an administrator with associated password.
 In addition, you must choose whether to create a standard
 chart of accounts for the new accounts. If in doubt, choose the one,
 as it suits most small businesses and it is possible
 to correct it later. Click Save/Update.

14. After some time, a message appears that the accounts are
 created and activated. Click the OK button.

15. In the Administration menu that appears, select "Show accounts" and i
 the newly created account is selected from the list.

16. Now it is time to set up the accounts in Balances. See the link
 "User guide" under "Support" on the website https://saldi.dk/
```

</details>

---

<details>
<summary><strong>Option B — Docker Compose Installation</strong></summary>

### Requirements

- [Docker](https://docs.docker.com/get-docker/) with Docker Compose

No system-level PHP, Apache, or PostgreSQL needed — everything runs in containers.

### Steps

**1.** Clone the repository:

```bash
git clone https://github.com/DANOSOFT/saldi.git
cd saldi
```

**2.** *(Optional)* Configure email. By default, all outgoing emails are caught
by the built-in Mailpit service and **not delivered**. To send real emails,
copy the example env file and fill in your SMTP credentials:

```bash
cp .env.example .env
# Edit .env with your SMTP details
```

**3.** Build and start all services:

```bash
docker compose up --build
```

**4.** Open your browser and go to `http://localhost:5000/saldi`. Allow pop-ups if prompted.

**5.** The installation wizard opens. Fill in:

| Field | Value |
|---|---|
| Database server | PostgreSQL |
| Character set | UTF8 |
| Server name | `postgres` ← use this, **not** localhost |
| Database name | `saldi` |
| DB administrator | `user` |
| DB password | `password` |
| SALDI admin | your choice |

Click **Install**.

**6.** After installation completes, click **Næste** and log in.

**7.** Choose **"Opret Regnskab"**, fill in account details, click **Gem/Opdater**.

**8.** Select the account from **"Vis regnskaber"** and begin using it.

### Services

| Service | URL | Purpose |
|---|---|---|
| SALDI | http://localhost:5000/saldi | Main application |
| Adminer | http://localhost:5001 | Database browser |
| Mailpit | http://localhost:5002 | Inspect outgoing emails (default) |

**Adminer login:** System `PostgreSQL` · Server `postgres` · User `user` · Password `password`

### Email Configuration

Set these in your `.env` file to deliver real emails (see `.env.example`):

| Variable | Description | Default |
|---|---|---|
| `SMTP_HOST` | SMTP server hostname | `mailpit` (caught locally) |
| `SMTP_PORT` | SMTP port | `1025` |
| `SMTP_FROM` | Envelope from address | `noreply@example.com` |
| `SMTP_USER` | SMTP username | *(none)* |
| `SMTP_PASS` | SMTP password | *(none)* |
| `SMTP_TLS` | `on`/`off` | `off` |
| `SMTP_STARTTLS` | `on`/`off` — use for port 587 | `off` |

**Gmail** (requires a [Google App Password](https://support.google.com/accounts/answer/185833)):
```env
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_STARTTLS=on
SMTP_USER=you@gmail.com
SMTP_PASS=your-app-password
```

**Office 365:**
```env
SMTP_HOST=smtp.office365.com
SMTP_PORT=587
SMTP_STARTTLS=on
SMTP_USER=you@company.com
SMTP_PASS=yourpassword
```

### Useful Commands

```bash
docker compose up -d          # Start in background
docker compose down           # Stop all services
docker compose logs -f web    # Follow web server logs
docker compose exec web bash  # Shell into the web container
docker compose up --build     # Rebuild after Dockerfile changes
```

</details>

---

*© Saldi.dk ApS — https://saldi.dk/*

---

## SALDI Assist (support chatbot)

The support chatbot is embedded in the shell (`index/main.php`) and issues a
signed context token per session from `includes/saldi_assist_token.php` so the
assistant knows the tenant, user rights, licences and the screen the user is
on. It never receives record data. Set two variables in the web server
environment (never in a PHP file under the web root):

```
SALDI_ASSIST_CONTEXT_SECRET=<32+ random characters, shared with the chatbot operator>
SALDI_ASSIST_KID=k2026a            # key id; rotate by adding a new kid on both sides
# optional, for a test instance of the chatbot:
SALDI_ASSIST_WIDGET_URL=https://wuweiworkai.com/chaty-v2/widget.js
```

With Docker Compose these are passed through from `.env` (see
`docker-compose.yml`). Verify after deployment: log in, open the shell and
call `includes/saldi_assist_token.php?embed_session=<32 hex chars>` in the
same browser session; it must answer `{"token": "v1.<kid>...."}`. Anonymous
calls answer `{"error": "no_session"}` with HTTP 401.
