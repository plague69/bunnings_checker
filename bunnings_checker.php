<?php
die('Thanks for your support! Bunnings has implemented a few checks in their api in the form of cookies and cloudflare hotlink detection. If I have time, Bunnings_Checker will return at a later time, so keep an eye out!
<br/>In the mean time, source code for the bunnigns checker is available on GitHub. Feel free to have a look here: <a href="https://github.com/plague69/bunnings_checker">https://github.com/plague69/bunnings_checker</a><br/><br/>Thanks again for all the support and trust in using this checker!');

//ini_set('display_errors', 1); ini_set('display_startup_errors', 1); error_reporting(E_ALL);

define('STORES_FILE', 'stores.txt');
//define('HOME_STORE', '6359'); // Notting Hill VIC
define('HOME_STORE', '7341'); // Forbes NSW
define('CACHE_PATH', "./tmp/cache_");
define('API_BASE', "https://www.bunnings.com.au/api/v1");
define('CACHE_SECONDS', 300);
$item_id = null;

$_BASE_STORES = [
	6042, // VIC, Port Melbourne
	//6299, VIC Backup, Hawthorn
	5121, // SA, Mile End
	//5024, // SA Backup, Kent Town
	7313, // NSW, Alexandria
	//7175, // NSW Backup, Ashfield
	8197, // QLD, Indooroopilly
	//8087, // QLD Backup, Rocklea
	2260, // WA, Subiaco
	//2052, // WA Backup, Belmont
	4467, // TAS, Mornington
	//4455, // TAS Backup, Glenorchy
	2320, // NT, Darwin
	//2315, // NT Backup, Palmerston
];

function GetItemId($string)
{
	if (is_numeric($string) && strlen($string) == 7) {
		return $string;
	} else {
		$string = substr($string, -7);
		if (is_numeric($string)) {
			return $string;
		} else {
			return false;
		}
	}
}

function GetProductInformation($item_id)
{
	$dataraw = file_get_contents(API_BASE.'/producttile?region=VICMetro&productsId='.$item_id);
	return json_decode($dataraw, true)[0];
}

function GetStoreItem($item_id, $store_id)
{
	$homeStoreData = API_BASE.'/store/'.$store_id.'/'.$item_id;
	$homeStoreData['StockStatus'][0];
	$homeStoreData['StockStatus']['Code'];
	$homeStoreData['StockStatus']['Message'];
	$homeStoreData['StockStatus']['StockCount'];
}

function GetNearestStock($item_id, $home_store = HOME_STORE, $max_val = 1000)
{
	$dataraw = file_get_contents(API_BASE.'/store/'.HOME_STORE.'/nearest/'.$max_val.'/'.$item_id);
	return json_decode($dataraw, true);
}

function GetAisleNumber($item_id, $store)
{
	$dataraw = file_get_contents(API_BASE.'/ProductAisle/getAisleNumber?countryCode=AU&locationCode='.$store_id.'&fineLine='.$item_id);
	$data = json_decode($dataraw, true);
	if ($data['Success'] == 'true') {
		return $data['Response'];
	} else {
		return false;
	}
}

function SaveStores()
{
	$dataset = GetNearestStock('0078679');

	$append = 0;
	foreach ($dataset as $data) {
		unset($data['StockStatus']);
		$data['StoreInfo']['StoreNumber'] = $data['StoreNumber'];
		file_put_contents('stores.txt', json_encode($data['StoreInfo']).PHP_EOL, $append);
		if ($append === 0) $append = FILE_APPEND;
	}
}

function cache_file($item_id)
{
	if (isset($_REQUEST['skip'])) $skip = '1';
	else $skip = '';
	return CACHE_PATH.$item_id.$skip;
}

function cache_display($item_id)
{
	$file = cache_file($item_id);
	if(!file_exists($file)) return;
	if(filemtime($file) < time() - CACHE_SECONDS) return;
	readfile($file);
	exit;
}

function cache_page($content)
{
	global $item_id;
	if(false !== ($f = @fopen(cache_file($item_id), 'w'))) {
		fwrite($f, $content);
		fclose($f);
	}
	return $content;
}

if (!$_REQUEST['item']) {
	if (!file_exists(STORES_FILE)) SaveStores();
	$stores = file(STORES_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
	$stores_list = [];

	echo '<form method="GET" action="'.$_SERVER['PHP_SELF'].'" >';
	echo '<select name="store" style="display:none">';
	foreach ($stores as $k => $store) {
		$store = json_decode($store, true);
		$stores_list[$store['Postcode'][0]][] = $store;
		echo '<option value="'.$store['StoreNumber'].'">'.$store['StoreName'].'</option>';
	}
	echo '</select>';

	echo '<input type="text" style="min-width:64em" name="item"></input><br /><i>Please enter iten number or paste product link</i><br /><br />';
	echo '<input type="checkbox" id="chk-skip" name="skip" value="skip" checked="checked"><label for="chk-skip">Hide no stock</lable><br>
<br /><br />';
	echo '<input type="submit" name="submit" value="Submit">';
	echo '</form>';
} else if (!$_REQUEST['load']) {
	$link = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://".$_SERVER['HTTP_HOST'].$_SERVER['REQUEST_URI']."&load=1";
	echo '<style>body{margin:0} #loader-text{position:absolute;top:calc(50vh + 64px);left:50vw;transform:translate(-50%,-50%);text-align:center} #main-wrapper{background:url(load.gif) center center no-repeat} #main-page{border:0;height:100vh;width:100vw;margin:0;padding:0;box-sizing:border-box;display:block}</style>';
	echo '<div id="main-wrapper"><span id="loader-text">Refreshing stock levels from Bunnings<br/>I/N: '.GetItemId($_REQUEST['item']).'</span><iframe id="main-page" src="'.$link.'" onLoad="document.getElementById(\'main-wrapper\').style.background = \'None\'; document.getElementById(\'loader-text\').style.display = \'None\'"></iframe></div>';
} else {
	$item_id = GetItemId($_REQUEST['item']);

	cache_display($item_id);
	ob_start('cache_page');

	$product = GetProductInformation($item_id);

	echo '<title>'.$product['displayName'].'</title>';

	echo '<section class="section-information">';
	echo '<h1>'.$product['displayName'].' - $'.$product['price'].'</h1>';
	echo '<a href="https://www.bunnings.com.au'.$product['productUrl'].'" target="_BLANK">Goto product page</a><br/><br/>';
	echo '<img src="'.$product['productImage'].'"/>';
	echo '</section>';

	$dataset = GetNearestStock($item_id);

	usort($dataset, function($a, $b) {
		if ($a['StoreInfo']['Postcode'][0] == $b['StoreInfo']['Postcode'][0]) {
			return strcmp($a['StoreInfo']['StoreName'], $b['StoreInfo']['StoreName']);
		} else {
			return $a['StoreInfo']['Postcode'] - $b['StoreInfo']['Postcode'];
		}
	});
	
	$sections = [];
	$last_post = '';
	echo '<section class="section-list"><table class="display-table" cellpadding="0" cellspacing="0">';

	foreach($dataset as $line) {
	    $state = $line['StoreInfo']['State'];

		if (@$_REQUEST['skip'] != "skip" || (isset($line['StockStatus'][0]['StockCount']) && intval($line['StockStatus'][0]['StockCount']) > 0)) {
		    $sections[$state][] = '<tr>'.
            			            '<td>'.$line['StoreInfo']['StoreName'].'</td>'.
            			            '<td>'.$line['StockStatus'][0]['StockCount'].'</td>'.
            			            '<td>'.$line['StockStatus'][0]['Message'].'</td>'.
            			        '</tr>';
		    
		}
	}
	
	foreach ($sections as $name => $section) {
	    echo '<tr class="state-line"><td colspan="3"><a name="'.$name.'">'.$name.'</a></td></tr>';
		echo '<tr class="cats-line"><td>Store</td><td>Stock</td><td>Message</td></tr>';
		echo implode($section);
	}
	
	echo '</table></section>';

	echo '<section class="section-contents"><h1>Contents</h1><ul>';
	foreach (array_keys($sections) as $state) {
		echo '<li><a href="#'.$state.'">'.$state.'</a></li>';
	}
	echo '</ul><br/><a href="'.$_SERVER['PHP_SELF'].'">Search Another</a><br/><br/><iframe style="border:0" src="counter.php" width="120" height="50"></iframe></section>';
}
?>
<style>
body {
    overflow-x: hidden;
}

.section-information {
	position: relative;
	left: 150px;
	margin-bottom: 20px;
}

.section-list {
	position: relative;
	left: 150px;
}

.section-contents {
	position: fixed;
	top: 0;
	left: 20px;
}

.display-table td {
	border: 1px solid #000000;
	padding: 5px;
}

.state-line {
	font-weight: 600;
	color: #EEEEEE;
	background: #000000;
}

.cats-line {
	font-weight: 600;
}
</style>
<?php
ob_flush();
?>