<?php

error_reporting(-1);
ini_set('display_errors', 'On');


session_start();
require_once '../vendor/autoload.php';

$token = md5($_GET['token']);
if(!$token) {
	die('No token');
}
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

$app = new \Slim\App();
$app->add(function(ServerRequestInterface $request, ResponseInterface $response, callable $next){
	$response = $response->withHeader('Content-type', 'application/json; charset=utf-8');
//	$response = $response->withHeader('Access-Control-Allow-Origin', 'http://allweb.fun');
	$response = $response->withHeader('Access-Control-Allow-Methods', 'GET,POST,PUT,PATCH,DELETE');
	return $next($request, $response);
});

$products_path = realpath('..').'/products.json';
if(!isset($_SESSION['cart'])) {
	$_SESSION['cart']=array();
}

//On charge les produits existants
$products = array();
if(file_exists($products_path)){
	$products = json_decode(file_get_contents($products_path), true);
}
//retourner la liste des produits
$app->get('/products', function() use($products){
	$page = intval($_GET['page']);
	$sort = ($_GET['sort']);
	$field = $_GET['field'];

	if(!$field) {
		$field='nom';
	}

	if(!$sort) {
		$sort='asc';
	}

	$sorted=[];
	foreach($products as $k => $product) {
		$sorted[$k] = $product[$field];
	}
	asort($sorted);

	if($sort == 'desc') {
		$sorted = array_reverse($sorted,true);
	}
	$final=[];
	foreach($sorted as $k=>$v) {
		$final[] = $products[$k];
	}

	$parpage=6;
	$debut = ($page-1)*$parpage;
	$final = array_slice($final,$debut,$parpage);
	echo json_encode($final);

});

$GLOBALS['file_cart'] = '../carts/cart-'.$token.'.json';
// methode relative au panier
$app->group('/cart', function() use($app, $products){
	// sans arguments: retourner le contenu du panier
	$app->get('', function() {
		$cart = file_get_contents($GLOBALS['file_cart']);
		if(!$cart) {
			$cart='[]';
		}
		echo $cart;
	});

	// sans arguments mais delete: on vide le panier
	$app->delete('', function() {
		file_put_contents($GLOBALS['file_cart'],'');
		echo '[]';
	});

	// si on reçoit un identifiant produit, on l'ajoute au panier
	$app->post('/{pid}', function(Slim\Http\Request $request, Slim\Http\Response $response) use($products){
		$pid = $request->getAttribute('pid');
		if(!empty($pid) && isset($products[$pid])){
			$current = 	file_get_contents($GLOBALS['file_cart']);
			if($current) {
				$current= json_decode($current,true);
			} else {
				$current = [];
			}
			if( ! isset($current[$pid]) ){
				$current[$pid]['id'] = $products[$pid]['id'];
				$current[$pid]['nom'] = $products[$pid]['nom'];
				$current[$pid]['qte'] = 0;
				$current[$pid]['ok'] = false;
				$current[$pid]['prix'] = 0;
			}

			$current[$pid]['qte'] += 1;
			$current[$pid]['prix'] = $current[$pid]['qte'] * $products[$pid]['prix'];

			$to_json = json_encode(array_values($current));
			file_put_contents($GLOBALS['file_cart'],$to_json);
			echo $to_json;
		}
	});

	// si on veut valider l'achat d'un produit
	$app->put('/{pid}/buy', function(Slim\Http\Request $request, Slim\Http\Response $response){
		$pid = $request->getAttribute('pid');
		sleep(rand(0,5)); // faire durer, de manière aléatoire, le temps de traitement de cette méthode
		echo json_encode(array('success' => true, 'product' => $pid));
	});
});

$app->run();
