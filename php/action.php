<?php

  // pull flight and medoo via composer autoload

  require 'vendor/autoload.php';

  // test with http -f POST 127.0.0.1:8000/index1.php  email=123@ab.cd  lang=de

  // --------------------------------------------------
  // log function
  // --------------------------------------------------
  define("LOG","okl.log");
  define("LPRIO",0); // minimal log priority

  // log function to file
  function mlog($msg,$prio = 0) {
    if ($prio >= LPRIO){
  	  $ts = date(DATE_RFC2822);
  	  file_put_contents(LOG, $ts . " : " . $msg . PHP_EOL, FILE_APPEND);
    }
  }

  // --------------------------------------------------
  // encrypt function
  // --------------------------------------------------
  // encrypt using openssl
  // cert is like public key like "file://path/to/file.pem";
  // msg is plaintext message
  // return is hex encoded string

  function encrypt($msg,$cert) {
    $pkmsg = "";  // variable for return value
    try  {
      $pubkey = openssl_pkey_get_public ( $cert);
      openssl_public_encrypt ( $msg , $pkmsg , $pubkey);
      mlog("Encryption OK");
      return bin2hex($pkmsg);
    } catch (Exception $e) {
      mlog($e->getMessage(),3);
      return Null;
    }
  }

  // --------------------------------------------------
  // config setup
  // --------------------------------------------------
  // ini file on uberspace is elsewhere
  $cfg = array();
  //$cfg = parse_ini_file("/home/akugel/files/kdg/kdg.ini",false);
  // $cfg = parse_ini_file("kdg.ini",false);
  try {
      if (!isset($_SERVER['HTTP_HOST']) or ($_SERVER['HTTP_HOST'] == "okl")) {
          $cfg = parse_ini_file("news.ini", false);
      } else {
          //$cfg = parse_ini_file("/mnt/rid/74/42/510237442/htdocs/files/okla/news.ini", false);
          $cfg = parse_ini_file("news.ini", false);
      }

      // don't print in real program
      //echo "Config items: " . count($cfg) . "\n";
      //print_r($cfg);
  } catch (Exception $e) {
      mlog("Config error",9);
      die("Config Error");
  }



  // --------------------------------------------------
  // database setup
  // --------------------------------------------------

  // Using Medoo namespace
  use Medoo\Medoo;

  $database = null;

  try {
      $database = new Medoo([
          'database_type' => 'mysql',
          'database_name' => $cfg["dbname"],
          'server' =>  $cfg["dbserv"],
          'username' => $cfg["dbuser"],
          'password' => $cfg["dbpass"]
      ]);

      // don't print in real program
      // print_r($database->info());
  } catch (Exception $e) {
    mlog("DB error",9);
      die("DB Error");
  }

  // --------------------------------------------------
  // smtp send
  // --------------------------------------------------
  function sendSmtp($to, $subj, $body)
  {
      global $cfg;
      // set time zone for date usage later on
      date_default_timezone_set("Europe/Berlin");

      $host = $cfg["smtphost"];
      $port = $cfg["smtpport"];
      $from = $cfg["smtpfrom"];
      $date = date(DATE_RFC2822);

      // test only
      //$to = "ak@akugel.de";

      mlog("Start smtp for " . $to);
      try {
          $socket_options = array('ssl' => array('verify_peer_name' => true));
          /* Create a new Net_SMTP object. */
          if (! ($smtp = new Net_SMTP($host, $port, null, false, 0, $socket_options))) {
              throw new Exception("Unable to instantiate Net_SMTP object\n");
          }

          // Debug-Modus einschalten
          $smtp->setDebug(false);

          /* Connect to the SMTP server. */
          if (PEAR::isError($e = $smtp->connect())) {
              throw new Exception($e->getMessage() . "\n");
          }

          // authenticate
          if (PEAR::isError($smtp->auth($cfg["smtpuser"], $cfg["smtppass"]))) {
              throw new Exception("Unable to authenticate\n");
          }

          /* Send the 'MAIL FROM:' SMTP command. */
          if (PEAR::isError($smtp->mailFrom($from))) {
              throw new Exception("Unable to set sender to <$from>\n");
          }
          /* Address the message to each of the recipients. */
          if (PEAR::isError($res = $smtp->rcptTo($to))) {
              throw new Exception("Unable to add recipient <$to>: " . $res->getMessage() . "\n");
          }

          // set headers
          // options: "Content-Transfer-Encoding: 8bit \r\n";
          // "MIME-Version: 1.0 \r\n";
          $header = "From: OK Lab Karlsruhe <".$from.">\r\nTo: ".$to;
          $header .= "\r\nSubject: ".$subj."\r\nDate: ".$date;
          $header .= "\r\nMIME-Version: 1.0";
          $header .= "\r\nContent-Encoding: 8bit"; // don't use quoted printable here ....
          $header .= "\r\nContent-Type: text/plain; charset=utf-8";
          $header .= "\r\n"; // terminating header
          /* Send the message. */
          //if (PEAR::isError($smtp->data($subj . "\r\n" . $body))) {
          if (PEAR::isError($smtp->data($body, $header))) {
              throw new Exception("Unable to send email\n");
          }
          /* Disconnect from the SMTP server. */
          $smtp->disconnect();
      } catch (Exception $e) {
          mlog("Error: ".$e->getMessage(), 4);
          return false;
      }

      mlog("Done smtp for " . $to);
      return true;
  }

  // --------------------------------------------------
  // smtp send
  // --------------------------------------------------

  function sendOptIn($to,$lang)
  // return <0 on error, 0 on existing mail, 1 on mail sent
  {
      global $cfg;
      global $database;

      $cert = $cfg["cert"];
      $mailDigest = encrypt($to,$cert);
      if (Null == $mailDigest) {
        mlog("Creating mail digest failed",2);
        return -1;
      }
      mlog($mailDigest);

      // generate the codes for mail
      $mailcode =  hash_hmac("sha256",$to,$cfg["seed"]);

      try {
          $user = $database->select("users", ["id","confirmed"], ["code" => $mailcode]);
          if ($user && count($user) > 0) {
            mlog("Existing user ".$to, 0);
            // check confirmed, if not set proceed
            if ($user[0]["confirmed"] == 1) {
              mlog("already confirmed");
              return 0;
            }
          } else {
              mlog("Inserting user ".$to, 0);
              $newUser = array();
              $newUser["email"] = $mailDigest;
              $newUser["code"] = $mailcode;
              $newUser["lang"] = $lang;
              $database->insert("users", $newUser);
              $err = $database->error();
              // if OK, $err[0] is 0
              // on error, $err[] != 0 and $err[2] is error string
              if ($err[0] != 0) {
                  mlog($err[2],2);
                  return -1;
              }
          }
          mlog("User inserted or existing w/o confirm", 0);
      } catch (Exception $e) {
          mlog("Error".$e->getMessage(), 4);
          return -1;
      }

      // mail
      // add confirmation link
      $mode = "https://";
      if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off') {
          $mode = "http://";
      }
      // create text and subject
      if ($lang == "en") {
        $subj = "Subscription to the OK Lab Karlsruhe newsletter";
        $body = "Thank you for subcribing to our newsletter\r\n.";
        $body .= "Please click on the following link to confirm\r\n";
        $body .= $mode . $_SERVER["SERVER_NAME"] . "/actions/index.php/?code=\"";
        $body .= $mailcode . "&lang=en\r\n\r\n";
      } else {
        $subj = "Anmeldung für den Newsletter aus dem OK Lab Karlsruhe";
        $body = "Vielen Dank für Ihre Anmeldung für unseren Newsletter.\r\n";
        $body .= "Bitte klicken Sie zur Bestätigung auf folgenden link:\r\n";
        $body .= $mode . $_SERVER["SERVER_NAME"] . "/actions/index.php/?confirm=";
        $body .= $mailcode . "&lang=de\r\n\r\n";
      }

      try {
          mlog("Sending mail: ".$body, 0);
          if (!sendSmtp($to, $subj, $body)) {
              throw new Exception("SMTP failed");
          }
      } catch (Exception $e) {
          mlog("Error".$e->getMessage(), 4);
          return -1;
      }
      return 1;
  }

  // --------------------------------------------------
  // confirmation
  // --------------------------------------------------
  function confirm($a)
  // confirmation: check mailcode and update user if OK
  {
    global $database;
	  global $cfg;
    mlog("Confirmation code ".$a, 0);

		try {
		  // find codes, use only non-confirmed users
		  $user = $database->select("users", ["id"], ["code" => $a,"confirmed" => 0]);
		  if (!$user or (count($user) == 0)) {
		      mlog("Mailcode not valid");
          return false;
		  }
		  // mailcode is OK, update
		  $uid = $user[0]["id"];
      mlog("user id: " . $uid);
      // create a new code, independent from email
      $code = hash("md5", uniqid($uid, true));
      $cert = $cfg["cert"];
		  $database->update("users", ["confirmed" => 1,"code" => $code], ["id" => $uid]);
		  $err = $database->error();
		  if ($err[0] != 0) {
        mlog("Update error: " . $err[2]);
        return false;
		  }
    } catch (Exception $e) {
      mlog($e->getMessage());
      return false;
    }
    mlog("Confirmation set");
    return true;
  }

  // --------------------------------------------------
  // cancellation
  // --------------------------------------------------
  function remove($a)
  // remove: check mailcode and remove if OK
  {
    global $database;
	  global $cfg;
    mlog("Removal code ".$a, 0);

		try {
		  // find codes, use only non-confirmed users
		  $user = $database->select("users", ["id"], ["code" => $a,"confirmed" => 1]);
		  if (!$user or (count($user) == 0)) {
		      mlog("Mailcode not valid");
          return false;
		  }
		  // mailcode is OK, remove
		  $uid = $user[0]["id"];
      mlog("user id: " . $uid);
		  $database->delete("users", ["id" => $uid]);
		  $err = $database->error();
		  if ($err[0] != 0) {
        mlog("Update error: " . $err[2]);
        return false;
		  }
    } catch (Exception $e) {
      mlog($e->getMessage());
      return false;
    }
    mlog("User removed");
    return true;
  }

  // --------------------------------------------------
  // download active users
  // --------------------------------------------------
  function down($a)
  // remove: check password and create list
  {
    global $database;
	  global $cfg;

    if ($a !== $cfg["down"])
      return json_encode([]);

		try {
		  // find codes, use only non-confirmed users
		  $user = $database->select("users", ["email","code","lang"], ["confirmed" => 1]);
		  if (!$user or (count($user) == 0)) {
        mlog("NO users found");
        return json_encode([]);
		  } else {
        mlog("Users: ". count($user));
        // encrypt link code for download
        $cert = $cfg["cert"];
        for ($l=0;$l<count($user);$l++)
          $user[$l]["code"] = encrypt($user[$l]["code"],$cert);
        return json_encode($user);
      }
    } catch (Exception $e) {
      mlog($e->getMessage());
      return json_encode([]);
    }
  }


  // --------------------------------------------------
  // PHP response
  // --------------------------------------------------
