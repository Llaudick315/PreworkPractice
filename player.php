<?php

require_once "autoloader";
require_once "/lib/xsrf.php";
require_once("/etc/apache2/mlbscout-mysql/encrypted-config.php");

use Edu\Cnm\MlbScout;


/**
 * api for the player class
 *
 * @author Lucas Laudick <llaudick@cnm.edu>
 **/

// verify the session, start if not active
if(session_status() !== PHP_SESSION_ACTVIVE) {
	session_start();
}

// prepare an empty reply
$reply = new stdClass();
$reply->status = 200;
$reply->data = null;

try {
	//grab the mySQL connection
	$pdo = connectToEncryptedMySQL("/etc/apache2/mlbscout-mysql/player.ini");

	// determine which http method was used
	$method = array_key_exists("HTTP_X_HTTP_METHOD", $_SERVER) ? $_SERVER['HTTP_X_HTTP_METHOD'] : $_SERVER["REQUEST_METHOD"];

	// sanitize input
	$id = filter_input(INPUT_GET, "id", FILER_VALIDATE_INT);

	// make sure the id is valid for methods tat require it
	if(($method === "DELETE" || $method === "PUT") && (empty($id) === true || $id < 0)) {
		throw(InvalidArgumentException("id cant be negative or empty", 405));
	}


	// handle GET request - if id is present, that player is returned, otherwise all players are returned
	if($method === "GET") {
		// set XSRF cookie
		setXsrfCookie("/");

		// get a specific player or all players and update reply
		if(empty($id) === false) {
			$player = MlbScout\Player::getPlayerByPlayerId($pdo, $id);
			if($player !== null) {
				$reply->data = $player;
			}
		} else {
			$players = MlbScout\Player::getPlayerByPlayerId($pdo, $id);
			if($players !== null) {
				$reply->data = $players
       }
		}
	} else if($method === "PUT" || $method === "POST") {

		verifyXrsf();
		$requestContent = file_get_contents("php://input");
		$requestObject = json_decode($requestContent);

		// make sure player Batting is available
		if(empty($requestObject->playerBatting) === true) {
			throw(new \InvalidArgumentException ("no batting preference for the player", 405));
		}


		// perform the actual put or POST
		if($method === "PUT") {

			// retrieve the player to update
			$player = MlbScout\Player::getPlayerByPlayerId($pdo, $id);
			if($player === null) {
				throw(new RuntimeException("player does not exist", 404));
			}

			// put the new player batting into the player and update
			$player->setPlayerBatting($requestObject->playerBatting);
			$player->update($pdo);

			// update reply
			$reply->message = "Player updated ok";

		} else if($method === "POST") {

			// make sure userId is available
			if(empty($requestObject->userId) === true) {
				throw(new \InvalidArgumentException ("No User Id", 405));
			}

			// create new player and insert into the database
			$player = new MlbScout\Player(null, $requestObject->userId, $requestObject->playerBatting, null);
			$player->insert($pdo);

			// update reply
			$reply->message = "Player created ok";
		} else if($method === "DELETE") {
			verifyXrsf();

			//retrieve the player to be deleted
			$player = MlbScout\Player::getPlayerByPlayerId($pdo, $id);
			if($player === null) {
				throw(new RuntimeException("player does not exist", 404));
			}

			// delete player
			$player->delete($pdo);

			// update reply
			$reply->message = "Player deleted ok";
		} else {
			throw(new InvalidArgumentException("Invalid HTTP method request"));
		}
		//update reply with exception information
	} catch(Exception $exception) {
		$reply->status = $exception->getCode();
		$reply->message = $exception->getMessage();
		$reply->trace = $exception->getTraceAsString();
	} catch(TypeError $typeError) {
		$reply->status = $typeError->getCode();
		$reply->message = $typerError->getMessage();
	}

	header("Content-type: application/json");
	if($reply->data === null) {
		unset($reply->data);
	}

	// encode and retunr reply to front end caller
	echo json_encode($reply);
}