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
$container['view'] = function ($container) {
   $view = new \Slim\Views\Twig(__DIR__ . '/resources/views', [
       'cache' => false,
   ]);
   $view->addExtension(new \Slim\Views\TwigExtension(
       $container->router,
       $container->request->getUri()
   ));
   return $view;
};
$app->group('/api', function () use ($app) {
	$app->get('/available-buses', 'getAvailableBuses');
	$app->post('/users', 'postUsers');
	$app->post('/boardings', 'insertBoardings');
});
$app->get('/stations', function ($request, $response) {
	$data = [];
	$stationSql = "SELECT * FROM stations";
    try {
		$db = getConnection();
        $stmt = $db->query($stationSql);
        $data = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;
		
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    } 

    $params = array('data' => $data,
        'base_url' => "http://localhost/slim/src/public/index.php/station",
        'title' => 'All Station Listing'
    );
   return $this->view->render($response, 'stations.twig', $params);
});
$app->group('/station', function () use ($app) {
	$app->get('/add', function ($request, $response) {
		$params = array(
			'base_url' => "http://localhost/slim/src/public/index.php/station",
			'title' => 'All Station Listing'
		);
	    return $this->view->render($response, 'add_station.twig', $params);
	});
	$app->post('/store', function($request, $response){
		$lat  = $_POST['latitude'];
		$lng  = $_POST['longitude'];
		$name = $_POST['name'];
		
		try {
			$db = getConnection();
			$date = date('Y-m-d H:i:s');
			$sql = "INSERT INTO stations (name, latitude, longitude) VALUES (:name, :lat, :lng)";
			$stmt = $db->prepare($sql);
			$stmt->bindParam("name", $name);
			$stmt->bindParam("lat", $lat);
			$stmt->bindParam("lng", $lng);
			$stmt->execute();
			
			$data = [];
			$stationSql = "SELECT * FROM stations";
			$db = getConnection();
			$stmt = $db->query($stationSql);
			$data = $stmt->fetchAll(PDO::FETCH_OBJ);
			$db = null;

			$params = array('data' => $data,
				'base_url' => "http://localhost/slim/src/public/index.php/station",
				'title' => 'All Station Listing'
			);
			return $this->view->render($response, 'stations.twig', $params);
		} catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}';
		}
	});
	$app->get('/{id}/edit', function ($request, $response) {
		$route = $request->getAttribute('route');
		$id = $route->getArgument('id');
		
		$stationSql = "SELECT * FROM stations WHERE id=".$id;
		try {
			$db = getConnection();
			$stmt = $db->query($stationSql);
			$data = $stmt->fetch();
			$db = null;
			
		} catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}';
		} 

		$params = array('data' => $data,
			'base_url' => "http://localhost/slim/src/public/index.php/station",
			'title' => 'All Station Listing'
		);
	   return $this->view->render($response, 'edit_station.twig', $params);
	});
	$app->post('/update/{id}', function ($request, $response) {
		$route = $request->getAttribute('route');
		$id = $route->getArgument('id');
		$lat = $_POST['latitude'];
		$lng = $_POST['longitude'];
		$name = $_POST['name'];
		
		try {
			$db = getConnection();
			$sql = "UPDATE `stations` SET name = :name, latitude = :lat, longitude = :lng WHERE `id` = :id";
			$stmt = $db->prepare($sql);
			$stmt->bindParam("name", $name);
			$stmt->bindParam("lat", $lat);
			$stmt->bindParam("lng", $lng);
			$stmt->bindParam("id", $id);
			$stmt->execute();
			
			$data = [];
			$stationSql = "SELECT * FROM stations";
			$db = getConnection();
			$stmt = $db->query($stationSql);
			$data = $stmt->fetchAll(PDO::FETCH_OBJ);
			$db = null;

			$params = array('data' => $data,
				'base_url' => "http://localhost/slim/src/public/index.php/station",
				'title' => 'All Station Listing'
			);
			return $this->view->render($response, 'stations.twig', $params);
		} catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}';
		}
	});
	$app->post('/delete/{id}', function ($request, $response) {
		try {
			$route = $request->getAttribute('route');
			$id = $route->getArgument('id');
			$db = getConnection();
			$sql = "DELETE from `stations` WHERE `id` = :id";
			$stmt = $db->prepare($sql);
			$stmt->bindParam("id", $id);
			$stmt->execute();
			
			$data = [];
			$stationSql = "SELECT * FROM stations";
			$db = getConnection();
			$stmt = $db->query($stationSql);
			$data = $stmt->fetchAll(PDO::FETCH_OBJ);
			$db = null;

			$params = array('data' => $data,
				'base_url' => "http://localhost/slim/src/public/index.php/station",
				'title' => 'All Station Listing'
			);
			return $this->view->render($response, 'stations.twig', $params);
		} catch(PDOException $e) {
			echo '{"error":{"text":'. $e->getMessage() .'}}';
		}
	});
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
    //$user = json_decode($request->getBody());
	$lat = $_POST['lat'];
	$lng = $_POST['lng'];
	
	$stationSql = "SELECT id, ( 6371 * acos( cos( radians( " . $lat . " ) ) * cos( radians( latitude ) ) * 
			cos( radians( longitude ) - radians( " . $lng . ") ) + sin( radians( " . $lat . " ) ) * 
			sin( radians( latitude ) ) ) ) AS distance FROM stations HAVING
			distance < 10 ORDER BY distance LIMIT 1";
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
			echo $db->lastInsertId();
			$db = null;
		}
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}
function addStation($request) {
    //$user = json_decode($request->getBody());
	$lat = $_POST['lat'];
	$lng = $_POST['lng'];
	$name = $_POST['name'];
	
    try {
		$db = getConnection();
		$date = date('Y-m-d H:i:s');
		$sql = "INSERT INTO stations (name, latitude, longitude) VALUES (:name, :lat, :lng)";
		$stmt = $db->prepare($sql);
		$stmt->bindParam("name", $name);
		$stmt->bindParam("lat", $lat);
		$stmt->bindParam("lng", $lng);
		$stmt->execute();
		echo $db->lastInsertId();
		$db = null;
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}
function storeStation($request) {
    //$user = json_decode($request->getBody());
	$lat = $_POST['latitude'];
	$lng = $_POST['longitude'];
	$name = $_POST['name'];
	
    try {
		$db = getConnection();
		$date = date('Y-m-d H:i:s');
		$sql = "INSERT INTO stations (name, latitude, longitude) VALUES (:name, :lat, :lng)";
		$stmt = $db->prepare($sql);
		$stmt->bindParam("name", $name);
		$stmt->bindParam("lat", $lat);
		$stmt->bindParam("lng", $lng);
		$stmt->execute();
		
		$data = [];
		$stationSql = "SELECT * FROM stations";
		$db = getConnection();
		$stmt = $db->query($stationSql);
		$data = $stmt->fetchAll(PDO::FETCH_OBJ);
		$db = null;

		$params = array('data' => $data,
			'base_url' => "http://localhost/slim/src/public/index.php/station",
			'title' => 'All Station Listing'
		);
	    return $this->view->render($response, 'stations.twig', $params);
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}
function insertBoardings($request) {
	$user = json_decode($request->getBody());
    $userId = $user->userId;
	$lat = $user->lat;
	$lng = $user->lng;
	
	
	$stationSql = "SELECT id, ( 6371 * acos( cos( radians( " . $lat . " ) ) * cos( radians( latitude ) ) * 
			cos( radians( longitude ) - radians( " . $lng . ") ) + sin( radians( " . $lat . " ) ) * 
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
			$stmt->bindParam("userId", $userId);
			$stmt->bindParam("busId", $bus["id"]);
			$stmt->bindParam("stationId", $station["id"]);
			$stmt->execute();
			$boarding = [];
			$boarding['userId'] = $userId;
			$boarding['busId'] = $db->lastInsertId();
			
			$status = 1;
			$user = $db->prepare("UPDATE `users` SET status = :status, boarded_at = :boardedAt WHERE `id` = :userId");
			$user->bindParam('status', $status);
			$user->bindParam('boardedAt', $date);
			$user->bindParam('userId', $userId);
			$user->execute();
			
			$db = null;
			echo json_encode($boarding);
		}
    } catch(PDOException $e) {
        echo '{"error":{"text":'. $e->getMessage() .'}}';
    }
}
$app->run();