<?php
if (!defined('_PS_VERSION_'))
	exit;

require __DIR__.'/../../IndoCarrierAPI.php';

class IndoCarrierTestModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		$api = new IndoCarrierAPI();
		
		var_dump(
			//~ $api->fetchCities()
			
			//~ IndoCarrier::CACHE_TABLE
			
			//~ $api->fromDB(1,1, 10, 'jne')
			
			
			$api->getCosts( array('city' => 23), array('city' => 107), 1000, 'jne')
			
			//~ $api->clearCache()
		);
		exit;
	}
}
