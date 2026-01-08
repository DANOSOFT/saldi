#!/bin/bash
# Script to sync FEFO/Expiry Date feature files to server
# Run this script to copy all modified files to the server

SERVER="saul@ssl12.saldi.dk"
PORT="50012"
SCP_OPTS="-P $PORT -o PreferredAuthentications=password -o PubkeyAuthentication=no"
REMOTE_BASE="/var/www/html/saul"

echo "=== Syncing FEFO/Expiry Date files to server ==="
echo ""

# 1. includes/std_func.php
echo "1. Syncing includes/std_func.php..."
scp $SCP_OPTS /home/saul/saldi/includes/std_func.php $SERVER:$REMOTE_BASE/includes/

# 2. includes/stdFunc/std_func.php
echo "2. Syncing includes/stdFunc/std_func.php..."
scp $SCP_OPTS /home/saul/saldi/includes/stdFunc/std_func.php $SERVER:$REMOTE_BASE/includes/stdFunc/

# 3. stdFunc/std_func.php
echo "3. Syncing stdFunc/std_func.php..."
scp $SCP_OPTS /home/saul/saldi/stdFunc/std_func.php $SERVER:$REMOTE_BASE/stdFunc/

# 4. includes/ordrefunc.php
echo "4. Syncing includes/ordrefunc.php..."
scp $SCP_OPTS /home/saul/saldi/includes/ordrefunc.php $SERVER:$REMOTE_BASE/includes/

# 5. debitor/ordre.php
echo "5. Syncing debitor/ordre.php..."
scp $SCP_OPTS /home/saul/saldi/debitor/ordre.php $SERVER:$REMOTE_BASE/debitor/

# 6. debitor/batch.php
echo "6. Syncing debitor/batch.php..."
scp $SCP_OPTS /home/saul/saldi/debitor/batch.php $SERVER:$REMOTE_BASE/debitor/

# 7. finans/ordre.php
echo "7. Syncing finans/ordre.php..."
scp $SCP_OPTS /home/saul/saldi/finans/ordre.php $SERVER:$REMOTE_BASE/finans/

# 8. lager/lagerflyt.php
echo "8. Syncing lager/lagerflyt.php..."
scp $SCP_OPTS /home/saul/saldi/lager/lagerflyt.php $SERVER:$REMOTE_BASE/lager/

# 9. lager/varekort.php
echo "9. Syncing lager/varekort.php..."
scp $SCP_OPTS /home/saul/saldi/lager/varekort.php $SERVER:$REMOTE_BASE/lager/

# 10. kreditor/modtag.php
echo "10. Syncing kreditor/modtag.php..."
scp $SCP_OPTS /home/saul/saldi/kreditor/modtag.php $SERVER:$REMOTE_BASE/kreditor/

# 11. admin/admin_settings.php
echo "11. Syncing admin/admin_settings.php..."
scp $SCP_OPTS /home/saul/saldi/admin/admin_settings.php $SERVER:$REMOTE_BASE/admin/

# 12. lager/productCardIncludes/showExpirySettings.php
echo "12. Syncing lager/productCardIncludes/showExpirySettings.php..."
scp $SCP_OPTS /home/saul/saldi/lager/productCardIncludes/showExpirySettings.php $SERVER:$REMOTE_BASE/lager/productCardIncludes/

# 13. lager/lister/expiring_items.php
echo "13. Syncing lager/lister/expiring_items.php..."
scp $SCP_OPTS /home/saul/saldi/lager/lister/expiring_items.php $SERVER:$REMOTE_BASE/lager/lister/

# 14. lager/lister/batch_overview.php
echo "14. Syncing lager/lister/batch_overview.php..."
scp $SCP_OPTS /home/saul/saldi/lager/lister/batch_overview.php $SERVER:$REMOTE_BASE/lager/lister/

# 15. lager/lister/topLineVarer.php
echo "15. Syncing lager/lister/topLineVarer.php..."
scp $SCP_OPTS /home/saul/saldi/lager/lister/topLineVarer.php $SERVER:$REMOTE_BASE/lager/lister/

# 16. importfiler/tekster.csv (translations)
echo "16. Syncing importfiler/tekster.csv..."
scp $SCP_OPTS /home/saul/saldi/importfiler/tekster.csv $SERVER:$REMOTE_BASE/importfiler/

echo ""
echo "=== Sync complete! ==="
echo "Now test by visiting varekort.php to trigger migration if needed."
