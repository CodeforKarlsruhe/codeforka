<?php
session_start();

require 'vendor/autoload.php';

// spitaler
define("CONFDIR","/home/akugel/files/freundeskreis-news/data/");
// else 
//define("CONFDIR","/var/www/files/fknews/data/");
define("CONF","config.ini");
define("LOG","fk.log");
define("LPRIO",1); // minimal log priority

//Captcha symbols
define("SYM",str_split("0123456789abcdefABCDEF"));

// --------------------------------------------------
// helpers
// --------------------------------------------------
// log file
function mlog($msg,$prio = 0) {
  if ($prio >= LPRIO){
	  $ts = date(DATE_RFC2822);
	  file_put_contents(LOG, $ts . " : " . $msg . PHP_EOL, FILE_APPEND);
  }
}

// create nsel item
function newsSel($id,$name) {
	$n  = "<div>\r\n";
	$n .= "<label class=\"check\" >";
	$n .= $name . "</label>\r\n";
	$n .= "<button type=\"button\" ";
	$n .= "id=\"nbi" . $id . "\"";
	$n .= " class=\"check checkoff\" ";
	$n .= "onclick=\"chk(" . $id . ");return false\"></button>\r\n";
	// works, but we need the index first
	//$n .= "onclick=\"chk(nitems[" . $id . "]);return false\"></button>\r\n";
	$n .= "</div>\r\n";
	return $n;
};


function captchaGen() {
	$cap = array();
	// 6 characters code
    $val = "";
    for ($i = 0; $i < 6; $i++) 
        $val .= SYM[rand(0,count(SYM)-1)];

	$options = array('display_value' => $val,
                     // id  not needed without storage
		             //'captchaId'     => rand(1,1000), // random id.
		             'image_width'   => 240,
		             'image_height'  => 80,
                     'noise_level'   => 3,    // default 2
                     'perturbation'  => 0.7,  // default .85
		             'no_session'    => true,
		             'no_exit'       => true,
		             'use_database'  => false,
		             'send_headers'  => false);

    try {
	$image = new Securimage($options);
	// create image
	ob_start();   // start the output buffer
	$image->show(); // output the image so it is captured by the buffer
	$imgBinary = ob_get_contents(); // get contents of the buffer
	ob_end_clean(); // turn off buffering and clear the buffer
	file_put_contents("img/captcha.png",$imgBinary);
	$src = "data:image/png;base64,";
	$src .= base64_encode($imgBinary);
	
    // set code in session
	mlog("Captcha code: " . $val);
	$_SESSION["captcha"] = $val;
	$result["captcha"] = $src;

    } catch (Exception $e) {
        mlog("Captcha failed: " . $e->getMessage(),99);
		$_SESSION["captcha"] = "xxxxxxx"; // not in symbol list
        $src = "/img/captcha.png";
    }

	return $src;

}


// --------------------------------------------------
// config setup
// --------------------------------------------------
// ini file on uberspace is elsewhere
$cfg = array();

try {
  if (!isset($_SERVER['HTTP_HOST']) or 
        ($_SERVER['HTTP_HOST'] == "127.0.0.1") or 
        ($_SERVER["SERVER_NAME"] == "127.0.0.1")) {
      $cfg = parse_ini_file("data/" . CONF, false);
      $cfg["datadir"] = "data/";
  } else {
	  mlog("External host: " . $_SERVER['HTTP_HOST']);
      $cfg = parse_ini_file(CONFDIR . CONF, false);
      $cfg["datadir"] = CONFDIR;
  }


  // don't print in real program
  //mlog("Config items: " . count($cfg));
  //print_r($cfg);
  mlog("Config SMTP Port: " . $cfg["smtpport"]);
} catch (Exception $e) {
  mlog("Config error: " . $e->getMessage(), 99);
  exit("Error");
}

// --------------------------------------------------
// email functions
// --------------------------------------------------