// Request: http://www.example.org/suche?stichwort=wiki&ausgabe=liste

  $lang = $email = "";
  // default error if lang not set or wrong
  $err = "Es ist leider ein Problem aufgetreten / Unfortunately we discovered a problem";

  try {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
      mlog(json_encode($_POST));
      if (empty($_POST["lang"])) {
        throw new Exception("0");
      } else {
        $lang = trim($_POST["lang"]);
        if (("de" != $lang) and ("en" != $lang)) {
          throw new Exception("0");
        }
      }

      // we require lang to be OK
      if (empty($_POST["email"])) {
        throw new Exception("2");
      } else {
        $email = filter_var($_POST["email"], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW | FILTER_SANITIZE_SPECIAL_CHARS);
        // check if e-mail address is well-formed
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
          throw new Exception("2");
        }
        mlog("Lang: " . $lang . ", mail: " . $email);
        // check if email exists in database, else append
        // send mail
        $res = sendOptIn($email,$lang);
        switch ($res) {
          case -1: // database error
            throw new Exception("1");
          break;
          case 0: // already subscribed
            throw new Exception("3");
          break;
          case 1:
            // worked!
            if ($lang == "de") {
              $respTitle = "Anmeldung";
              $respSubtitle = "";
              $respContent = "Vielen Dank für Ihre Anmeldung.<br>";
              $respContent .= "Wir haben Ihnen eine Email mit einem Bestätigungslink geschickt<br>";
              $respContent .= "Bitte überprüfen Sie Ihr Postfach.";
            } else {
              $respTitle = "Subscription";
              $respSubtitle = "";
              $respContent = "Thank you for subscribing.<br>";
              $respContent .= "We've sent an email with a confirmation link to you<br>";
              $respContent .= "Please check your inbox.";
            }
          break;
          default:
        }

      }
    } else {
      // test get like  http GET http://127.0.0.1:8000/index1.php/
      // confirm==d239672bd8d7986458abeae3454b6be7fe11e6bfa375741f157bdf2d3088f338
      //lang==de
      // note double == for GET
      if ($_SERVER["REQUEST_METHOD"] == "GET") {
        mlog(json_encode($_GET));
        // check lang present
        if (empty($_GET["lang"])) {
          mlog("Missing lang on get");
          throw new Exception("1");
        } else {
          $lang = $_GET["lang"];
          if (($lang != "de") and ($lang != "en")) {
            mlog("Wrong language code on get ");
            throw new Exception("1");
          }
          mlog("Get language: " . $lang);
        }
        // check confirm and remove actions
        // confirms
        if (!empty($_GET["confirm"])) {
          $val = filter_var($_GET["confirm"], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW | FILTER_SANITIZE_SPECIAL_CHARS);
          mlog("confirm: " . $val);
          if (!confirm($val))
            throw new Exception ("1");
          else {
            // worked!
            if ($lang == "de") {
              $respTitle = "Bestätigung";
              $respSubtitle = "";
              $respContent = "Vielen Dank für Ihre Bestätigung.<br>";
            } else {
              $respTitle = "Confirmation";
              $respSubtitle = "";
              $respContent = "Thank you for the confirmation.<br>";
            }
          }
        } else {
          // remove
          if (!empty($_GET["remove"])) {
            $val = filter_var($_GET["remove"], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW | FILTER_SANITIZE_SPECIAL_CHARS);
            mlog("remove: " . $val);
            if (!remove($val))
              throw new Exception ("1");
            else {
              // worked!
              if ($lang == "de") {
                $respTitle = "Abmeldung";
                $respSubtitle = "";
                $respContent = "Wir haben Sie aus dem Verteiler ausgetragen.<br>";
              } else {
                $respTitle = "Unsubscription";
                $respContent = "We have cancelled your subscription.<br>";
                $respSubtitle = "";
              }
            }
          } else {
            // download
            if (!empty($_GET["down"])) {
              $val = filter_var($_GET["down"], FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW | FILTER_SANITIZE_SPECIAL_CHARS);
              $users = down($val);
              mlog("users: " . $users);
              // this is special: echo and die
              echo $users . PHP_EOL;
              die(); // don't proceed from here
              //
            } else {
              mlog("Invalid GET");
              throw new Exception("1");
            }
          }
        }
      }
    }

  } catch (Exception $e) {
    switch ($e->getMessage()) {
      case "0":
        $respTitle = "Verzeihung / We're sorry";
        $respSubtitle = "";
        $respContent = "Leider ist ein Fehler aufgetreten. Bitte versuchen Sie es später noch einmal.<br>";
        $respContent .= "Unfortunately we encountered an error. Please try again later";
      break;
      case "1":
      if ($lang == "de") {
        $respTitle = "Verzeihung";
        $respSubtitle = "";
        $respContent = "Leider ist uns ein Fehler unterlaufen. Bitte versuchen Sie es später noch einmal";
      } else {
        $respTitle = "We're sorry";
        $respSubtitle = "";
        $respContent = "Unfortunately we encountered an error. Please try again later";
      }
      break;
      case "2":
        if ($lang == "de") {
          $respTitle = "Das hat leider nicht geklappt.";
          $respSubtitle = "";
          $respContent = "Die Email Adresse ist ungültig. Bitte versuchen Sie nochmal";
          $respContent .= "<a href=\"\#newsletter\">Zurück</a>";
        } else {
          $respTitle = "Sorry, this didn't work.";
          $respSubtitle = "";
          $respContent = "The Email Address is invalid. Please try again.";
          $respContent .= "<a href=\"\#newsletter\">Back</a>";
        }
      break;
      case "3":
      if ($lang == "de") {
        $respTitle = "Anmeldung";
        $respSubtitle = "";
        $respContent = "Alles OK, Sie waren bereits für den Newsletter angemeldet.";
      } else {
        $respTitle = "Subscription";
        $respSubtitle = "";
        $respContent = "OK, you have been subscribed already.";
      }
      break;
      default:
        mlog("Processing error");
        die("Invalid error code");
    }
    mlog($respTitle . "," . $respSubtitle . "," . $respContent);
    include "../actions/action/index.html";
    die();

  }
