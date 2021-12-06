<?php

session_start();
require_once '../vendor/autoload.php';
$token=false;
if(isset($_GET['token'])) {
	$token = $_GET['token'];
	if($$token == '[votre adresse mail ici!]'){
		$$token = '';
	}
}
if($token) {
	$token = md5($token);
} else {
	die('You must provide a token via a GET parameter to all API calls');	
}
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;

$app = new \Slim\App();
$app->add(function(ServerRequestInterface $request, ResponseInterface $response, callable $next){
	$response = $response->withHeader('Content-type', 'application/json; charset=utf-8');
//	$response = $response->withHeader('Access-Control-Allow-Origin', 'http://allweb.fun');
	$response = $response->withHeader('Access-Control-Allow-Origin', '*');
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
 * @api {get} /api/products?page=:page&sort=:sort&field=:field Get products
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
	if(!$page) {
		$page=1;
	}
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
 * @api {delete} /api/cart/:product_id Remove product from cart
 * @apiName EmptyCartProduct
 * @apiGroup Cart
 *
 * @apiParam {Number} product_id The id of the product to remove from the cart
 *
 * @apiSuccess {Array} cart The cart content
 */
$app->delete('/{pid}', function(Slim\Http\Request $request, Slim\Http\Response $response) {
	$pid = $request->getAttribute('pid');

	$current = getCart();
	$final = [];
	foreach($current as $k=>$v) {
		if($v['id'] != $pid){
			$final[$k]=$v;
		}
	}
	$json = json_encode(array_values($final));
	file_put_contents($GLOBALS['file_cart'],$json);
	echo $json;
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
	if($product = getProduct($pid)){
		$cart = getCart();
		$key = rangDansCart($cart, $pid);
		if(!isset($cart[$key]) ){

			$cart[$key]=[];
			$cart[$key]['id'] = $product['id'];
			$cart[$key]['nom'] = $product['nom'];
			$cart[$key]['qte'] = 0;
			$cart[$key]['ok'] = false;
			$cart[$key]['prix'] = 0;
		} 
		$cart[$key]['key'] = $key;
		$cart[$key]['qte'] += 1;
		$cart[$key]['prix'] = $cart[$key]['qte'] * $product['prix'];

		$to_json = json_encode(array_values($cart));
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
		sleep(rand(0,5)); // faire durer, de mani�re al�atoire, le temps de traitement de cette m�thode
		echo json_encode(array('success' => true, 'product' => $pid));
	});
});

$app->run();

function getCart() {
	$cart = file_get_contents($GLOBALS['file_cart']);
	if($cart) {
		$cart= array_values(json_decode($cart,true));
	} else {
		$cart = [];
	}
	return $cart;	
}
function getProduct($pid) {
	global $products;
	foreach($products as $product) {
		if($product['id'] == $pid) {
			return $product;
		}
	}
}
function rangDansCart($cart, $pid) {
	foreach($cart as $k=>$v) {
		if($v['id'] == $pid) {
			return $k;
		}
	}

	return intval(is_array($cart) ? count($cart) : 0);
}
