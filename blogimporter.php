<?php
/**
 * Crunchify Hello World Plugin is the simplest WordPress plugin for beginner.
 * Take this as a base plugin and modify as per your need.
 *
 * @package Blog Importer Plugin
 * @author Le Sanglier des Ardennes
 * @license GPL-2.0+
 * @link https://crunchify.com/tag/wordpress-beginner/
 * @copyright 2017 Crunchify, LLC. All rights reserved.
 *
 *            @wordpress-plugin
 *            Plugin Name: Blog Importer Plugin
 *            Plugin URI: https://github.com/nekrofage
 *            Description: Blog importer
 *            Version: 3.0
 *            Author: Le Sanglier des Ardennes
 *            Author URI: https://github.com/nekrofage
 *            Text Domain: blog-importer
 *            Contributors: Nekrofage
 *            License: GPL-2.0+
 *            License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 */


function getRemoteHtml($uri)
  {
    $http = new Wp_HTTP();

    $result = $http->get($uri, array(
        'redirection' =>  5,
        'timeout' =>      20,
        'user-agent' =>   'WordPress/'.get_bloginfo('version')
      ));

    return $result['body'];
  }

function getDomDocumentFromHtml(&$html, $stripJavaScript = true)
  {
    //removing all scripts (we don't want them)
    if (!!$stripJavaScript)
    {
      $html = preg_replace('#<script[^>]*>.*<\/script>#siU', '', trim($html));
    }

    /*
     * Fixing UTF8 encoding on existing full document
     * @props ricola
     */
    if (preg_match('/<head/iU', $html))
    {
      $html = preg_replace('/(<head[^>]*>)/siU', '\\1<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />', $html);
    }
    /*
     * Fixing UTF8 on HTML fragment
     */
    else
    {
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




function getRemoteXpath($uri, $xpath_query, &$html = '') {
    $dom = getRemoteDomDocument($uri, $html);
    $xpath = new DOMXPath($dom);

    $result = $xpath->query($xpath_query);
    unset($http, $dom, $xpath);
    return $result;
}


function getContentFromUri($uri) {
    $html = null;

    $query = getRemoteXpath($uri, "//div[@id='content']", $html);
    $dom = new DomDocument();
    $dom->appendChild($dom->importNode($query->item(0), true));

    return array('dom' => $dom, 'html' => $html);
}


function extractTitle($xpath) {
    $title = '';
    $attempt = $xpath->query("//div[@class='blogbody']//a[@rel='bookmark']");


    if ($attempt->length) {
      $title = $attempt->item(0)->getAttribute('title');
    }
    else {
      $title = $xpath->query("//div[@class='blogbody']//a[@name]/following-sibling::*")->item(0)->textContent;
    }

    return trim($title);
  }

function extractPostDate($xpath) {
    
    $dateResult = $xpath->query('//div[@class="dateheader"]');
    $timeResult = $xpath->query('//span[@class="articledate"]');

    $datePost = $dateResult->item(0)->textContent;

    $timePost = $timeResult->item(0)->textContent;   

    list($day, $month, $year) =  preg_split("/ /", $datePost);

    list($hour, $minutes) = preg_split("/:/", $timePost);
    

    $months = array('janvier' => 1, 'février' => 2, 'mars' => 3, 'avril' => 4, 'mai' => 5, 'juin' => 6, 'juillet' => 7, 'août' => 8, 'septembre' => 9, 'octobre' => 10, 'novembre' => 11, 'décembre' => 12);


    return sprintf('%s-%s-%s %s:%s', $year, $months[$month], $day, $hour, $minutes);
  }


function extractPostContent($uri) {

    $html =  file_get_contents($uri, false, $context);


    $dom = new DOMDocument();
    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    $div = $xpath->query('//div[@class="articlebody"]');
    $div = $div->item(0);

    $contentPost = $dom->saveHTML($div);

    return $contentPost;
}


function savePost(DomDocument $dom, $uri) {
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

    $post_id = wp_insert_post( $my_post );
    return $post_id;
}


function cleanupMediaUri ($uri) {
    return array('uri' => $uri, 'original_uri' => $uri, 'size' => 'full');
}


function extractMediaUris($html, $path_media) {
    $remote_uris = array();
    $dom = getDomDocumentFromHtml($html);
    $xpath = new DomXpath($dom);

    foreach ($xpath->query("//a[contains(@href, 'canalblog.com/storagev1') or contains(@href, 'storage.canalblog.com') or contains(@href, 'canalblog.com/docs')]") as $link) {
      array_push($remote_uris, cleanupMediaUri($link->getAttribute('href')));
      $path_href = $link->getAttribute('href');
    
    echo $path_href . " " .  $path_media . "/" . basename($path_href);
      copy( $path_href, $path_media . "/" . basename($path_href));  
    }

    foreach ($xpath->query("//img[contains(@src, 'canalblog.com/storagev1') or contains(@src, 'storage.canalblog.com') or contains(@src, 'canalblog.com/images')]") as $link) {
      array_push($remote_uris, cleanupMediaUri($link->getAttribute('src')));
      $path_src = $link->getAttribute('src');
      copy( $path_src, $path_media . "/" . basename($path_src));
    }

}


function saveMedias($post) {
    $post_content = $post->post_content;
    $post_date = $post->post_date;

    $date_media = str_replace("-", "/", substr( $post_date, 0, 8));

    echo $date_media;

    $path_media = "/home/util01/public_html/onmjfootsteps/wp-content/uploads/" . $date_media;

    if (!is_dir($path_media)) {
        mkdir($path_media, 0777, true); 
    }

    $remote_uris = extractMediaUris($post_content, $path_media);

}



function blogimporter_add_menu() {
	add_submenu_page("options-general.php", "Blog Importer Plugin", "Blog Importer Plugin", "manage_options", "blog-importer", "blog_importer_page");
}
add_action("admin_menu", "blogimporter_add_menu");


function blog_importer_page()
{

    //$uri = "http://www.onmjfootsteps.com/archives/2020/04/23/38227331.html";
    $uri = "http://www.onmjfootsteps.com/archives/2014/04/09/29630116.html";

    echo "<div class=\"wrap\"> ";
    echo "uri: " . $uri; 
    echo "</div>";



    $data = array();
    $remote = getContentFromUri($uri);

    $post_id = savePost($remote['dom'], $uri);

    saveMedias(get_post($post_id));
}
