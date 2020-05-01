<?php
/**
 * Blog importer WordPress plugin.
 * Import data from a blog to Wordpress
 *
 * @package   Blog Importer Plugin
 * @author    Le Sanglier des Ardennes <lesanglierdesardennes@gmail.com>
 * @license   GPL-2.0+
 * @link      https://github.com/nekrofage
 * @copyright 2020 Le Sanglier des Ardennes
 *
 * @wordpress-plugin
 *            Plugin Name: Blog Importer Plugin
 *            Plugin URI: https://github.com/nekrofage
 *            Description: Blog importer
 *            Version: 3.0
 *            Author: Le Sanglier des Ardennes
 *            Author URI: https://github.com/nekrofage
 *            Text Domain: blog-importer
 *            Contributors: Le Sanglier des Ardennes
 *            License: GPL-2.0+
 *            License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */

/**
 * Get remote HTML
 *
 * @author  Le Sanglier des Ardennes
 * @since   1.0
 * @version 1.0
 * @param $uri
 * @return $result
 */

function getRemoteHtml($uri)
{
    $http = new Wp_HTTP();

    $result = $http->get(
        $uri, array(
        'redirection' =>  5,
        'timeout' =>      20,
        'user-agent' =>   'WordPress/'.get_bloginfo('version')
        )
    );

    return $result['body'];
}


/**
 * Get Dom Document from HTML
 *
 * @author  Le Sanglier des Ardennes
 * @since   1.0
 * @version 1.0
 * @param
 * @return
 */

function getDomDocumentFromHtml(&$html, $stripJavaScript = true)
{
    //removing all scripts (we don't want them)
    if (!!$stripJavaScript) {
        $html = preg_replace('#<script[^>]*>.*<\/script>#siU', '', trim($html));
    }

    /*
     * Fixing UTF8 encoding on existing full document
     * @props ricola
     */
    if (preg_match('/<head/iU', $html)) {
        $html = preg_replace('/(<head[^>]*>)/siU', '\\1<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />', $html);
    } else {
        /*
        * Fixing UTF8 on HTML fragment
        */
        $html = sprintf('<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8" /></head><body>%s</body></html>', $html);
    }

    $dom = new DomDocument();
    $dom->preserveWhitespace = false;
    @$dom->loadHTML($html);

    return $dom;
}


function getRemoteDomDocument($uri, &$html = '')
{
    $html = getRemoteHtml($uri);
    $dom = getDomDocumentFromHtml($html);

    return $dom;
}


function getRemoteXpath($uri, $xpath_query, &$html = '')
{
    $dom = getRemoteDomDocument($uri, $html);
    $xpath = new DOMXPath($dom);

    $result = $xpath->query($xpath_query);
    unset($http, $dom, $xpath);
    return $result;
}


function getContentFromUri($uri)
{
    $html = null;

    $query = getRemoteXpath($uri, "//div[@id='content']", $html);
    $dom = new DomDocument();
    $dom->appendChild($dom->importNode($query->item(0), true));

    return array('dom' => $dom, 'html' => $html);
}


function extractTitle($xpath)
{
    $title = '';
    $attempt = $xpath->query("//div[@class='blogbody']//a[@rel='bookmark']");


    if ($attempt->length) {
        $title = $attempt->item(0)->getAttribute('title');
    } else {
        $title = $xpath->query("//div[@class='blogbody']//a[@name]/following-sibling::*")->item(0)->textContent;
    }

    return trim($title);
}


function extractPostDate($xpath)
{

    $dateResult = $xpath->query('//div[@class="dateheader"]');
    $timeResult = $xpath->query('//span[@class="articledate"]');

    $datePost = $dateResult->item(0)->textContent;

    $timePost = $timeResult->item(0)->textContent;

    list($day, $month, $year) =  preg_split("/ /", $datePost);

    list($hour, $minutes) = preg_split("/:/", $timePost);


    $months = array('janvier' => 1, 'février' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7, 'août' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12);


    return sprintf('%s-%s-%s %s:%s', $year, $months[$month], $day, $hour, $minutes);
}


function extractPostContent($uri)
{
    $html =  file_get_contents($uri, false, $context);


    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $div = $xpath->query('//div[@class="articlebody"]');
    $div = $div->item(0);

    $contentPost = $dom->saveHTML($div);

    return $contentPost;
}


