<?php
error_reporting(-1);
ini_set('display_errors', 1);

use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require '../../vendor/autoload.php';

$config['displayErrorDetails'] = true;
$config['addContentLengthHeader'] = false;

$config['db']['host']   = 'localhost';
$config['db']['user']   = 'root';
$config['db']['pass']   = 'ken2017';
$config['db']['dbname'] = 'altruistic';

$corsOptions = array(
    "origin" => "*",
    "exposeHeaders" => array("Content-Type", "X-Requested-With", "X-authentication", "X-client"),
    "allowMethods" => array('GET', 'POST', 'PUT', 'DELETE', 'OPTIONS')
);
$cors = new \CorsSlim\CorsSlim($corsOptions);

$app = new \Slim\App(['settings' => $config]);
$app->add($cors);
$container = $app->getContainer();
$container['logger'] = function($c) {
    $logger = new \Monolog\Logger('my_logger');
    $file_handler = new \Monolog\Handler\StreamHandler('../logs/app.log');
    $logger->pushHandler($file_handler);
    return $logger;
};
$container['db'] = function ($c) {
    $db = $c['settings']['db'];
    $pdo = new PDO('mysql:host=' . $db['host'] . ';dbname=' . $db['dbname'],
        $db['user'], $db['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    return $pdo;
};
$app->group('/api', function () use ($app) {
	$app->get('/available-buses', 'getAvailableBuses');
	$app->post('/users', 'postUsers');
	$app->post('/boardings', 'insertBoardings');
});
function getConnection() {
    $dbhost="127.0.0.1";
    $dbuser="root";
    $dbpass="social2012";
    $dbname="altruistic";
    $dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $dbh;
}
function getAvailableBuses($request) {
	//$cordinates = json_decode($request->getBody());
	
	$stationSql = "SELECT id, ( 6371 * acos( cos( radians( " . $_GET['lat'] . " ) ) * cos( radians( latitude ) ) * 
			cos( radians( longitude ) - radians( " . $_GET['lng'] . ") ) + sin( radians( " . $_GET['lat'] . " ) ) * 
			sin( radians( latitude ) ) ) ) AS distance FROM stations HAVING
			distance < 10 ORDER BY distance LIMIT 1";
    try {
		$db = getConnection();
        $stmt = $db->query($stationSql);
        $station = $stmt->rowCount();
		
		$buses = [];
		
		if($station > 0){
			$sql = "select * FROM bus";
			$stmt = getConnection()->query($sql);
			$buses = $stmt->fetchAll(PDO::FETCH_OBJ);
			$db = null;
		}
		
        return json_encode($buses);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    } 
}
function postUsers($request) {
    $user = json_decode($request->getBody());
	return $request;
	
	$stationSql = "SELECT id, ( 6371 * acos( cos( radians( " . $user->lat . " ) ) * cos( radians( latitude ) ) * 
			cos( radians( longitude ) - radians( " . $user->lng . ") ) + sin( radians( " . $user->lat . " ) ) * 
			sin( radians( latitude ) ) ) ) AS distance FROM stations HAVING
			distance < 0.05 ORDER BY distance LIMIT 1";
    try {
		$db = getConnection();
        $stmt = $db->query($stationSql);
        $count = $stmt->rowCount();
		$station = $stmt->fetch();
		
		if($count > 0){
			$date = date('Y-m-d H:i:s');
			$sql = "INSERT INTO users (station_id) VALUES (:stationId)";
			$stmt = $db->prepare($sql);
			$stmt->bindParam("stationId", $station["id"]);
			$stmt->execute();
			$user->id = $db->lastInsertId();
			$db = null;
			echo json_encode($user->id);
		}
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}
function insertBoardings($request) {
    $boarding = json_decode($request->getBody());
	
	$stationSql = "SELECT id, ( 6371 * acos( cos( radians( " . $boarding->lat . " ) ) * cos( radians( latitude ) ) * 
			cos( radians( longitude ) - radians( " . $boarding->lng . ") ) + sin( radians( " . $boarding->lat . " ) ) * 
			sin( radians( latitude ) ) ) ) AS distance FROM stations HAVING
			distance < 0.001 ORDER BY distance LIMIT 1";
    try {
		$db = getConnection();
        $stmt = $db->query($stationSql);
        $count = $stmt->rowCount();
		$station = $stmt->fetch();
		
		if($count > 0){
			$busStmt = $db->query("select vacant_seats, id FROM bus where vacant_seats > 1 LIMIT 1");
			$bus = $busStmt->fetch();
			
			$busSql = $db->prepare("UPDATE `bus` SET vacant_seats = :seat WHERE `id` = :busId");
			$seat = $bus["vacant_seats"]-1;
			$busSql->bindParam('seat', $seat);
			$busSql->bindParam('busId', $bus["id"]);
			$busSql->execute();
			
			$sql = "INSERT INTO boardings (user_id, bus_id, station_id, created_at) VALUES (:userId, :busId, :stationId, NOW())";
			$stmt = $db->prepare($sql);
			$stmt->bindParam("userId", $boarding->userId);
			$stmt->bindParam("busId", $bus["id"]);
			$stmt->bindParam("stationId", $station["id"]);
			$stmt->execute();
			$boarding->id = $db->lastInsertId();
			
			$status = 1;
			$user = $db->prepare("UPDATE `users` SET status = :status, boarded_at = :boardedAt WHERE `id` = :userId");
			$user->bindParam('status', $status);
			$user->bindParam('boardedAt', $date);
			$user->bindParam('userId', $boarding->userId);
			$user->execute();
			
			$db = null;
			echo json_encode($boarding);
		}
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}
$app->run();