function composeMsg($text,$files){

   // create attachment parms   
   $atts = array();
   foreach($files AS $f) {
        array_push($atts,array("name"=>$f["name"], "data"=>$f["data"]));
        mlog("File " . $f["name"]);
   }
 
   // there are only two supported content types:
   // application/pdf and text/plain

   $mime_boundary = "-----=" . md5(time());

   // add lines to header for multipart after MIME-Version: 1.0 !
   $header = "Content-Type: multipart/mixed;\r\n";
   $header .= " boundary=\"".$mime_boundary."\"\r\n";
 
   // create content. text part is text/plain   
   $content  = "This is a multi-part message in MIME format.\r\n\r\n";
   $content .= "--".$mime_boundary."\r\n";
   $content .= "Content-Type: text/plain; charset=utf-8\r\n";
   $content .= "Content-Transfer-Encoding: 8bit\r\n\r\n";
   $content .= $text."\r\n\r\n";
 
   // create attachements
   // only pdf allowed
 
   foreach($atts AS $a) {
         // make 76 chacter long lines
		 // Note: data in this application if from fetch 
         // and already b64 encoded!
         // if we had a file, we would need to load and b64 encode first 
         mlog("Start chunk");
         $data = chunk_split($a['data']);
         mlog("End chunk");
         $content.= "--".$mime_boundary."\r\n";
         $content.= "Content-Type: application/pdf;\r\n";
         //$content.= "\t name=\"".$a['name']."\"\r\n";
         /* also b64-encode name and filename
         ** Note the "B", that marks Base64 encoded payload, 
         ** as opposed to the "Q" which marks "Q-encoded" payload. 
         **The latter could be faked by URL-encoding the string and 
         ** replacing all "%" characters with "=".
         */
         $content.= "\t name=\"" . "=?utf-8?b?".base64_encode($a['name'])."?=" . "\";\r\n";
         $content.= "Content-Transfer-Encoding: base64\r\n";
         $content.= "Content-Disposition: attachment;\r\n";
         // $content.= "\tfilename=\"".$a['name']."\";\r\n\r\n";
         $content.= "\tfilename=\"" . "=?utf-8?b?".base64_encode($a['name'])."?=" . "\";\r\n\r\n";
         $content.= $data."\r\n\r\n";
   }
   $content .= "--".$mime_boundary."--"; 

   $msg = array();
   $msg["hdr"] = $header;
   $msg["body"] = $content;
   
   return $msg;

}



function sendSmtp($to, $subj , $body, $files)
{
  global $cfg;
  // set time zone for date usage later on
  date_default_timezone_set("Europe/Berlin");

  // we can test if $to is an arraya or a scalar via is_array($to);
  if (!is_array($to)) {
	// convert to array
	mlog("Converting: " . $to);
	$to = array($to);
  }

  $host = $cfg["smtphost"];
  $port = $cfg["smtpport"];
  $from = $cfg["from"];
  //$subj = "Informationen vom Freundeskreis für Lebensenergie Karlsruhe";
  $signature = "\r\n\r\n--\r\n";
  $signature .= "Zum Abmelden vom Newsletter oder für weitere Infos zu den ";
  $signature .= "Veranstaltungen schreibe eine Mail an ";
  $signature .= $from . "\r\n";
  $body .= $signature;

  // test only
  //$to = "ak@akugel.de";

  // if we have attachements, compose message
  if (count($files) > 0){
    $msg = composeMsg($body,$files);
    if (count($msg) == 2){
        mlog("Mail composed");
    } else {
        mlog("Composing mail failed: " . json_encode($msg),1);
        return false;
    }
  } else {
	$msg = array();
  }

  // mail count
  $cnt = 0;

  try {
      $socket_options = array('ssl' => array('verify_peer_name' => true));
      /* Create a new Net_SMTP object. */
	  /* set timeout to 15 seconds (from 0) */
	  /* must be smaller than fetch timeout on client ! */
      if (! ($smtp = new Net_SMTP($host, $port, null, false, 15, $socket_options))) {
          throw new Exception("Unable to instantiate Net_SMTP object\n");
      }

      // Debug-Modus einschalten
      $smtp->setDebug(false);

      mlog("Start SMTP connect");
      /* Connect to the SMTP server. */
      if (PEAR::isError($e = $smtp->connect())) {
          throw new Exception($e->getMessage() . "\n");
      }

      // authenticate
      if (PEAR::isError($smtp->auth($cfg["smtpuser"], $cfg["smtppass"]))) {
          throw new Exception("Unable to authenticate\n");
      }

	  // send loop
	  foreach ($to as $t) {

		  mlog("Start SMTP from");
		  /* Send the 'MAIL FROM:' SMTP command. */
		  if (PEAR::isError($smtp->mailFrom($from))) {
		      throw new Exception("Unable to set sender to <$from>\n");
		  }
		  /* Address the message to each of the recipients. */
		  if (PEAR::isError($res = $smtp->rcptTo($t))) {
		      throw new Exception("Unable to add recipient <$t>: " . $res->getMessage() . "\n");
		  }

		  // set headers
		  // options: "Content-Transfer-Encoding: 8bit \r\n";
		  // "MIME-Version: 1.0 \r\n";
		  $header = "From: Freundeskreis KA <".$from.">\r\n";
		  $header .= "To: ".$t ."\r\n";
		  $date = date(DATE_RFC2822);
		  $header .= "Date: ".$date . "\r\n";
          // subject needs to be encoded separately
		  $header .= "Subject: "."=?utf-8?b?".base64_encode($subj)."?="."\r\n";
		  $header .= "MIME-Version: 1.0\r\n";

		  // if we have attachements, compose message
		  if (count($msg) == 2){
			  $header .= $msg["hdr"];
			  $body = $msg["body"];
		  } else {
		      $header .= "Content-Encoding: 8bit\r\n"; // don't use quoted printable here ....
		      $header .= "Content-Type: text/plain; charset=utf-8\r\n";
		      // leave body unchanged
		  }

		  $header .= "\r\n"; // terminating header

		  mlog("Smtp: Start data");

		  /* Send the message. */
          
		  if (PEAR::isError($smtp->data($body, $header))) {
		      throw new Exception("Unable to send email\n");
		  }

          $cnt += 1;

		  mlog("Smtp: End data");
	  }

      mlog("Emails sent: " . $cnt,1);

      /* Disconnect from the SMTP server. */
      $smtp->disconnect();
  } catch (Exception $e) {
      mlog("Error: ".$e->getMessage(),99);
      return false;
  }

  return true;
}

