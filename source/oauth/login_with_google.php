<?php
/* OAuth Login With Google
 * Copyright 2012 Manuel Lemos
 * Copyright 2014 Subin Siby
 * Login to Open with google
 * Thank you Manuel Lemos
*/

session_start();
/* Include the files */
$OP->inc('inc/oauth/http.php');
$OP->inc('inc/oauth/oauth_client.php');

/* $_GET['c'] have the URL that should be redirected to after oauth logging in */
$_GET['c']					=		isset($_GET['c']) ? $_GET['c'] : "";
$hostParts					=		parse_url($_GET['c']);
$hostParts['host'] 		= 		isset($hostParts['host']) ? $hostParts['host']:"";

/* We add the session variable containing the URL that should be redirected to after logging in */
$_SESSION['continue']	=	isset($_SESSION['continue']) ? $_SESSION['continue']:"";

if(($_GET['c']=='' && $_SESSION['continue']=='') || $hostParts['host']!=CLEAN_HOST){
 	/* The default Redirect URL open.subinsb.com/home */
 	$_SESSION['continue'] 	=	HOST . "/home";
}else{
 	/* Or the URL that was sent */
 	$_SESSION['continue']	=	$_GET['c'];
}

/* We make an array of Database Details */
$databaseDetails 				  = 	unserialize(DATABASE);
/* The PHP OAuth Library requires some special items in array, so we add that */
$databaseDetails["password"] = 	$databaseDetails["pass"];
$databaseDetails["socket"]	  = 	"/var/lib/mysql/mysql.sock";

$client = new oauth_client_class;
$client->database 		= 	$databaseDetails;
$client->server 			= 	'Google';
$client->offline 			= 	true;
$client->debug				= 	true;
$client->debug_http 		= 	true;
$client->redirect_uri 	= 	HOST . '/oauth/login_with_google';
$client->client_id 		= 	'private_value';
$client->client_secret 	= 	'private_value';
$client->scope 			= 	'https://www.googleapis.com/auth/userinfo.email https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/plus.me';
if(($success = $client->Initialize())){
 	if(($success = $client->Process())){
  		if(strlen($client->authorization_error)){
   		$client->error = $client->authorization_error;
   		$success = false;
  		}elseif(strlen($client->access_token)){
   		$success = $client->CallAPI('https://www.googleapis.com/oauth2/v2/userinfo', 'GET', array(), array('FailOnAccessError' => true), $user);
  		}
 	}
 	$success = $client->Finalize($success);
}
if($client->exit){
 	$OP->ser("Something Happened", "<a href='".$client->redirect_uri."'>Try Again</a>");
}
/* A function to validate date */
function validateDate($date){
    	$d = DateTime::createFromFormat('Y-m-d', $date);
    	return $d && $d->format('Y-m-d') == $date;
}
if($success){
    $location =  $_SESSION['continue'];
    $email 	  =  $user->email;
    $name  	  =  $user->name;
    $gender	  =  $user->gender;
    /* Make it DD/MM/YYYY format */
    if(isset($user->birthday) && validateDate($user->birthday)){
    	$birthday =  date('d/m/Y', strtotime($user->birthday));
    }
    $image	  =  $user->picture;
    	
    /* We now check if the E-Mail is already registered */
    $sql 		  =  $OP->dbh->prepare("SELECT `id` FROM `users` WHERE `username` = ?");
    $sql->execute(array($email));
    
    /* The Cookie Expiration Time */
    $cookieTime  = time() * 60 + 1200;
    	
    /* $sql->rowCount() will be 0 if user doesn't exist */
    if($sql->rowCount() !=0 ){
     	/* Get the user id */
     	$id = $sql->fetchColumn();
     	/* Since user exist, we log him in */
     	setcookie("curuser", $id, $cookieTime, "/", CLEAN_HOST);
     	setcookie("wervsi", $OP->encrypter($id), $cookieTime, "/", CLEAN_HOST);
     	$OP->redirect($location);
    }else{
     	$randomSalt	 = $OP->randStr(25);
     	$siteSalt	 = "private_value";
     	$salted_hash = hash('sha256', "private_value" . $siteSalt . $randomSalt);
     	/* An array containing user details that will made in to JSON */
     	$userArray   = array(
     	 	"joined"	=> date("Y-m-d H:i:s"),
     		"gen"		=> $gender, /* gen = gender (male/female) */
     	 	"img"		=> $image /* img = image */
     	);
     	if(isset($birthday)){
     		$userArray["birth"] = $birthday;
     	}
     	$json	= json_encode($userArray);
     			
     	/* Now, register the user */
    	$sql	= $OP->dbh->prepare('INSERT INTO `users` (`username`, `password`, `psalt`, `name`, `udata`) VALUES(:email, :password, :salt, :name, :json)');
     	$sql->bindParam(":email", $email);
     	$sql->bindParam(":password", $salted_hash);
     	$sql->bindParam(":salt", $randomSalt);
    	$sql->bindParam(":name", $name);
     	$sql->bindParam(":json", $json);
     	$sql->execute();
     			
     	/* The make the cookies for the new registered user and redirect */
     	$sql  = $OP->dbh->prepare("SELECT `id` FROM `users` WHERE `username` = ?");
     	$sql->execute(array($email));
     	$id   = $sql->fetchColumn();
     		
     	/* Cookie Set */
     	setcookie("curuser", $id, $cookieTime, "/", CLEAN_HOST);
     	setcookie("wervsi", $OP->encrypter($id), $cookieTime, "/", CLEAN_HOST);
     	$OP->redirect($location);
	}
}
if(!$success){
?>
 <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
 <html>
  <head>
   <title>Error</title>
  </head>
  <body>
   <h1>OAuth client error</h1>
   <pre>Error: <?php echo HtmlSpecialChars($client->error); ?></pre>
  </body>
 </html>
<?}?>