# Driftsbesked (operations notice)

Shows a popup in Saldi for every user on a server, without a deploy.

- **On:** put the text in `driftsbesked.txt` in the folder above the installations, e.g. `/var/www/html/driftsbesked.txt` on ssl3 (readable by www-data). First line = heading, the rest = body.
  `cp /var/www/html/live/doc/driftsbesked-eksempel.txt /var/www/html/driftsbesked.txt`
- **Off:** `rm /var/www/html/driftsbesked.txt`
- Shown once per browser session in the main window (`index/main.php`); OK hides it. Editing the text shows it again.
- Test: `php tests/test_driftsbesked.php`