function sendNewsletter($news) {
	global $cfg;
	mlog("Sending newsletter " . $news);

	// read address file
    try {
		$a = getCsv($cfg["datadir"] . $cfg["newsaddr"]);

		// composer current address list
		$addr = array();
		foreach ($a as $aa) {
			//mlog(json_encode($aa));
			if ($aa[$news] == "1") 
				array_push($addr,$aa["email"]);
		}
		mlog("Adresses for  " . $news . " : " . count($addr));
		mlog("Collected adresses: " . json_encode($addr));
	} catch (Exception $e) {
		mlog("Error : " . $e->getMessage(),1);
		return false;
	}
    return sendSmtp($addr, $_SESSION["news"]["subject"] ,
		$_SESSION["news"]["body"], $_SESSION["news"]["files"]);
}

function getCsv($name) {
    $lines = array();
    $csv = array();
    if (file_exists($name) and is_readable($name)) {
        //  fgetcsv ( resource $handle [, int $length = 0 [, string $delimiter = "," 
        // [, string $enclosure = '"' [, string $escape = "\\" ]]]] ) : array
        if (($handle = fopen($name, "r")) !== FALSE) {
            while (($data = fgetcsv($handle, 0, ",")) !== FALSE) {
                $num = count($data);
                // check empty line: 1 field, value Null
                if ( ($num == 1) and ($data[0] == Null) ) {
                    continue;
                }
                array_push($lines,$data);
             }
         }
         fclose($handle);
        $hdr = array_shift($lines); // first line is header
        // make email lowercase
        $hdr[0] = strtolower($hdr[0]);
        foreach ($lines as $row) {
          array_push($csv,array_combine($hdr, $row));
        }
        mlog("Csv rows: " . count($csv));
    } else {
        mlog("File error on " . $name,99);
    }
    return $csv;
}

function checkPwd($email,$pwd){
    global $cfg;
    $h = hash_hmac("sha256",$pwd,$cfg["newscrypt"]);
    $c = getCsv($cfg["datadir"] . $cfg["newsadmin"]);
    if (0 == count($c))
        return false;

    foreach ($c as $row) {
        if ((strtolower($row["email"]) == strtolower($email)) and ($row["hash"] == $h)) {
            // correct
            return true;
        }
    }
    return false;

}
// --------------------------------------------------
// flight config
// --------------------------------------------------
Flight::set('flight.log_errors', true);
Flight::set('flight.views.path', './');


// --------------------------------------------------
// handlers
// --------------------------------------------------

