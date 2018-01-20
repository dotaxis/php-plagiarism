<?php
include_once('simplehtmldom_1_5/simple_html_dom.php');
include_once("../wp-load.php");
require_once('WordPress_PostController/class.postcontroller.php');
require_once('class.ripper.php');
set_time_limit(0); // fix the god damn timeout
ignore_user_abort(TRUE); // lol just kidding 1&1 has a 40s time limit
// ... and there's nothing you can do about it
date_default_timezone_set('MST');
$time = date("m.d.y(H:i)"); //get the date and time for logging



if ($_POST['rip']) {

	$url = $_POST['url'];
	$title = $_POST['title'];

	//Decide what site this is from
	if (stripos($url, 'viralnova.com/'))
		$Ripper = new ViralNova;
	else if (stripos($url, 'buzzfeed.com/'))
		$Ripper = new BuzzFeed;
	else { die("Not a valid URL fuck off plzthankz."); }

	//Load our document
	$Ripper->Load($url);

	//brief fail-safe.
	if(!$Ripper->doc || !is_object($Ripper->doc) || !isset($Ripper->doc->nodes))
		die("Something went wrong here.");


	//create a wordpress post
	$Poster = new PostController;
	$Poster->set_title($title);
	$Poster->set_post_state( "draft" );
	$Poster->create();
	$post_id = $Poster->get_var('current_post_id');
	$Poster->search('id', $post_id);

	//Run all of our ripper functions
	$Ripper->Strip();
	$Ripper->Save_Attributes();
	$Ripper->Rip_Images($post_id);
	$Ripper->Rebuild();
	$Ripper->Trim();
	$Ripper->Spin();


	//finish adding information for the post and update it
	$Poster->add_category(12);
	$Poster->set_type("post");
	$Poster->set_content($Ripper->doc);

	$Poster->update();

	//log to logs/$time.html
	$logfile = "logs/" . $time . ".html"; //lets use html logs because they're easier to read
	file_put_contents($logfile, $Poster->PrettyPrintAll());

	//unset vars
	$Poster = null;
	$Ripper = null;
	echo("All done! Good job!<br/>
		<a href='$logfile'>Log entry</a>");

}
?>

<html>
<head>
<title>Rip This!</title>
</head>
<body style="text-align:center;">
<form method=POST>
	<input type="text" name="title" value="post title here" onclick="if(this.value == 'post title here') this.value = ''" /><br />
	<input type="text" name="url" value="page url here" onclick="if(this.value == 'page url here') this.value = ''" /><br />
	<input type="submit" name="rip" onclick="alert('Please wait 5-10 seconds before closing this tab.\nYou don\'t need to wait for it to load completely.')" />
</form>
<br/><br/>
</body>
</html>
