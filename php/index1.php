<?php

  // pull flight and medoo via composer autoload

  require 'vendor/autoload.php';

  use Endroid\QrCode\ErrorCorrectionLevel;
  use Endroid\QrCode\QrCode;

  // --------------------------------------------------
  // logging
  // --------------------------------------------------

  //relevant logLevels:"DEBUG", "NOTICE", "WARNING", "ERROR", "URGENT");

  $log_file = './kdg.log';
  Analog::handler(
      Analog\Handler\Threshold::init(
          Analog\Handler\File::init($log_file),
          Analog::DEBUG  // this and all below will be logged
      // set to WARNING in production environment
      )
  );

  if(!isset($_SERVER['HTTP_HOST'])) {
	Analog::log("Log initialized on localhost", Analog::NOTICE);
  } else {
    Analog::log("Log initialized on " . $_SERVER['HTTP_HOST'], Analog::NOTICE);
  }

  // --------------------------------------------------
  // config setup
  // --------------------------------------------------
  // ini file on uberspace is elsewhere
  $cfg = array();
  //$cfg = parse_ini_file("/home/akugel/files/kdg/kdg.ini",false);
  // $cfg = parse_ini_file("kdg.ini",false);
  try {
      if (!isset($_SERVER['HTTP_HOST']) or ($_SERVER['HTTP_HOST'] == "kdg")) {
          $cfg = parse_ini_file("kdg.ini", false);
	      Analog::log("Using local config", Analog::NOTICE);
      } else {
          $cfg = parse_ini_file("/home/kdg/files/kdg/kdg.ini", false);
      }

      // don't print in real program
      //echo "Config items: " . count($cfg) . "\n";
      //print_r($cfg);
      Analog::log("Config parsed", Analog::NOTICE);
  } catch (Exception $e) {
      Analog::log("Config error", Analog::URGENT);
      exit("Error");
  }


  // --------------------------------------------------
  // Date and timing settings and functions
  // --------------------------------------------------
  function getEpoche() {
	global $cfg;
	$tz = new DateTimeZone("Europe/Berlin");
	$start = new DateTime($cfg["start"], $tz);
	Analog::log("Start: ".$start->format("d.m.Y H:i"), Analog::DEBUG);
	$d = new DateTime("NOW",$tz);
	Analog::log("Now: ".$d->format("d.m.Y H:i"), Analog::DEBUG);
	$dl = $start->diff($d);
	$delta = intval($dl->format("%R%d"));	// signed
	Analog::log("Delta: ".$delta, Analog::DEBUG);
	$epoche = floor($delta/$cfg["epoche"]);
	if ($epoche < 0) {
		return 0;
	} else {
		return $epoche + 1;
	}
  }

  Analog::log("Epoche: ".getEpoche(), Analog::DEBUG);

  // --------------------------------------------------
  // database setup
  // --------------------------------------------------

  // Using Medoo namespace
  use Medoo\Medoo;

  // Initialize
  /*
  $database = new Medoo([
      'database_type' => 'mysql',
      'database_name' => 'DB3707046',
      'server' => 'rdbms.strato.de',
      'username' => 'U3707046',
      'password' => 'Eewie9QuupaihahMae1u'
  ]);
  */
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
      //print_r($database->info());
      Analog::log("Database opened", Analog::NOTICE);
  } catch (Exception $e) {
      Analog::log("Database error", Analog::URGENT);
      exit("Error");
  }

  // --------------------------------------------------
  // router  setup
  // --------------------------------------------------
  // Routing in Flight is done by matching a URL pattern with a callback function.

  // --------------------------------------------------
  // helpers
  // --------------------------------------------------
  function sendSmtp($to, $body)
  {
      global $cfg;
      // set time zone for date usage later on
      date_default_timezone_set("Europe/Berlin");

      $host = $cfg["smtphost"];
      $port = $cfg["smtpport"];
      $from = $cfg["from"];
      $subj = "Anmeldung zum Datenspiel";
      $date = date(DATE_RFC2822);

      // test only
      //$to = "ak@akugel.de";


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
          $header = "From: KDG <".$from.">\r\nTo: ".$to;
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
          Analog::log("Error: ".$e->getMessage(), Analog::ERROR);
          return false;
      }

      return true;
  }


  function sendOptIn($to,$age,$area)
  {
      global $cfg;
      global $database;
      Analog::log("opt in for ".$to, Analog::DEBUG);
      // if $to is new, create new user first
      // create a new conf slot for $to
      // database
      //echo "md5 : ".hash("md5",$p)."\r\n";
      //echo "sha256 : ".hash("sha256",$p)."\r\n";

      // generate the codes for mail
      $mailcode = hash("md5", uniqid($to, true));

      try {
          $user = $database->select("users", "id", ["email" => $to]);
          Analog::log("searching user ".$to." returned ".json_encode($user), Analog::DEBUG);
          if (count($user) > 0) {
              $uid = $user[0];
              $database->update("confirms", ["mailcode" => $mailcode], ["id" => $uid]);
              $err = $database->error();
              if ($err[0] != 0) {
                  throw new Exception($err[2]);
              }
              Analog::log("Updating mailcode for user ".$uid, Analog::DEBUG);
          } else {
              Analog::log("Inserting user ".$to, Analog::DEBUG);
              $newUser = array();
              $newUser["email"] = $to;
              $newUser["epoche"] = getEpoche();
              $newUser["age"] = $age;
              $newUser["area"] = $area;
              $database->insert("users", $newUser);
              $err = $database->error();
              // if OK, $err[0] is 0
              // on error, $err[] != 0 and $err[2] is error string
              if ($err[0] != 0) {
                  throw new Exception($err[2]);
              } else {
                  $user = $database->select("users", "id", ["email" => $to]);
                  if (count($user) == 0) {
                      throw new Exception("New user but not found on ".$to);
                  }
                  $uid = $user[0];
                  Analog::log("searching user ".$to." returned ".$uid, Analog::DEBUG);
				  // insert mailcode
				  $newConfirm = array();
		          $newConfirm["user"] = $uid;
		          $newConfirm["mailcode"] = $mailcode;
	              $database->insert("confirms", $newConfirm);
		          $err = $database->error();
		          if ($err[0] != 0) {
		              throw new Exception($err[2]);
		          }
		          Analog::log("Setting mailcode for user ".$uid, Analog::DEBUG);
              }
          }

          Analog::log("Registration code created", Analog::DEBUG);
      } catch (Exception $e) {
          Analog::log("Error".$e->getMessage(), Analog::ERROR);
          return false;
      }

      // mail
      try {
          // set time zone for date usage later on
          // create text
          $body = "Hallo\n\n";
		  $body .= "Wir haben eine Anmeldung zum Karlsruher Datenspiel erhalten\r\n";
		  $body .= "Wenn Du mitmachen möchtest, klicke bitte auf diesen Link, um die Anmeldung abzuschliessen.\r\n";
		  $body .= "Wenn Du nicht mitmachen möchtest, kannst Du diese Nachricht einfach löschen.\r\n\r\n";
          // add confirmation link
          $mode = "https://";
          if (!isset($_SERVER['HTTPS']) || $_SERVER['HTTPS'] == 'off') {
              $mode = "http://";
          }
          $body .= $mode . $_SERVER["SERVER_NAME"] . "/confirm/" . $mailcode . "\r\n\r\n";
		  $body .= "Vielen Dank\r\n";
          Analog::log("Sending mail: ".$body, Analog::DEBUG);
          if (!sendSmtp($to, $body)) {
              throw new Exception("SMTP failed");
          }
      } catch (Exception $e) {
          Analog::log("Error".$e->getMessage(), Analog::ERROR);
          return false;
      }
      return true;
  }


	// login check
	function checkLogin($cookie) {
      global $database;
      Analog::log("Login check for cookie ".$cookie, Analog::DEBUG);

      try {
          $user = $database->select("users", ["id", "validated"], ["cookie" => $cookie]);
          Analog::log("searching user returned id ".json_encode($user), Analog::DEBUG);
          if (count($user) == 0) {
			  throw new Exception("Cookie not valid");
          } else {
              $uid = $user[0]["id"];
              $validated = $user[0]["validated"];
			  // check if user is validated
	          if ($validated != null) {
                Analog::log("Found verified user ".$uid.", validated: ".$validated, Analog::DEBUG);
				return 2; // registered and verified
			  } else {
	            Analog::log("Found user ".$uid, Analog::DEBUG);
				return 1; // registered only
			  } 
          }

          Analog::log("User logged in", Analog::DEBUG);
      } catch (Exception $e) {
          Analog::log("Error".$e->getMessage(), Analog::ERROR);
          return 0;
      }
	}

  // --------------------------------------------------
  // callbacks
  // --------------------------------------------------
  function confirm($a)
  // confirmation: check mailcode and update cookie if OK
  {
      global $database;
	  global $cfg;
      Analog::log("Confirmation code ".$a, Analog::DEBUG);

      if (isset($a)) {
		try {
		  $aa = htmlspecialchars($a);
		  // find codes
		  $user = $database->select("confirms", ["user"], ["mailcode" => $aa]);
		  if (count($user) == 0) {
		      $msg = "Mailcode not valid";
		      Analog::log($msg, Analog::DEBUG);
		      $result["msg"] = $msg;
		      throw new Exception($msg);
		  }
		  // mailcode is OK, find user
		  $uid = $user[0]["user"];
		  $user = $database->select("users", ["email"], ["id" => $uid]);
		  if (count($user) == 0) {
		      $msg = "User id not valid";
		      Analog::log($msg, Analog::DEBUG);
		      $result["msg"] = $msg;
		      throw new Exception($msg);
		  }
		  $email = $user[0]["email"];
		  Analog::log("searching mailcode returned uid: ".$uid.", mail ".$email, Analog::DEBUG);
		  // create (new) cookie
		  $cookie = hash("sha256", uniqid($email.$cfg["nonce"], true)); // database
		  // update
		  $database->update("users", ["cookie" => $cookie], ["id" => $uid]);
		  $err = $database->error();
		  if ($err[0] != 0) {
		      throw new Exception($err[2]);
		  }
		  Analog::log("Updating cookie for user ".$uid, Analog::DEBUG);

		  // set the server cookie
		  setcookie("kdgId", $cookie, time()+3600, "/", $_SERVER["SERVER_NAME"], 0, 0);
		  Analog::log("Cookie set: " . $cookie, Analog::DEBUG);
		  // dummy extra cookie
		  setcookie("dummy", "xyz", time()+3600, "/", $_SERVER["SERVER_NAME"], 0, 0);
		  //
		  $f = file_get_contents("kdgConfirm.html");
		  echo $f;
		  } catch (Exception $e) {
		      Analog::log("Error: ".$e->getMessage(), Analog::ERROR);
			  $f = file_get_contents("problem.html");
			  echo $f;
		  }

      } else {
          Analog::log("Confimration without data: " . $cookie, Analog::WARNING);
		  // go to main page
		  $f = file_get_contents("kdg.html");
		  echo $f;
      }
  }

  // --------------------------------------------------
  // routing
  // --------------------------------------------------
  // Routing in Flight is done by matching a URL pattern with a callback function.

  // ---- errors ------
  Flight::map('notFound', function () {
      // Handle not found
      echo "Flight: not found";
  });


  // ---- links ------
  // route root to main html file
  Flight::route('/', function () {
      $f = file_get_contents("kdg.html");
      echo $f;
  });

  // email confirmation link
  Flight::route('/confirm/@a', function ($a) {
      confirm($a);
  });

  // ---- collage ------
  // web collage
  Flight::route('/collage', function () {
      $f = file_get_contents("kdgIdeas.html");
      echo $f;
  });

  // ---- qrcode generator ------
  // qr
  Flight::route('/qr', function () {
	global $cfg;
	$res = 0;
    $f = file_get_contents("problem.html");
	  if (isset ($request->data->cookie)) {
		  $cookie = $request->data->cookie;
		  Analog::log("Cookie: ".$cookie, Analog::DEBUG);
		  $res = checkLogin($cookie);
	  } else {
		  Analog::log("Missing cookie on QR request", Analog::DEBUG);
	  }
    if ($res > 0) {
		$expectedCode = hash("md5", date('j.m.y').$cfg["confseed"]);
		// Create a basic QR code
		$qrCode = new QrCode($expectedCode);
		$qrCode->setSize(400);

		// Set advanced options
		$qrCode->setWriterByName('png');
		$qrCode->setMargin(20);
		$qrCode->setEncoding('UTF-8');
		$qrCode->setErrorCorrectionLevel(new ErrorCorrectionLevel(ErrorCorrectionLevel::HIGH));
		$qrCode->setForegroundColor(['r' => 0, 'g' => 0, 'b' => 0, 'a' => 0]);
		$qrCode->setBackgroundColor(['r' => 255, 'g' => 255, 'b' => 255, 'a' => 0]);

		// Save it to a file
		//$file = "qrcode_" + (string)getEpoche() + ".png"
		$file = __DIR__."/img/qrcode.png";
		Analog::log("Writing QR to: ".$file, Analog::DEBUG);
		$qrCode->writeFile($file);
		$f = file_get_contents("kdgQr.html");
	}
    echo $f;

  });



  // ---- posts ------
  // registration
  Flight::route('POST /register', function () {
      global $database;
      $request = Flight::request();
      $type = $request->data->type;
      $mail = $request->data->email;
      $age = $request->data->age;
      $area = $request->data->area;
      Analog::log("Registration", Analog::DEBUG);
      Analog::log("Request: ".json_encode($request), Analog::DEBUG);
      Analog::log("Type: ".$type, Analog::DEBUG);
      Analog::log("Email: ".$mail, Analog::DEBUG);
      Analog::log("Age: ".$age, Analog::DEBUG);
      Analog::log("Area: ".$area, Analog::DEBUG);

      $e = filter_var($mail, FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_HIGH | FILTER_FLAG_STRIP_LOW | FILTER_SANITIZE_SPECIAL_CHARS);
      $email = filter_var($e, FILTER_VALIDATE_EMAIL);

      $result = array();
      $result["msg"] = "";
      $result["status"] = "1";
      $result["type"] = "registration";
      if (!$email) {
          $result["status"] = "0";
          $result["msg"] = "Ungültige EMail";
      } else {
          // send mail
          if (! sendOptIn($email,$age,$area)) {
              $result["status"] = "0";
              $result["msg"] = "Fehler beim Senden der Mail";
          }
      }
      Analog::log("Result: ".json_encode($result), Analog::DEBUG);
      print Flight::json($result);
  });

 // data
  Flight::route('POST /data', function () {
      global $database;
      global $cfg;
      $request = Flight::request();
      $obj = $request->data->payload;
      Analog::log("Data", Analog::DEBUG);
      Analog::log("Request: ".json_encode($request), Analog::DEBUG);

	  $result = array();
	  $result["status"] = (string)0; // default
	  $result["type"] = "data";

	  $data = array();

      try {
          // find user
		  if (!isset ($obj["cookie"])) {
            Analog::log("Missing cookie", Analog::DEBUG);
			throw new Exception("Missing cookie");
		  }
		  $cookie = $obj["cookie"];
          $user = $database->select("users", "id", ["cookie" => $cookie]);
		  $uid = 0; // init uid to invalid
          if (count($user) > 0) {
	          $uid = $user[0];
              Analog::log("Cookie valid: ".$cookie, Analog::DEBUG);
          }
          Analog::log("searching user returned ".$uid, Analog::DEBUG);

		  // current epoche
		  $epoche = getEpoche();

		  for ($e = 1; $e <= $epoche; $e ++) {
		      Analog::log("Data epoche: ".$e, Analog::DEBUG);
			  $d = array(); 
			  $d["epoche"] = (string)$e;
			  $d["own"] = (string)0;  // init own items to 0

			  // get status items
		      $x = $database->select("users", "id",["epoche[<=]" => $e]);
			  $d["user"] = (string)count($x);
		      Analog::log("Users: ".$d["user"], Analog::DEBUG);

		      $x = $database->select("uploads", "id",["epoche[<=]" => $e]);
			  $d["all"] = (string)count($x);
		      Analog::log("All: ".$d["all"], Analog::DEBUG);

			  if ($uid > 0) {
				  $x = $database->select("uploads", "id",["user" => $uid,"epoche[<=]" => $e]);
				  $d["own"] = (string)count($x);
				  Analog::log("Own: ".$d["own"], Analog::DEBUG);
			  }
			  array_push($data,$d);
		  }
		  if (count($data) > 0) {			
	          $result["status"] = "1";
		  }
		  $result["data"] = $data;

      } catch (Exception $e) {
          Analog::log("Error", Analog::ERROR);
          $result["msg"] = "Error occured: ".$e->getMessage();
      }
	  
      Analog::log("Result: ".json_encode($result), Analog::DEBUG);
      print Flight::json($result);
  });



  // quiz
  Flight::route('POST /quizitem', function () {
      global $database;
      global $cfg;
      $request = Flight::request();
      Analog::log("Quiz", Analog::DEBUG);
      Analog::log("Request: ".json_encode($request), Analog::DEBUG);

	  $result = array();
	  $result["status"] = (string)0; // default
	  $result["type"] = "quiz";

      try {

		  $epoche = getEpoche();
		  // get quiz
          $quiz = $database->select("quiz", ["hint","question"], ["epoche" => $epoche]);
          if (count($quiz) == 0) {
              Analog::log("Nothing found", Analog::DEBUG);
              $result["msg"] = "No data";
              throw new Exception("No data");
          }
		  $result["quiz"] = $quiz[0];
          $result["status"] = "1";

      } catch (Exception $e) {
          Analog::log("Error", Analog::ERROR);
          $result["msg"] = "Error occured:".$e->getMessage();
      }
	  
      Analog::log("Result: ".json_encode($result), Analog::DEBUG);
      print Flight::json($result);
  });


  // ideaList
  Flight::route('POST /idealist', function () {
      global $database;
      global $cfg;
      $request = Flight::request();
      $obj = $request->data->payload;
      Analog::log("Idealist", Analog::DEBUG);
      Analog::log("Request: ".json_encode($request), Analog::DEBUG);

	  $result = array();
	  $result["status"] = (string)0; // default
	  $result["type"] = "idealist";

      try {
          // find user
		  /*
		  $cookie = $obj["cookie"];
          $user = $database->select("users", "id", ["cookie" => $cookie]);
          if (count($user) == 0) {
              Analog::log("Cookie not valid: ".$cookie, Analog::DEBUG);
              $result["msg"] = "Invalid cookie";
              throw new Exception("Invalid cookie");
          }
          $uid = $user[0];
          Analog::log("searching user returned ".$uid, Analog::DEBUG);
		  */

		  $epoche = getEpoche();
		  // get idea
          $list = $database->select("ideas", ["id","type","text"], ["epoche" => $epoche]);
          if (count($list) == 0) {
              Analog::log("Nothing found", Analog::DEBUG);
              $result["msg"] = "No data";
              throw new Exception("No data");
          }
		  $ideas = array();
		  foreach ($list as $li) {
			array_push($ideas,$li);
		  }
		  $result["ideas"] = $ideas;
          $result["status"] = "1";

      } catch (Exception $e) {
          Analog::log("Error", Analog::ERROR);
          $result["msg"] = "Error occured:".$e->getMessage();
      }
	  
      Analog::log("Result: ".json_encode($result), Analog::DEBUG);
      print Flight::json($result);
  });

  

  // ideas
  Flight::route('POST /idea', function () {
      global $database;
      global $cfg;
      $request = Flight::request();
      $obj = $request->data->payload;
      Analog::log("Ideas", Analog::DEBUG);
      Analog::log("Request: ".json_encode($request), Analog::DEBUG);

	  $result = array();
	  $result["status"] = (string)0; // default
	  $result["type"] = "idea";

      try {
          // find user
		  if (!isset ($obj["cookie"])) {
            Analog::log("Missing cookie", Analog::DEBUG);
			throw new Exception("Missing cookie");
		  }
		  $cookie = $obj["cookie"];
          $user = $database->select("users", "id", ["cookie" => $cookie]);
          if (count($user) == 0) {
              Analog::log("Cookie not valid: ".$cookie, Analog::DEBUG);
              $result["msg"] = "Invalid cookie";
              throw new Exception("Invalid cookie");
          }
          $uid = $user[0];
          Analog::log("searching user returned ".$uid, Analog::DEBUG);
		  $epoche = getEpoche();

		  // insert idea
		  $type = $obj["type"];
		  $idea = $obj["idea"];
          $database->insert("ideas", ["user" => $uid, "epoche" => $epoche, "type" => $type, "text" => $idea ]);
          $err = $database->error();
          if ($err[0] != 0) {
              throw new Exception($err[2]);
          }
          Analog::log("Idea inserted for user ".$uid, Analog::DEBUG);

          $result["status"] = "1";
      } catch (Exception $e) {
          Analog::log("Error", Analog::ERROR);
          $result["msg"] = "Error occured:".$e->getMessage();
      }
	  
      Analog::log("Result: ".json_encode($result), Analog::DEBUG);
      print Flight::json($result);
  });


  // login
  Flight::route('POST /login', function () {
      global $database;
      $request = Flight::request();
      $type = $request->data->type;
      Analog::log("Registration", Analog::DEBUG);
      Analog::log("Request: ".json_encode($request), Analog::DEBUG);
      Analog::log("Type: ".$type, Analog::DEBUG);

      $result = array();
	  $res = 0;
	  if (isset ($request->data->cookie)) {
	      $cookie = $request->data->cookie;
	      Analog::log("Cookie: ".$cookie, Analog::DEBUG);
		  $res = checkLogin($cookie);
	      $result["msg"] = "";
	  } else {
      	$result["msg"] = "Missing cookie";
	  }

      $result["status"] = (string)$res;
      $result["epoche"] = (string)getEpoche();
      $result["type"] = "login";

      Analog::log("Result: ".json_encode($result), Analog::DEBUG);
      print Flight::json($result);
  });


  // qr code verification
  Flight::route('POST /verify', function () {
      global $database;
      global $cfg;
      $request = Flight::request();
      $result = array();
      $result["status"] = "0";
      $result["type"] = "verify";
      $result["msg"] = "";

      try {
          $type = $request->data->type;
          $obj = $request->data->payload;
          Analog::log("Verify QR", Analog::DEBUG);
          Analog::log("Request: ".json_encode($request), Analog::DEBUG);
          Analog::log("Type: ".$type, Analog::DEBUG);

          // find user
		  if (!isset ($obj["cookie"])) {
            Analog::log("Missing cookie", Analog::DEBUG);
			throw new Exception("Missing cookie");
		  }
          $cookie = $obj["cookie"];
          Analog::log("Provided cookie: ".$cookie, Analog::DEBUG);
          $user = $database->select("users", "id", ["cookie" => $cookie]);
          if (count($user) == 0) {
              Analog::log("Cookie not valid", Analog::DEBUG);
              $result["msg"] = "Invalid cookie";
              throw new Exception("Invalid cookie");
          }
          $uid = $user[0];
          Analog::log("searching user returned ".$uid, Analog::DEBUG);

		  // check code
		  //$expectedCode = (string)(date('j.m.y')).$cfg["confseed"];
		  $expectedCode = hash("md5", date('j.m.y').$cfg["confseed"]);
		  //$expectedCode = "http://rg-asc.ziti.uni-heidelberg.de/kugel"; // test
          Analog::log("Expected code: ".$expectedCode, Analog::DEBUG);
          Analog::log("Provided code: ".$obj["qrcode"], Analog::DEBUG);

		  if ($obj["qrcode"] !== $expectedCode) {
			throw new Exception("Invalid QR code");
		  }

		  // insert code
          // $database->insert("verifications", ["user" => $uid, "qrcode" => $obj["qrcode"]]);
		  // set validation
		  $tz = new DateTimeZone("Europe/Berlin");
		  $d = new DateTime("NOW",$tz);
		  $validate = $d->format("Y-m-d H:i:s");
          $database->update("users", ["validated" => $validate], ["id" => $uid]);
          $err = $database->error();
          if ($err[0] != 0) {
              throw new Exception($err[2]);
          }
          Analog::log("User validated: ".$uid, Analog::DEBUG);

          $err = $database->error();
          // if OK, $err[0] is 0
          // on error, $err[] != 0 and $err[2] is error string
          if ($err[0] != 0) {
              throw new Exception($err[2]);
          }
		  $msg = "QR code inserted";
          Analog::log($msg, Analog::DEBUG);

          $result["msg"] = $msg;
          $result["status"] = "1";
      } catch (Exception $e) {
          Analog::log("Error", Analog::ERROR);
          $result["msg"] = "Error occured:".$e->getMessage();
      }

      Analog::log("Result: ".json_encode($result), Analog::DEBUG);
      print Flight::json($result);
  });

  // data upload
  Flight::route('POST /upload', function () {
      global $database;
      global $cfg;
      $request = Flight::request();
      $result = array();
      $result["status"] = "0";
      $result["type"] = "upload";
      $result["msg"] = "";

      try {
          $type = $request->data->type;
          $obj = $request->data->payload;
          Analog::log("Uploading image", Analog::DEBUG);
          Analog::log("Request: ".json_encode($request), Analog::DEBUG);
          Analog::log("Type: ".$type, Analog::DEBUG);
          Analog::log("Date: ".$obj["date"], Analog::DEBUG);

          // find user
		  if (!isset ($obj["cookie"])) {
            Analog::log("Missing cookie", Analog::DEBUG);
			throw new Exception("Missing cookie");
		  }
          $cookie = $obj["cookie"];
          Analog::log("Provided cookie: ".$cookie, Analog::DEBUG);
          $user = $database->select("users", "id", ["cookie" => $cookie]);
          if (count($user) == 0) {
              Analog::log("Cookie not valid", Analog::DEBUG);
              $result["msg"] = "Invalid cookie";
              throw new Exception("Invalid cookie");
          }
          $uid = $user[0];
          Analog::log("searching user returned ".$uid, Analog::DEBUG);

		  // if we have type 1 then process the image
          $upType = intval($obj["type"]);
	      Analog::log("Upload type: ".$upType,Analog::DEBUG);

          // enter int database
          //Analog::log("Processing: ".json_encode($obj),Analog::DEBUG);
          $new = array();
          $new["user"] = $uid;
		  $new["type"] = $upType;
          // $new["created"] = $obj["date"];  // use mysql timestamp
          $new["client"] = $obj["client"];
          $new["epoche"] = getEpoche();
          $new["lat"] = floatval($obj["geo"][0]);
          $new["long"] = floatval($obj["geo"][1]);
          $new["gridx"] = intval($obj["grid"][1]);
          $new["gridy"] = intval($obj["grid"][0]);
          $new["answers"] = $obj["answers"];

		  if ($upType == 2) {
		      Analog::log("Processing: ".json_encode($new),Analog::DEBUG);
		  } else {
		      // process image
		      //Analog::log("Cookie: ".$obj["cookie"],Analog::DEBUG);
		      //Analog::log("Comment: ".$obj["comment"],Analog::DEBUG);
		      // write image, first remove mime type from string (up to first ,)
		      // databse works, so no storage of full image as full. just the thimbnails (further down)
		      // file_put_contents ("img.jpg", base64_decode(trim(strpbrk($obj["data"],","),",")));

		      // using gd2 for thumbnails
		      $source_image = imagecreatefromstring(base64_decode(trim(strpbrk($obj["data"], ","), ",")));
		      // we could use this to write the file here: imagejpeg($virtual_image, "thumb_".$imgId."jpg");

		      $width = imagesx($source_image);
		      $height = imagesy($source_image);
		      $desired_width = 128;
		      $new["orientation"] = intval($obj["orientation"]);
	          $new["exif"] = $obj["exif"];

		      $crop = false; // True;

		      if ($crop) {
		          // crop image for thumbnail
		          $size = min($width, $height);
		          //$cropped_image = imagecrop($source_image, ['x' => 0, 'y' => 0, 'width' => $size, 'height' => $size]);
		          $cropped_image = imagecrop($source_image, ['x' => ($width-$size)/2, 'y' => ($height-$size)/2, 'width' => $size, 'height' => $size]);

		          $width = imagesx($cropped_image);
		          $height = imagesy($cropped_image);
		          $desired_width = 128;
		          $desired_height = floor($height * ($desired_width / $width));
		          Analog::log("Thumbnail :".strval($desired_width).",".strval($desired_height), Analog::DEBUG);

		          $virtual_image = imagecreatetruecolor($desired_width, $desired_height);
		          /* copy source image at a resized size */
		          imagecopyresampled($virtual_image, $cropped_image, 0, 0, 0, 0, $desired_width, $desired_height, $width, $height);
		      } else {
		          $desired_height = floor($height * ($desired_width / $width));
		          Analog::log("Thumbnail :".strval($desired_width).",".strval($desired_height), Analog::DEBUG);

		          $virtual_image = imagecreatetruecolor($desired_width, $desired_height);
		          /* copy source image at a resized size */
		          imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $desired_width, $desired_height, $width, $height);
		      }

		      $new["location"] = $obj["location"];
		      $new["comment"] = $obj["comment"];
		      $new["tag"] = $obj["tag"];
		      Analog::log("Processing: ".json_encode($new),Analog::DEBUG);
				 
		      // images must be converted
		      ob_start();
		      imagejpeg($source_image);
		      $image_string = ob_get_contents();
		      ob_end_flush();
			  // we don't insert the full size image into the database any more
			  // only the thumnails
		      //$new["privimg"] = $image_string;
		      $privimg = $image_string;

		      //$new["pubimg"] = null;
		      // pubimage will be inserted after image recognition.
		      // then also update thumbnail

			  // create initial thumbnail		
		      ob_start();
		      imagejpeg($virtual_image);
		      $image_string = ob_get_contents();
		      ob_end_flush();
		      $new["thumb"] = $image_string;

		      // clean up image storage
		      imagedestroy($virtual_image);
		      imagedestroy($source_image);

		  }

		  // we need to insert first in order to get the id ...
          // insert
          $database->insert("uploads", $new);
          $err = $database->error();
          // if OK, $err[0] is 0
          // on error, $err[] != 0 and $err[2] is error string
          if ($err[0] != 0) {
              throw new Exception($err[2]);
          }
          $imgId = $database->id();


          Analog::log("Image item inserted: ".$imgId, Analog::DEBUG);
		  // now we need to check again for images

		  if ($upType == 1) {

		      /* create the physical thumbnail image to its destination */
		      // we can directly write the blob to a file
			  $fileBase = $cfg["fsbase"].$cfg["picdir"];
			  // check if directory exists, else create it
			  // make sure to get selinux permissions right!
			  try {
				  if(!is_dir($fileBase)){
				  	mkdir($fileBase, 0755);
					Analog::log("Creating image directory: ".$fileBase, Analog::DEBUG);
				  }
			  } catch (Exception $e) {
				  Analog::log("Error", Analog::ERROR);
				  $result["msg"] = "Error occured:".$e->getMessage();
			  }
		      Analog::log("Using image directory: ".$fileBase, Analog::DEBUG);
		      file_put_contents($fileBase."thumb_".$imgId.".jpg", $new["thumb"]);
		      file_put_contents($fileBase."priv_".$imgId.".jpg",$privimg);

		      //
		      $result["msg"] = "Image uploaded";
		      $result["status"] = "1";
		  } else {
		      $result["status"] = "1";
		  }
      } catch (Exception $e) {
          Analog::log("Error", Analog::ERROR);
          $result["msg"] = "Error occured:".$e->getMessage();
      }

      Analog::log("Result: ".json_encode($result), Analog::DEBUG);
      print Flight::json($result);
  });

  // get current hostspots
  Flight::route('POST /hotspots', function () {
      global $database;
      $request = Flight::request();
      $type = $request->data->type;
      $obj = $request->data->payload;
      print Flight::json(array('message' => $form, 'code' => 0));
  });

  // get user spots
  Flight::route('POST /userspots', function () {
      global $database;
      $request = Flight::request();
      $type = $request->data->type;
      $obj = $request->data->payload;
      print Flight::json(array('message' => $form, 'code' => 0));
  });

  // get statistics
  Flight::route('POST /statistics', function () {
      global $database;
      $request = Flight::request();
      $type = $request->data->type;
      $obj = $request->data->payload;
      print Flight::json(array('message' => $form, 'code' => 0));
  });

  // browser log
  Flight::route('POST /log', function () {
      $request = Flight::request();
      $result = array();
      $result["status"] = "1";
      $result["type"] = "log";
      $result["msg"] = "Log OK";
      $type = $request->data->type;
      $agent = $request->data->agent;
      $log = $request->data->log;
      if ($type == "log") {
          Analog::log("Browser - ".$agent . ": " . $log, Analog::DEBUG);
      } else {
          $result["status"] = "0";
          $result["msg"] = "Wrong type";
      }
      print Flight::json($result);
  });


  // start framework
  // this seems to work so far ...
  Analog::log("Starting flight", Analog::NOTICE);
  Flight::start();
