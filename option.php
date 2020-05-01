<?php

include ('function.php');

$canalblog_opt_name = "canalblog_show";

if(isset($_POST["submit"])){
    $canalblog_show = $_POST[$canalblog_opt_name];
    update_option($canalblog_opt_name, $canalblog_show);

    echo '<div class="wrap">';

    canalblog_importer_page();

    echo '</div>';

    echo '<div id="message" class="updated fade"><p>Options Updates</p></div>';
} else {
    $canalblog_show = get_option($canalblog_opt_name);
}
?>
<div class="wrap">
<h2>Welcome To Blog import administration panel</h2>
<br />
<br />
<div class="canalblog-left">
    <fieldset>
        <legend>Canalblog importation</legend><br/>
        <form method="post" action="">
            <input type="checkbox" name="<?php echo $canalblog_opt_name; ?>" <?php echo $canalblog_show?"checked='checked'":""; ?> /> &nbsp; <span> Run blog import </span>
            <br /><br />
            <p><input type="submit" value="Run" class="button button-primary" name="submit" /></p>
        </form>
    </fieldset>
</div>
