<?php

if (!defined('_PS_VERSION_'))
	exit;
	
if (!class_exists('IndoCarrierAPI')) {
	class IndoCarrierAPI
	{
		const CACHE_TABLE = 'shipping_cost_cache';
		
		const PREFIX = 'indocarrier_';
		const RAJAONGKIR_KEY = '10c8cfeff2abc2ba01fc1dbdcc71c547';
		const RAJAONGKIR_ACCOUNT = 'starter';
		
		protected $api;
		protected $table;
		
		public function __construct()
		{
			require __DIR__.'/vendor/autoload.php';
			$this->api = new \Steevenz\Rajaongkir(array(
							'api_key' => self::RAJAONGKIR_KEY,
							'account_type' => self::RAJAONGKIR_ACCOUNT,
						));
		}
		
		public function fetchCities()
		{
			$cities = $this->api->get_cities();
			$new_cities = array();
			foreach($cities as $city) $new_cities[$city->city_id] = array(
																		'type' => $city->type,
																		'city_name' => $city->city_name,
																	);
			$cityFile =  _PS_MODULE_DIR_.'indocarrier/views/city.js';
			//~ $cityData =  'var cities = '.json_encode($new_cities);
			$cityData =  json_encode($new_cities);
			file_put_contents($cityFile, $cityData);
			
			return $new_cities;
		}

		public function getCosts($from, $to, $weight, $carrier)
		{
			$fromDB = $this->fromDB($from, $to, $weight, $carrier);
			return $fromDB === NULL ? $this->fromAPI($from, $to, $weight, $carrier) : $fromDB;
		}
		
		public function fromDB($from, $to, $weight, $carrier)
		{
			$key = md5(json_encode(array($from, $to, $weight, $carrier)));
			
			$sql =  'select value from `'._DB_PREFIX_.self::CACHE_TABLE.'` '.
					'where `key` like \''.$key.'\' '.
					'and `date` = \''.date('Y-m-d').'\';';
			//~ return $sql;
			$value = Db::getInstance()->getValue($sql, false);
			return json_decode($value, true);
		}
		
		
		public function fromAPI($from, $to, $weight, $carrier)
		{
			$costs = array();
			$tmp = (array) $this->api->get_cost($from, $to, $weight, $carrier);
			
			if (is_array($tmp)) {
				foreach ($tmp as $cost) {
					if ($cost['code'] == $carrier) {
						$costs = $cost['costs'];
						break;
					}
				}
			}
			
			// save db
			$key = md5(json_encode(array($from, $to, $weight, $carrier)));
			Db::getInstance()->autoExecute(_DB_PREFIX_.self::CACHE_TABLE, array(
				'key' => $key,
				'value' => json_encode($costs),
				'date' => date('Y-m-d'),
			), 'INSERT');
			
			return json_decode(json_encode($costs), true); // fromDB compatibility
		}
		
		public function clearCache()
		{	
			$sql =  'TRUNCATE TABLE `'._DB_PREFIX_.self::CACHE_TABLE.'`;';
			return Db::getInstance()->execute($sql);
		}
	}
}
