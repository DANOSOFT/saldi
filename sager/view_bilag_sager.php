<?php
@session_start();
$s_id=session_id();

include("../includes/connect.php");
include("../includes/online.php");
include("../includes/std_func.php");

$id=if_isset($_GET['sag_id']);

global $db;

$url  = isset($_SERVER['HTTPS']) ? 'https://' : 'http://';
$url .= $_SERVER['SERVER_NAME'];
$url .= htmlspecialchars($_SERVER['REQUEST_URI']);
$urlstr = dirname(dirname($url));
//echo $urlstr; exit();
$x=0;
	$q=db_select("select * from bilag where assign_to='sager' and assign_id='$id' order by datotid asc",__FILE__ . " linje " . __LINE__);
	while($r=db_fetch_array($q)) {
		$bilag_id[$x]=$r['id'];
		$bilag_title[$x]=$r['navn'];
		$bilag_filtype[$x]=$r['filtype'];
		$x++;
	}
//print_r($bilag_filtype); exit();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Stillads</title>
  <link rel="stylesheet" href="../css/idangerous.swiper.css">
  <!--[if lt IE 9]>
		<script src=\"http://ie7-js.googlecode.com/svn/version/2.1(beta4)/IE9.js\"></script>
	<![endif]-->
  <style>
/* Swiper Styles */
html {
  height: 100%;
}
body {
  margin: 0;
  font-family: Arial, Helvetica, sans-serif;
  font-size: 13px;
  line-height: 1.5;
  position: relative;
  height: 100%;
}

.image_style {
	max-height: 95%;
	max-width: auto;
	border: none;
}
.image_style {
	max-height: auto;
	max-width: 90%;
	border: none;
}
.docs_style {
	height: 95%;
	width: 90%;
	border: none;
}
.swiper-container {
  width: 100%;
  height: 100%;
  color: #fff;
  background: #111;
  text-align: center;
}
/*
.red-slide {
  background: #ca4040;
}
.blue-slide {
  background: #4390ee;
}
.orange-slide {
  background: #ff8604;
}
.green-slide {
  background: #49a430;
}
.pink-slide {
  background: #973e76;
}
*/
.swiper-slide .title {
  font-style: italic;
  font-size: 42px;
  margin-top: 80px;
  margin-bottom: 0;
  line-height: 45px;
}/*
.pagination {
  position: absolute;
  z-index: 20;
  left: 10px;
  bottom: 10px;
}
*/
.pagination {
  position: absolute;
  left: 0;
  text-align: center;
  bottom:5px;
  width: 100%;
}
/*
.swiper-pagination-switch {
  display: inline-block;
  width: 8px;
  height: 8px;
  border-radius: 8px;
  background: #222;
  margin-right: 5px;
  opacity: 0.8;
  border: 1px solid #fff;
  cursor: pointer;
}
.swiper-visible-switch {
  background: #aaa;
}
.swiper-active-switch {
  background: #fff;
}
*/

.swiper-pagination-switch {
  display: inline-block;
  width: 10px;
  height: 10px;
  border-radius: 10px;
  background: #999;
  box-shadow: 0px 1px 2px #555 inset;
  margin: 0 3px;
  cursor: pointer;
}
.swiper-active-switch {
  background: #fff;
}
.swiper-container .arrow-left {
	display: inline-block;
  background: url(../img/arrows.png) no-repeat left top;
  position: absolute;
  z-index: 20;
  left: 10px;
  top: 50%;
  margin-top: -15px;
  width: 17px;
  height: 30px;
  cursor: pointer;
}
.swiper-container .arrow-right {
	display: inline-block;
  background: url(../img/arrows.png) no-repeat left bottom;
  position: absolute;
  z-index: 20;
  right: 10px;
  top: 50%;
  margin-top: -15px;
  width: 17px;
  height: 30px;
  cursor: pointer;
}
.swiper-container .rotate-right {
	display: inline-block;
  background: url(../img/image-rotate.png) no-repeat;
  position: absolute;
  z-index: 20;
  right: 10px;
  top: 90%;
  margin-top: -15px;
  width: 32px;
  height: 32px;
  cursor: pointer;
}
  </style>
