<?php

namespace SafePal;

// //db
// use SafePalDB;

//mapping
 use SafePalMapping;

/**
* 
*/
final class SafePalCSO
{
	/* PROPERTIES */
	private $cso_name;

	private $cso_latitude;

	private $cso_longitude; 

	private $typeofownership; 

	private $contacts;

	private $db;

	private $map;


	 function __construct()
	{
		$this->db = new SafePalDb();
		//$this->map = new SafePalMapping;
	} 

	//add new cso
	// public function AddCSO($name, $latitude, $longitude, $contactsArray, $typeofownership = "NGO"){
	// 	$this->cso_name = $name;
	// 	$this->cso_latitude = $latitude;
	// 	$this->cso_latitude = $longitude;
	// 	$this->contacts = $contacts; 
	// 	$this->typeofownership = $typeofownership;

	// 	$cso = $this;

	// 	$res = $this->db->AddCSO($cso);
	// 	return $res;
	// }

	//get all CSOs
	public function GetAllCSOs(){
			$csos = $this->db->GetCSOs();
			return $csos;
	}
	//get all CSO
	public function GetCSO($cso_id){
		$csos = $this->db->GetCSO($cso_id);
		return $csos;
}

	//add csos
	public function AddCSO($reportarray){
		$data = $this->db->AddCSO((array)$reportarray, $typeID);
		$this->db = null; //close connection
		return $data;
	}
	//update csos
	public function updateCSO($csoarray){
		$data = $this->db->updateCSO((array)$csoarray, $typeID);
		$this->db = null; //close connection
		return $data;
	}

	//return array of csos with 
	public static function GetNearestCSO($reportergps){

		$gps = json_decode($reportergps);

		//get district name
		$district = $this->map->GetLocationDistrict($gps['reporter_lat'], $gps['reporter_long']);

		if (!empty($district)) {
			$csos = $this->db->NearestCSO($district);
			return $csos;
		}

		$this->db = null; //close connection
	}


	//GetCSOs
}

?>