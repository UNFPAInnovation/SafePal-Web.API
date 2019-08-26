<?php
namespace SafePal;

//geocoder
use \Geocoder\Query\ReverseQuery as gmaps;
//use \Geocoder\Provider\GoogleMaps as gmaps;

//adapter
use \Ivory\HttpAdapter\CurlHttpAdapter as CurlHttpAdapter;

/**
* Handles all mapping-related work
*/
final class SafePalMapping
{
	protected $curl;
	protected $maps;
	protected $adapter;
	protected $provider;
	protected $geocoder;

	 function __construct()
    {
    	//curl http adapter -- *note: should be optional since slim already implements PSR-7
        //$this->curl = new CurlHttpAdapter();
        $this->adapter = new \Http\Adapter\Guzzle6\Client();
        $this->provider = $provider = new \Geocoder\Provider\GoogleMaps\GoogleMaps($this->adapter, 'ug', getenv('GMAPS_KEY'));
        $this->geocoder = new \Geocoder\StatefulGeocoder($provider, 'en');
    }

	public function GetLocationDistrict($lat, $long){

		if (!empty($lat) && !empty($long)) {

			$district = $this->maps->reverse($lat, $long)->first()->getLocality();

			if ($district) {
				return $district;
			}
		}
	}

	public function checkIfGeoPointInRadius($lat1, $long1, $lat2, $long2, $radius = 1000000.0){ //5km radius
		//calculate distance --
		$distance = $this->calculateGreatCircleDistance(floatval($lat1), floatval($long1), floatval($lat2), floatval($long2));

		if($distance <= $radius) return true;
		return false;
	}

	/*
	-- alternate implementation of Haversian Formula
	*/
	private function calculateGreatCircleDistance($lat1, $long1, $lat2, $long2, $earthRadius = 6371){

		/// convert from degrees to radians
		$lat1 = deg2rad($lat1);
	  	$long1 = deg2rad($long1);
	  	$lat2 = deg2rad($lat2);
	  	$long2 = deg2rad($long2);

	  	$latDelta = $lat1 - $lat2;
	  	$lonDelta = $long1 - $long2;

	  	$angle = 2 * asin(sqrt(pow(sin($latDelta / 2), 2) +
	    cos($lat1) * cos($lat2) * pow(sin($lonDelta / 2), 2)));

	  	return $angle * $earthRadius;


	  	/*
	  		$theta = $lat1 - $long1;

		$d = sin(deg2rad($lat1)) * sin(deg2rad($lat2))
			+ cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * cos(deg2rad($theta));

		$d = (float) rad2deg(acos($d)) * 69.09; //converted to miles from nautical miles? (60 * 1.1515)
	  	*/
	}

	//reverse geocode location
	public function ReverseGeoCodeLocation($lat, $long){
		$result = $this->geocoder->reverseQuery(gmaps::fromCoordinates($lat, $long))->first();
		$location['country'] = $result->getPolitical();
		$location['neighbourhood'] = $result->getNeighborhood();
		$location['address'] = $result->getFormattedAddress();
		$location['sublocalities'] = $result->getSubLocalityLevels();
		$location['district'] = $result->getLocality();
		$location['subcounty'] = $result->getSubLocality();
		$location['streetname'] = $result->getStreetName();
		echo $location;
		return $location;
	}
}
?>
