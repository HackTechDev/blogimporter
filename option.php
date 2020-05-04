<?php
?>

<div class="wrap">
<h2>Welcome To Blog import administration panel</h2>
<br />
<br />

<?php
include ('function.php');

$server_path = "/home/util01/public_html/onmjfootsteps";
$website_path = "http://dev.onmjfootsteps.com";

$canalblog_opt_name = "canalblog_show";

if(isset($_POST["submit"])){
    $articleBatchNumber = file_get_contents( $server_path . "/wp-content/plugins/blogimporter/article_batch.txt");

    $canalblog_show = $_POST[$canalblog_opt_name];
    update_option($canalblog_opt_name, $canalblog_show);

    echo '<div class="wrap">';

    echo "Article batch number: " . $articleBatchNumber . "<br/>";

    $listArticleFile = $website_path . "/wp-content/plugins/blogimporter/article/article_" .  sprintf("%02d", $articleBatchNumber);

    canalblog_importer_page($listArticleFile);

    $articleBatchNumber = intval($articleBatchNumber) + 1;

    file_put_contents( $server_path . "/wp-content/plugins/blogimporter/article_batch.txt", $articleBatchNumber);

    echo '</div>';

    echo '<div id="message" class="updated fade"><p>Options Updates</p></div>';
} else {
    $canalblog_show = get_option($canalblog_opt_name);
}
?>
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
