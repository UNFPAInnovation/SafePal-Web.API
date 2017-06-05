<?php 
namespace SafePal;

/**
* Helper for report-related work
*/
final class SafePalReport
{
	private $db;

	function __construct()
	{
		$this->db = new SafePalDB();
	} 

	//data from json/array
	public function AddReport($reportarray){
		$typeID = $this->getTypeID($reportarray['type']);
		$data = $this->db->SaveReport((array)$reportarray, $typeID);
		$this->db = null; //close connection
		return $data;
	}

	public function AddNote($note){
		$status = $this->db->AddCaseActivity($note);
		$this->db = null; //close connection
		return $status;
	}

	public function GetAllNotes(){
		$notes = $this->db->GetNotes();
		return $notes;
	}

	//get all reports
	public function GetAllReports($csoID){
		$reports = $this->db->GetReports($csoID);
		return $reports;
	}

	//add contact to report
	public function AddContact($caseNumber, $contact){
		$response = $this->db->AddContactToReport($caseNumber, $contact);
		return $response;
	}

	//-- helper function to get ID
	private function getTypeID($type){
		$typeID = null;
		switch ($type) {
			case getenv('CASE_TYPE_TWO'):
				$typeID = 2;
				break;
			case getenv('CASE_TYPE_THREE'):
				$typeID = 3;
				break;
			case getenv('CASE_TYPE_FOUR'):
				$typeID = 4;
				break;
			case getenv('CASE_TYPE_FIVE'):
				$typeID = 5;
				break;
			default:
				$typeID = 1;
				break;
		}

		return $typeID;
	}

}
?>