// login
function login ($payload) {
  $result = array();
  $result["status"] = "0";
  if (!isset($payload["email"]) || !isset($payload["email"]) || !isset($payload["captcha"])){
    $msg = "Fehlende Angaben";
    mlog($msg,1);
    $result["msg"] = $msg;
  } else {

      mlog(json_encode($payload));
      $mail = $payload["email"];
      $pwd = $payload["pwd"];
      $code = $payload["captcha"];

      $e = filter_var($mail, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW | FILTER_SANITIZE_SPECIAL_CHARS);
      $email = filter_var($e, FILTER_VALIDATE_EMAIL);

      $email = strtolower($email);

      if (!$email) {
          $result["msg"] = "Ungültige Email";
      } else {
        // check captcha
        if ($code != $_SESSION["captcha"]) {
          $result["msg"] = "Ungültiges Captcha";
        } else {
            // login
            if (checkPwd($email,$pwd)) {
                // force logout
                logout($payload);
                //
                $result["msg"] = "Anmeldung OK";
                $result["status"] = "1";
                // with forced logout we cannot use the session counter ...
                /*
                // ---- some other stuff ---
                if (isset ($_SESSION["item"])) {
                    $_SESSION["item"] += 1; 
                    $result["session"] = session_id();
                } else {
                    $_SESSION["item"] = "123";
                }
                $result["sessitem"] = $_SESSION["item"];
                // ----
                */
                // sending welcome mesage works ... don't 
                //sendSmtp($email,"Hello ");
            } else {
              $result["msg"] = "Passwort oder Email falsch";
            }
        }
     }
  }
  // save or destroy user variable
  if ($result["status"] == "1"){
      $_SESSION["user"] = $email; // save user
      mlog("After login: " . json_encode($_SESSION));
  } else {
      if (isset($_SESSION["user"]))
        unset($_SESSION["user"]);
	// generate a new captcha
	$result["captcha"] = captchaGen();
  }
  return($result);
};

// logout
function logout($payload) {
    session_regenerate_id(False);
    session_unset();
    mlog("new session: " . json_encode($_SESSION));
    $result = array();
    $result["status"] = "1";
    $result["msg"] = "Nicht angemeldet";
    // generate a new captcha
    $result["captcha"] = captchaGen();
    return($result);
};

// fetch test
function ftest($payload) {
	$result = array();
	// respond if loggedin or not
	if (isset($_SESSION["user"])) {
	  $result["status"] = "1";
      $result["msg"] = "Angemeldet";
	} else {
	  $result["status"] = "0";
      $result["msg"] = "Nicht angemeldet";
    }

	// generate a new captcha
	$result["captcha"] = captchaGen();
    
  return $result;
};

// mail test
function test($payload) {
  $result = array();
  $result["status"] = "0";
  // payload has subject, body and files
  // files is (possibly empty) array with name and data
  if (!isset($_SESSION["user"])) {
      $result["msg"] = "Nicht angemeldet";
  } else {
      if (!isset($payload["subject"]) || !isset($payload["body"]) || !isset($payload["files"])){
        $msg = "Fehlende Angaben";
        mlog($msg,1);
        $result["msg"] = $msg;
      } else {
          mlog($payload["subject"] . "," . $payload["body"]);
          foreach ($payload["files"] as $f) {
            mlog($f["name"] . ": " . strlen($f["data"]));
            //mlog($f["data"]);
            // normally starts with:
            /*
            data:application/pdf;base64,JVBERi0xLjUKJcOkw7zDtsOfCjIgMCBvY
            */
			// cut on client side ...
          }
          if (sendSmtp($_SESSION["user"], $payload["subject"] , $payload["body"], $payload["files"])) {
              $result["status"] = "1";
              $result["msg"] = "Test-Email wurde verschickt";
              // save in session
              $_SESSION["news"] = array("subject" => $payload["subject"], 
                    "body" => $payload["body"], "files" => $payload["files"]);
          } else {
              $result["msg"] = "Fehler beim Mailversand";
          }
      }
  }
  return($result);
};

// prepare
function prepare($payload) {
  $result = array();
  $result["status"] = "0";
  // payload has selected
  if (!isset($_SESSION["user"])) {
      $result["msg"] = "Nicht angemeldet";
  } else {
      if (!isset($payload["selected"])){
        $msg = "Fehlende Angaben";
        mlog($msg,1);
        $result["msg"] = $msg;
      } else {
          mlog("Selected " . $payload["selected"]);
		  // should check if selection exista in keys ..
		  // but we cannot save hdr from / as session is destroyed on login
		  // use exception ...
		  try {
			  // send confirm code. 
			  $code = rand(100000,999999);
			  mlog("Code: " . $code); 
		      if (sendSmtp($_SESSION["user"], "Bestätigung für Newsletter " . $payload["selected"], 
				  "Bitte diesen Code eingeben: \r\n" . $code . "\r\n", array())) {
		          $result["status"] = "1";
		          $result["msg"] = "Mail mit Bestätigungscode wurde verschickt";
		          // save in session
				  $_SESSION["selected"] = $payload["selected"];
				  $_SESSION["code"] = $code;
		      } else {
				  $msg = "Fehler beim Mailversand";
				  mlog($msg);
				  $result["msg"] = $msg;
		      }
			} catch (Exception $e) {
				  $msg = $e->getMessage();
				  mlog($msg,1);
				  $result["msg"] = $msg;
			}
	  }
  }
  return($result);
};

