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

		$data = $this->db->SaveReport((array)$reportarray);
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
	public function GetAllReports(){
		$reports = $this->db->GetReports();
		return $reports;
	}

	//add contact to report
	public function AddContact($caseNumber, $contact){
		$response = $this->db->AddContactToReport($caseNumber, $contact);
		return $response;
	}

}
?>