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
	$app->get('/stations', 'getStations');
	$app->post('/boardings', 'insertBoardings');
});
function getConnection() {
    $dbhost="127.0.0.1";
    $dbuser="root";
    $dbpass="ken2017";
    $dbname="altruistic";
    $dbh = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass);
    $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    return $dbh;
}
function getAvailableBuses($response) {
    $sql = "select * FROM bus";
    try {
        $stmt = getConnection()->query($sql);
        $buses = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
		
        return json_encode($buses);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}
function getStations($response) {
    $sql = "select * FROM stations";
    try {
        $stmt = getConnection()->query($sql);
        $stations = $stmt->fetchAll(PDO::FETCH_OBJ);
        $db = null;
		
        return json_encode($stations);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}
function postUsers($request) {
    $user = json_decode($request->getBody());
	
    $sql = "INSERT INTO users (station_id, queued_at, status, boarded_at) VALUES (:stationId, :queuedAt, :status, :boardedAt)";
    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("stationId", $user->stationId);
        $stmt->bindParam("queuedAt", $user->queuedAt);
        $stmt->bindParam("status", $user->status);
		$stmt->bindParam("boardedAt", $user->boardedAt);
        $stmt->execute();
        $user->id = $db->lastInsertId();
        $db = null;
        echo json_encode($user);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}
function insertBoardings($request) {
    $boarding = json_decode($request->getBody());
	
    $sql = "INSERT INTO boardings (user_id, bus_id, station_id, created_at) VALUES (:userId, :busId, :stationId, NOW())";
    try {
        $db = getConnection();
        $stmt = $db->prepare($sql);
        $stmt->bindParam("userId", $boarding->userId);
        $stmt->bindParam("busId", $boarding->busId);
        $stmt->bindParam("stationId", $boarding->stationId);
        $stmt->execute();
        $boarding->id = $db->lastInsertId();
        $db = null;
        echo json_encode($boarding);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}
$app->run();