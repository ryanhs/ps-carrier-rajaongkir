<?php
if (!defined('_PS_VERSION_'))
	exit;

class IndoCarrier extends Module
{
	const CACHE_TABLE = 'shipping_cost_cache';
	
	public function __construct()
	{
		$this->name = 'indocarrier';
		$this->version = '1.8.1';
		$this->author = 'Ryan hs';
		$this->need_instance = 0;
		parent::__construct();
		
		$this->displayName = $this->l($this->name);
		$this->description = $this->l('indocarrier root module, contain lib only');
	}
	
	
	public function install()
	{
		if (parent::install() &&
			$this->installDB()
		) {
			return true;
		}
		
		return false;
	}
	
	public function installDB()
	{
		$sql =  'CREATE TABLE `'._DB_PREFIX_.self::CACHE_TABLE.'` ( '.
				'`id_shipping_cost_cache` int NOT NULL AUTO_INCREMENT PRIMARY KEY, '.
				'`date` date NOT NULL, '.
				'`key` varchar(64) NOT NULL, '.
				'`value` text '.
				');';
		
		return Db::getInstance()->execute($sql);
	}
	
	public function uninstall()
	{
		if (parent::uninstall() &&
			$this->uninstallDB()
		) {	
			return true;
		}
		
		return false;
	}
	
	public function uninstallDB()
	{
		$sql =  'DROP TABLE `'._DB_PREFIX_.self::CACHE_TABLE.'`;';
		return Db::getInstance()->execute($sql);
	}
}
/*
class IndoCarrier extends CarrierModuleCore
{
	const PREFIX = 'indocarrier_';
	
	protected $_hooks = array(
		'actionCarrierUpdate'
	);
	
	protected $_carriers = array(
	//"Public carrier name" => "technical name",
		'POS' => 'pos',
		'JNE' => 'jne',
		'TIKI' => 'tiki'
	);
	
	public function __construct()
	{
		$this->name = 'indocarrier';
		$this->version = '1.8.1';
		$this->author = 'Ryan hs';
		$this->need_instance = 0;
		parent::__construct();
		
		$this->displayName = $this->l($this->name);
		$this->description = $this->l(implode(', ', array_keys($this->_carriers)));
	}

	public function install()
	{
		if (parent::install()) {
			foreach ($this->_hooks as $hook) {
				if (!$this->registerHook($hook)) {
					return false;
				}
			}
			
			if (!$this->createCarriers()) {
				return false;
			}
			
			return true;
		}
		
		return false;
	}
	
	public function createCarriers()
	{
		foreach ($this->_carriers as $key => $value) {
			$carrier = new Carrier();
			$carrier->name = $key;
			$carrier->active = true;
			$carrier->deleted = 0;
			$carrier->shipping_handling = false;
			$carrier->range_behavior = 0;
			$carrier->delay[Configuration::get('PS_LANG_DEFAULT')] = $key;
			$carrier->shipping_external = true;
			$carrier->is_module = true;
			$carrier->external_module_name = $this->name;
			$carrier->need_range = true;
			
			if ($carrier->add()) {
				$groups = Group::getGroups(true);
				foreach ($groups as $group) {
					Db::getInstance()->autoExecute(_DB_PREFIX_.'carrier_group', array(
						'id_carrier' => (int) $carrier->id,
						'id_group' => (int) $group['id_group'],
					), 'INSERT');
				}
				
				$rangePrice = new RangePrice();
				$rangePrice->id_carrier = $carrier->id;
				$rangePrice->delimiter1 = '0';
				$rangePrice->delimiter2 = '10000000';
				$rangePrice->add();
				
				$rangeWeight = new RangeWeight();
				$rangeWeight->id_carrier = $carrier->id;
				$rangeWeight->delimiter1 = '0';
				$rangeWeight->delimiter2 = '10000000';
				$rangeWeight->add();
				
				$zones = Zone::getZones(true);
				foreach ($zones as $z) {
					Db::getInstance()->autoExecute(_DB_PREFIX_.'carrier_zone', array(
						'id_carrier' => (int) $carrier->id,
						'id_zone' => (int) $z['id_zone']
					), 'INSERT');
					Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_.'delivery', array(
						'id_carrier' => (int) $carrier->id,
						'id_range_price' => (int) $rangePrice->id,
						'id_range_weight' => null,
						'id_zone' => (int) $z['id_zone'],
						'price' => '0'
					), 'INSERT');
					Db::getInstance()->autoExecuteWithNullValues(_DB_PREFIX_.'delivery', array(
						'id_carrier' => (int) $carrier->id,
						'id_range_price' => null,
						'id_range_weight' => (int) $rangeWeight->id,
						'id_zone' => (int) $z['id_zone'],
						'price' => '0'
					), 'INSERT');
				}
				
				// carriers logo
				copy(dirname(__FILE__).'/views/img/'.$value.'jpg', _PS_SHIP_IMG_DIR_.'/'.$carrier->id.'.jpg');
				
				Configuration::updateValue(self::PREFIX.$value, $carrier->id);
				Configuration::updateValue(self::PREFIX.$value.'_reference', $carrier->id);
			}
		}
		
		return true;
	}
	
	public function deleteCarriers()
	{
		foreach ($this->_carriers as $value) {
			$tmpCarrierId = Configuration::get(self::PREFIX.$value);
			$carrier = new Carrier($tmpCarrierId);
			$carrier->delete();
		}
		
		return true;
	}
	
	public function uninstall()
	{
		if (parent::uninstall()) {
			foreach ($this->_hooks as $hook) {
				if (!$this->unregisterHook($hook)) {
					return false;
				}
			}
			
			if (!$this->deleteCarriers()) {
				return false;
			}
			
			return true;
		}
		
		return false;
	}
	
	
	
	
	public function hookActionCarrierUpdate($params)
	{
		if ($params['carrier']->id_reference == Configuration::get(self::PREFIX.'swipbox_reference')) {
			Configuration::updateValue(self::PREFIX.'swipbox', $params['carrier']->id);
		}
	}
	
	
	
	public function getOrderShippingCost($cart, $shippingCost)
	{
		$carrier = new Carrier($cart->id_carrier);
		$products = $cart->getProducts();
		$address = new Address($cart->id_address_delivery);
		$cityId = false;
		
		$cityFile = _PS_MODULE_DIR_.'indocarrier/views/city.js';
		$cities = json_decode(file_get_contents($cityFile), true);
		foreach($cities as $id => $city) {
			if ($address->city == '('.$city['type'].') '.$city['city_name']) {
				$cityId = $id;
				break;
			}
		}
		
		$rajaongkir = new Rajaongkir(array(
						'api_key' => '10c8cfeff2abc2ba01fc1dbdcc71c547',
						'account_type' => 'starter',
					));
		
		$weight = 0;
		foreach($products as $product) $weight += $product['weight'];
		
		// check to db first, then fallback to here
		$costs = (array) $rajaongkir->get_cost(array('city' => 23), array('city' => $cityId), $weight * 1000, 'pos');
		$costs = count($costs) > 0 ? $costs[0] : $costs;
		
		$costs = $costs['costs'][0]['cost'][0];
		$costValue = $costs['value'];
		//~ print_r($costValue);exit;
		
		return $cityId === false ? false : $costValue;
	}
	
	public function getOrderShippingCostExternal($cart)
	{
		return $this->getOrderShippingCost($cart, 0);
	}
	
	
}
*/