function savePost(DomDocument $dom, $uri)
{
     $xpath = new DomXpath($dom);
     $data = array(
      'post_status' => 'publish',
     );

     preg_match('#/(\d+)\.html$#U', $uri, $matches);
     $blog_id = $matches[1];

     $data['post_title'] = extractTitle($xpath);
     $data['post_date'] = extractPostDate($xpath);

     $data['post_content'] = trim(extractPostContent($uri));

     $my_post = array();
     $my_post['post_title']    = $data['post_title'];
     $my_post['post_content']  = $data['post_content'];
     $my_post['post_status']   = 'publish';
     $my_post['post_author']   = 1;
     $my_post['post_date']     = $data['post_date'];
     $my_post['post_category'] = array(0);

     $post_id = wp_insert_post($my_post);
     return $post_id;
}


function cleanupMediaUri($uri)
{
    return array('uri' => $uri, 'original_uri' => $uri, 'size' => 'full');
}



function addAttachmentToPost($post_id, $filename)
{
    $filetype = wp_check_filetype(basename($filename), null);
    $wp_upload_dir = wp_upload_dir();
    $attachment = array(
        'guid'           => $wp_upload_dir['url'] . '/' . basename($filename),
        'post_mime_type' => $filetype['type'],
        'post_title'     => preg_replace('/\.[^.]+$/', '', basename($filename)),
        'post_content'   => '',
        'post_status'    => 'inherit'
    );

    $attach_id = wp_insert_attachment($attachment, $filename, $post_id);

    include_once ABSPATH . 'wp-admin/includes/image.php';

    $attach_data = wp_generate_attachment_metadata($attach_id, $filename);

    wp_update_attachment_metadata($attach_id, $attach_data);

    set_post_thumbnail($post_id, $attach_id);
}


function extractMediaUris($post_id, $html, $path_media)
{
    $remote_uris = array();
    $dom = getDomDocumentFromHtml($html);
    $xpath = new DomXpath($dom);

    foreach ($xpath->query("//a[contains(@href, 'canalblog.com/storagev1') or contains(@href, 'storage.canalblog.com') or contains(@href, 'canalblog.com/docs')]") as $link) {
        array_push($remote_uris, cleanupMediaUri($link->getAttribute('href')));
        $path_href = $link->getAttribute('href');
        copy($path_href, $path_media . "/" . basename($path_href));

        addAttachmentToPost($post_id, $path_media . "/" . basename($path_href));
    }

    foreach ($xpath->query("//img[contains(@src, 'canalblog.com/storagev1') or contains(@src, 'storage.canalblog.com') or contains(@src, 'canalblog.com/images')]") as $link) {
        array_push($remote_uris, cleanupMediaUri($link->getAttribute('src')));
        $path_src = $link->getAttribute('src');
        copy($path_src, $path_media . "/" . basename($path_src));
    }
}


function saveMedias($post)
{
    $post_content = $post->post_content;
    $post_date = $post->post_date;

    $date_media = str_replace("-", "/", substr($post_date, 0, 8));

    $path_media = "/home/util01/public_html/onmjfootsteps/wp-content/uploads/" . $date_media;

    if (!is_dir($path_media)) {
        mkdir($path_media, 0777, true);
    }

    $remote_uris = extractMediaUris($post->ID, $post_content, $path_media);
}


function updateURLMedia($post)
{
    $post_content = $post->post_content;
    $post_date = $post->post_date;

    $date_media = str_replace("-", "/", substr($post_date, 0, 8));
    $path_media = "/wp-content/uploads/" . $date_media;

    $remote_uris = array();
    $dom = getDomDocumentFromHtml($post_content);
    $xpath = new DomXpath($dom);

    foreach ($xpath->query("//a[contains(@href, 'canalblog.com/storagev1') or contains(@href, 'storage.canalblog.com') or contains(@href, 'canalblog.com/docs')]") as $link) {
        array_push($remote_uris, cleanupMediaUri($link->getAttribute('href')));
        $path_href = $link->getAttribute('href');
        $new_path = $path_media . basename($path_href);
        $post_content = str_replace($path_href, $new_path, $post_content);
    }

    foreach ($xpath->query("//img[contains(@src, 'canalblog.com/storagev1') or contains(@src, 'storage.canalblog.com') or contains(@src, 'canalblog.com/images')]") as $link) {
        array_push($remote_uris, cleanupMediaUri($link->getAttribute('src')));
        $path_src = $link->getAttribute('src');
        $new_path = $path_media . basename($path_src);
        $post_content = str_replace($path_src, $new_path, $post_content);
    }

    $post->post_content = $post_content;

    wp_update_post($post);
}


