<?php 
$SourceFile = "SaldiFormular-design.fodg";

if (file_exists($SourceFile)) {
	process_xml();
} else {
	echo "<b>FEJL: $SourceFile ikke fundet!</b>";
}

function process_xml() {  // simplexml_load_file( string $filename) PHP5+ 
    $docs = simplexml_load_file("SaldiFormular-design.fodg");
    print_r($docs);
    echo "<br>Nu starter vi:";
    echo __line__;
    foreach ($docs->category as $category) {
    echo __line__;
      echo ": <h2>" . $category["name"] . "</h2>";
      echo "<ul>";
        foreach ($category->doc as $doc) {
          echo " <li><a href='" . $doc->link . "'>" . $doc->name . "</a></li>";
        }
      echo "</ul>";
    }
    echo __line__;
}
?>