</head>
<body>
		<div class="swiper-container">
			<a class="arrow-left" href="#"></a> 
			<a class="arrow-right" href="#"></a>
			<a class="rotate-right" href="#"></a>
			<div class="swiper-wrapper">
			<?php
			for ($y=0;$y<count($bilag_id);$y++) {
				if ($bilag_filtype[$y]=='pdf'||$bilag_filtype[$y]=='doc'||$bilag_filtype[$y]=='docx'||$bilag_filtype[$y]=='xls'||$bilag_filtype[$y]=='xlsx'||$bilag_filtype[$y]=='ppt'||$bilag_filtype[$y]=='pptx') {
					$style="docs_style";
					print "<div class=\"swiper-slide\">\n";
							print "
							<object data=\"http://docs.google.com/viewer?url=$urlstr%2Fbilag%2F$db%2F$id%2F$bilag_id[$y].$bilag_filtype[$y]&amp;embedded=true\" class=\"$style\">
								<p>Din browser kan ikke vise denne fil. Hent filen herunder.</p>
								<a href=\"../bilag/$db/$id/$bilag_id[$y].$bilag_filtype[$y]\">$bilag_title[$y]</a> 
							</object>\n";
							/*
							print "<embed src=\"http://docs.google.com/viewer?url=http%3A%2F%2Fgateway.saldi.dk%2Fudvikling%2Fbilag%2F$db%2F$id%2F$bilag_id[$y].$bilag_filtype[$y]&amp;embedded=true\" class=\"$style\">
							*/
					print "</div>\n";
				} else {
					$style="image_style";
					print "<div class=\"swiper-slide\">\n";
							/*$type = "";
							if ($bilag_filtype[$y]=='doc') $type = 'type="application/msword"';
							if ($bilag_filtype[$y]=='docx') $type = 'type="application/vnd.openxmlformats-officedocument.wordprocessingml.document"';
							if ($bilag_filtype[$y]=='xls') $type = 'type="application/vnd.ms-excel"';
							if ($bilag_filtype[$y]=='xlsx') $type = 'type="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"';
							if ($bilag_filtype[$y]=='ppt') $type = 'type="application/vnd.ms-powerpoint"';
							if ($bilag_filtype[$y]=='pptx') $type = 'type="application/vnd.openxmlformats-officedocument.presentationml.presentation"';*/
							print "
							<object data=\"../bilag/$db/$id/$bilag_id[$y].$bilag_filtype[$y]\" class=\"$style\">
								<p>Din browser kan ikke vise denne fil. Hent filen herunder.</p>
								<a href=\"../bilag/$db/$id/$bilag_id[$y].$bilag_filtype[$y]\">$bilag_title[$y]</a>
							</object>\n";
							/*
							print "<embed src=\"../bilag/$db/$id/$bilag_id[$y].$bilag_filtype[$y]\" class=\"$style\">
							*/
					print "</div>\n";
				}
			}
			?>
    <!--
      <div class="swiper-slide red-slide">
        <div class="title">Slide 1</div>
      </div>
      <div class="swiper-slide blue-slide">
        <div class="title">Slide 2</div>
      </div>
      <div class="swiper-slide orange-slide">
        <div class="title">Slide 3</div>
      </div>
      <div class="swiper-slide green-slide">
        <div class="title">Slide 4</div>
      </div>
      <div class="swiper-slide pink-slide">
        <div class="title">Slide 5</div>
      </div>
      <div class="swiper-slide red-slide">
        <div class="title">Slide 6</div>
      </div>
      <div class="swiper-slide blue-slide">
        <div class="title">Slide 7</div>
      </div>
      <div class="swiper-slide orange-slide">
        <div class="title">Slide 8</div>
      </div>
      -->
     
    </div>
    <div class="pagination"></div>
  </div>
  <script src="../javascript/jquery-1.8.0.min.js"></script>
  <script src="../javascript/idangerous.swiper-2.0.min.js"></script>
  <script src="../javascript/jQueryRotate.js"></script>
  <script type="text/javascript">
  var mySwiper = new Swiper('.swiper-container',{
    pagination: '.pagination',
    loop:true,
    grabCursor: true,
    paginationClickable: true
  })
  $('.arrow-left').on('click', function(e){
    e.preventDefault()
    mySwiper.swipePrev()
  })
  $('.arrow-right').on('click', function(e){
    e.preventDefault()
    mySwiper.swipeNext()
  })
  
 // $("object.image_style").rotate({animateTo:360});
 /*
		var angle = 0;
		setInterval(function(){
					angle+=3;
				$("object.image_style").rotate(angle);
		},50);
		*/
  
 // funktion som roterer billed 90gr højre om
	var value = 0
	$(".rotate-right").rotate({ 
		bind: 
			{ 
					click: function(){
							value +=90;
							$("object.image_style").rotate({ animateTo:value})
					}
			} 
		
	});
	
  </script>
</body>
</html>