// confirm
function confirm($payload) {
  $result = array();
  $result["status"] = "0";
  // payload has code
  if (!isset($_SESSION["user"])) {
      $result["msg"] = "Not logged in";
  } else {
      if (!isset($payload["code"])){
        $msg = "Missing parms";
        mlog($msg,1);
        $result["msg"] = $msg;
      } else {
          mlog("Code " . $payload["code"]);
		  if ($_SESSION["code"] == $payload["code"]) {
			mlog("Code OK, sending mails ...");
			// got to send all ...
			if (sendNewsletter($_SESSION["selected"])) {
			    // invalidate code. 
			    $_SESSION["code"] = rand(100000,999999);
				$result["status"] = "1";
				$msg = "Newsletter " . $_SESSION["selected"] . " versendet";
				mlog($msg);
				$result["msg"] = $msg;
			} else {
				$msg = "Keine gültige Auswahl";
				mlog($msg,1);
				$result["msg"] = $msg;
			}
		  } else {
		    $msg = "Ungültiger Code";
		    mlog($msg);
		    $result["msg"] = $msg;
		  }

      }
  }

  return($result);
};

// --------------------------------------------------
// routing
// --------------------------------------------------
// Routing in Flight is done by matching a URL pattern with a callback function.

// ---- errors ------
Flight::map('notFound', function () {
  // Handle not found
  echo "Flight: not found";
});

// ---- routing ----

// general POST 
Flight::route('POST /fetch', function () {
  mlog("POST");
  $request = Flight::request();
  if (!isset($request->data->type) || !isset($request->data->payload)) {
	  mlog("Missing data on request: " . json_encode($request),1);
	  $result = array();
	  $result["status"] = "0";
	  $result["msg"] = "Wir haben leider ein Problem ...";
  } else {
	  mlog("Request: " . json_encode($request));
	  $type = $request->data->type;
	  mlog("Type: " . $type);
	  $payload = $request->data->payload;
	  switch ($type) {
		case "ftest":
		  $result = ftest($payload);
		  break;
		case "test":
		  $result = test($payload);
		  break;
		case "login":
		  $result = login($payload);
		  break;
		case "logout":
		  $result = logout($payload);
		  break;
		case "prepare":
		  $result = prepare($payload);
		  break;
		case "confirm":
		  $result = confirm($payload);
		  break;

	  default:
    	mlog("Post error");
	      $result = array();
	      $result["status"] = "0";
	      $result["msg"] = "Wir haben leider ein Problem (400)...";
          $err=400; // bad request
	  }
  }
  if (isset ($err))
    Flight::json($result,$code=$err);
  else
    Flight::json($result);
});

// all other POSTs 
Flight::route('POST /*', function () {
  mlog("Invalid POST");
  $result = array();
  $result["status"] = "0";
  $result["msg"] = "Wir haben leider ein Problem (404)...";
  Flight::json($result,$code=404);  // not found
});


// root to main html file
Flight::route('/', function () {
	global $cfg;
	// destroy old session here and create a new cookie
	mlog("home");
	session_regenerate_id(False);
	session_unset();
	mlog("new session");

	// generate a new captcha
	captchaGen();

	// render newsletter selection from address table
	$c = getCsv($cfg["datadir"] . $cfg["newsaddr"]);
	$nsel = "";
	$nitems = "const nitems = [";
	if (0 < count($c)) {
		mlog("Array size: " . count($c) . "\r\n" . json_encode($c));
		$hdr = array_keys($c[0]);
		mlog("Headers: " . json_encode($hdr));
		// we assume first hdr is email
		if ("email" != $hdr[0]) {
			mlog("invalid header format");
		} else {
			// create nsel and sitems
			for ($i = 1; $i < count($hdr); $i++){
				$nsel .= newsSel($i,$hdr[$i]);
				$nitems .= "\"" . $hdr[$i] . "\"";
				if ($i <  count($hdr) - 1)
					$nitems .= ",";
			}
			$nitems .= "]";
			mlog("Selections:\r\n" . $nsel);
		}
	} else {
		mlog("No select items",1);
	}
	flight::render("main.php",array('nsel' => $nsel,"nitems" => $nitems));
	/*
	$f = file_get_contents("main.html");
	echo $f;
	*/
});



/*
Flight::before('json', function () {
    //header('Access-Control-Allow-Origin: https://news.freundeskreis-ka.de');
    header('Access-Control-Allow-Origin: *');
    //header('Access-Control-Allow-Methods: GET,POST,OPTIONS');
    //header('Access-Control-Allow-Headers: X-Requested-With, Content-Type, Accept, Origin,Authorization');
});
*/


// start framework
Flight::start();

// for uberspace: don't put ? > at end of file !!!!!

