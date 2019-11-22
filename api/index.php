<?php

error_reporting(-1);
ini_set('display_errors', 'On');


session_start();
require_once '../vendor/autoload.php';

if(isset($_GET['token'])) {
	$token = md5($_GET['token']);
} else {
	die('You must provide a token via a GET parameter to all API calls');	
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

/**
 * @api {get} /api/products/?page=:page&sort=:sort&field=:field Get products
 * @apiName getProducts
 * @apiGroup Products
 *
 * @apiParam {Number} page Paginate the product list. Default is 1
 * @apiParam {String} sort Sort order (`asc` or `desc`). Default is `asc`
 * @apiParam {String} field Field to sort on (`name` or `price`). Default is `price`
 *
 * @apiSuccess {Array} products The paginated and sorted product list
 */
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
	if($field == 'name') {
		$field='nom';
	}
	if($field == 'price') {
		$field='prix';
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

/**
 * @api {get} /api/cart Get cart content
 * @apiName GetCart
 * @apiGroup Cart
 *
 * @apiSuccess {Array} cart The cart content
 */
 	$app->get('', function() {
		$cart = file_get_contents($GLOBALS['file_cart']);
		if(!$cart) {
			$cart='[]';
		}
		echo $cart;
	});

/**
 * @api {delete} /api/cart Empty cart
 * @apiName EmptyCart
 * @apiGroup Cart
 *
 * @apiSuccess {Array} cart The cart content (an empty array)
 */
	$app->delete('', function() {
		file_put_contents($GLOBALS['file_cart'],'');
		echo '[]';
	});

/**
 * @api {delete} /api/cart/:product_id Remove product from cart
 * @apiName EmptyCartProduct
 * @apiGroup Cart
 *
 * @apiParam {Number} product_id The id of the product to remove from the cart
 *
 * @apiSuccess {Array} cart The cart content
 */
	$app->delete('/{pid}', function() {
		$pid = $request->getAttribute('pid');
		$current = getCart();
		$final = [];
		foreach($current as $k=>$v) {
				if($v['id'] != $pid){
					$final[$k]=$v;
				}
		}
		$json = json_encode($final);
		file_put_contents($GLOBALS['file_cart'],$json);
		echo $json;
	});


/**
 * @api {post} /api/cart/:product_id Add product to cart
 * @apiName CartProduct
 * @apiGroup Cart
 *
 * @apiParam {Number} product_id The id of the product to add to the cart
 *
 * @apiSuccess {Array} cart The cart content
 */
	$app->post('/{pid}', function(Slim\Http\Request $request, Slim\Http\Response $response) use($products){
		$pid = $request->getAttribute('pid');
		if(!empty($pid) && isset($products[$pid])){
			$current = getCart();
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


/**
 * @api {put} /api/cart/:product_id/buy Order a specific product in the cart
 * @apiName CartProductOrder
 * @apiGroup Cart
 *
 * @apiParam {Number} product_id The id of the product to order
 *
 * @apiSuccess {Object} ressource A structured ressource containing the state of the product's order
 */
 	$app->put('/{pid}/buy', function(Slim\Http\Request $request, Slim\Http\Response $response){
		$pid = $request->getAttribute('pid');
		sleep(rand(0,5)); // faire durer, de manière aléatoire, le temps de traitement de cette méthode
		echo json_encode(array('success' => true, 'product' => $pid));
	});
});

$app->run();

function getCart() {
	$cart = 	file_get_contents($GLOBALS['file_cart']);
	if($cart) {
		$cart= json_decode(array_values($cart),true);
	} else {
		$cart = [];
	}
	return $cart;	
}