function extractCategory(DomDocument $dom, $uri)
{
    $xpath = new DomXpath($dom);

    $attempt = $xpath->query('//div[@class="breadcrumb"]');

    $breadcrumb = preg_split("/>/", $attempt->item(0)->textContent);

    return trim($breadcrumb[1]);
}


function applyCategory($post_id, $category_name)
{
    $categories = array();

    $category = array('cat_name' => $category_name);

    if (!is_category($category)) {
        $category_id = wp_insert_category($category);
    }
    $category_id = get_cat_ID($category_name);

    wp_set_post_categories($post_id, $category_id);
}


function extractTag(DomDocument $dom, $uri)
{
    $xpath = new DomXpath($dom);

    $tags = array();
    foreach ($xpath->query("//div[@class='blogbody']//div[@class='itemfooter']//a[@rel='tag']") as $tag) {
        $tags[] = $tag->textContent;
    }

    return $tags;
}


function applyTag($post_id, $tags)
{
    wp_set_post_tags($post_id, implode(',', $tags));
}




function saveComments(DomDocument $dom, $uri, $post_id)
{

  $previous_comment_id_sublevel1 = 0;
  $previous_comment_id_sublevel2 = 0;

  $xpath = new DomXpath($dom);
  $comments = $xpath->query('//li[contains(@class, "comment")]');


  foreach($comments as $comment){
    $data = array(
      'comment_approved' => 1,
      'comment_karma' => 1,
      'comment_post_ID' => $post_id,
      'comment_agent' => 'Blog Importer',
      'comment_author_IP' => '127.0.0.1',
      'comment_type' => 'comment',
      'comment_author_url' => ''
    );


    $html = $dom->saveHTML($comment);
    $html = str_replace(array("\n", "\r"), "<br/>", $html);

    preg_match('/div>(.+?)<div/', $html, $output_array);
    $content = $output_array[1];
    //echo $content;
    $data['comment_content'] = $content;

    preg_match('/level-([0-9])">/', $html, $output_array);
    $level = $output_array[1];
    //echo $level;

    preg_match('/Post(.*) par (.+?),/', $html, $output_array);
    $author = $output_array[2];
    //echo $author;
    $data['comment_author'] =  strip_tags($author);


    preg_match('/timeago" title="(.+?)"/', $html, $output_array);
    $datetime =$output_array[1];
    //echo $datetime;
    $data['comment_date'] =  str_replace('T', ' ', $datetime);
    $data['comment_date_gmt'] = $data['comment_date'];

    $data['comment_post_ID'] = $post_id;

    if($level == 1) {
        $previous_comment_id_sublevel1 = wp_insert_comment($data);
    }

    if($level == 2) {
        $data['comment_parent'] = $previous_comment_id_sublevel1;
        $previous_comment_id_sublevel2= wp_insert_comment($data);
    }

  }

}





function blogimporter_add_menu()
{
    add_submenu_page("options-general.php", "Blog Importer Plugin", "Blog Importer Plugin", "manage_options", "blog-importer", "blog_importer_page");
}


add_action("admin_menu", "blogimporter_add_menu");


function blog_importer_page()
{
    $listarticle = file('http://dev.onmjfootsteps.com/wp-content/plugins/blogimporter/canalblog_liste_article.10.txt');

    echo "<div class=\"wrap\"> ";

    foreach ($listarticle as $article) {
        echo $article . "<br>";
        $uri = $article;
        $remote = getContentFromUri(trim($uri));


        $tag = extractTag($remote['dom'], trim($uri));

        $category = extractCategory($remote['dom'], trim($uri));

        $post_id = savePost($remote['dom'], trim($uri));

        saveComments($remote['dom'], $remote['html'], $post_id);

        applyCategory($post_id, $category);

        applyTag($post_id, $tag);

        saveMedias(get_post($post_id));

        updateURLMedia(get_post($post_id));

        file_put_contents ("/home/util01/public_html/onmjfootsteps/wp-content/plugins/blogimporter/canalblog_debug.txt", $article,  FILE_APPEND | LOCK_EX); 

    }

    echo "</div>";
}
