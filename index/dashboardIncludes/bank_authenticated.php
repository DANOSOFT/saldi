<style>
#bank-stat-wrapper a {
    flex: 1;
}
.auth-status-icon img {
    width: 100px;
    height: 100px;
}
</style>
<div style="
    min-width: 100px;
    background-color: #fff;
    border-radius: 5px;
    box-shadow: rgba(99, 99, 99, 0.2) 0px 2px 8px 0px;
    padding: 1.4em 2em;
">
    <h4 style="margin: 0; color: #999">Bank Status</h4>
    <br>
    <div id="bank-stat-wrapper" style="
        display: flex;
        gap: 2em;
        flex-wrap: wrap;
    ">
        <?php include('../bank_integration/includes/auth_check_icon.php'); ?>
    </div>
</div>