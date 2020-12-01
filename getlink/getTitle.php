<?php


function getLinkFromPage($url) {


    $file = 'titles.txt';

    @$dom = new DOMDocument();
    @$dom->loadHTMLFile($url);

    $xpath = new DOMXpath($dom);

    $nodeList = $xpath->query('//h2/a/@title');

    foreach ($nodeList as  $exemple) {
         $link = $exemple->nodeValue;
         echo $link . "\n";
         file_put_contents($file, $link . "\n", FILE_APPEND | LOCK_EX);
    }   
    

}

@unlink('titles.txt');

$listMonth = file('days.txt');


foreach ($listMonth as $month) {
    $uri = $month;

    getLinkFromPage(trim($month));   


}
?>
