<?php
namespace SafePal;

use Carbon\Carbon as carbon;

//use Predis as redis;


//register Redis
//redis\Autoloader::register(); // -- TO-DO: may do some caching for reports with redis

/**
* Handles all database work/interaction
*/
final class SafePalDB
{
	protected $pdo;
	//protected $redisclient;
	protected $cleardb;
	protected $dateUtil;

	function __construct()
	{

		if (getenv('APP_ENV') != 'dev') {
			$this->pdo = new \PDO('mysql:host='.getenv('HOST').';dbname='.getenv('DB').';port='.getenv('PORT').';charset=utf8',''.getenv('DBUSER'), ''.getenv('DBPWD'));
		} else {
			$cleardb = parse_url(getenv("CLEARDB_DATABASE_URL"));
			$this->pdo = new \PDO("mysql:host=".$cleardb['host'].";dbname=".substr($cleardb["path"], 1).";charset=utf8",$cleardb['user'], $cleardb['pass']);
		}

		$this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
		$this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
		$this->pdo->setAttribute(\PDO::MYSQL_ATTR_INIT_COMMAND, "SET NAMES 'utf8'");


		//date util
		$this->dateUtil = new carbon();
	}

	//check if user exists
	public function CheckUser($userid){

		$user = $this->pdo->prepare(getenv('CHECKUSER_QUERY'));
		$user->execute(array("userid" => $userid));
		$result = $user->fetchColumn();

		return ($result) ? true : false;
	}

	//save new report
	public function SaveReport($report, $typeID){
		$result = $this->AddReport($report, $typeID);
		return $result;
	}

	//add report to db
	private function AddReport($report, $typeID){
		$result = null;

		$prefix = getenv('DEFAULT_PREFIX');

		if ($report['report_source'] !== 'web') {
			$prefix = 'SPM';
		}

		$reporter_relationship = 'Unknown';

		if (!empty($report['reporter_relationship'])) {
			$reporter_relationship = $report['reporter_relationship'];
		}

		$query = getenv('ADD_REPORT_QUERY_1').' VALUES '.getenv('ADD_REPORT_QUERY_2');

		if (!empty($report)) {
			$query_params = array(
						'type' => $report['type'],
						'typeID' => $typeID,
				        'gender' => $report['gender'],
				        'reporter' => $report['reporter'],
				        'reporter_relationship' => $reporter_relationship,
				        'district' => $district,
				        'latitude' => $report['latitude'],
				        'longitude' => $report['longitude'],
				        'location' => $district,
				        'reportDate' => $report['reportDate'],
				        'incident_date' => $report['incident_date'],
				    	'perpetuator' => $report['perpetuator'],
				    	'age' => $report['age'],
				    	'contact' => $report['contact'],
				    	'details' => $report['details'],
				    	'report_source' => $report['report_source'],
						);

			$stmt = $this->pdo->prepare($query);

			$res = $stmt->execute(filter_var_array($query_params));
			$result['caseNumber'] = null;

			if ($res) {
				//-- hack to construct caseNumber from server side instead of rendering on client
				$cNumber = strval($this->getCaseNumber($this->pdo->lastInsertId(), $prefix, $typeID));
				$casenum = $this->pdo->lastInsertId();
				$q = getenv('UPDATE_CASE_NUMBER_QUERY_1')." '".$cNumber."' ".getenv('UPDATE_CASE_NUMBER_QUERY_2')." ".$casenum;
				$q = $this->pdo->prepare($q);
				$status = $q->execute();

				if ($status) {
					$result['caseNumber'] = $cNumber;
					$safePalNotifications = new SafePalNotifications($result['caseNumber']);
					$mapDistance = new SafePalMapping();
					$csos = $this->GetCSOs(); //get list of csos
					$nearbycsos = array();
					
					for ($i=0; $i < sizeof($csos); $i++) {

						$isCSOInRadius = $mapDistance->checkIfGeoPointInRadius($report['latitude'], $report['longitude'],$csos[$i]['cso_latitude'], $csos[$i]['cso_longitude']); //5km radius

						if ($isCSOInRadius) {
							//--notify via email TO-DO: Refactor -- also change to directly working with $result['csos'] with indices
							array_push($nearbycsos, $csos[$i]);
							
							if (!empty($csos[$i]['cso_email'])) { //only send email to csos with emails
								$mailNotification = $safePalNotifications->sendEmailNotification($csos[$i]['cso_email']);
								$this->LogNotification($csos[$i]['cso_email'], $result['caseNumber'], 'email', $this->dateUtil::now(getenv('SET_TIME_ZONE'))->toDateTimeString());
							}
							if (!empty($csos[$i]['cso_phone_number'])) {
								$sms = $safePalNotifications->sendSMSNotification($csos[$i]['cso_phone_number']); //only send to csos with sms numbers
								$this->LogNotification($csos[$i]['cso_email'], $result['caseNumber'], 'sms', $this->dateUtil::now(getenv('SET_TIME_ZONE'))->toDateTimeString());
							}

							$this->LogReferral($result['caseNumber'], $csos[$i]['cso_details_id'], $this->dateUtil::now(getenv('SET_TIME_ZONE'))->toDateTimeString());
						}

					}

					$result['csos'] = (sizeof($nearbycsos) < 1) ? $csos : $nearbycsos;

				}
			}
		}

		return $result;
	}

	//add note/case activity
	public function AddCaseActivity($note){
		$params = array(
			'note' => $note['note'],
			'action' => $note['action'],
			'action_date' => $note['action_date'],
			'user' => $note['user'],
			'caseNumber' => $note['caseNumber'],
			);

		$stmt = $this->pdo->prepare(getenv('NEW_CASE_ACTIVITY_QUERY'));

		$res = $stmt->execute(filter_var_array($params));

		$actionStatus = 'In Progress';

		if ($res) { //should only update status of case if note has been successfully logged

			//need to run second query to update case status
			if ((strpos($note['action'], 'closed')) !== false) {
				$actionStatus = 'Closed';
			}
			$q = getenv('UPDATE_CASE_STATUS_QUERY_1')." '".$actionStatus."' ".getenv('UPDATE_CASE_STATUS_QUERY_2')." '".$note['caseNumber']."'";

			$q = $this->pdo->prepare($q);
			$status = $q->execute();
			return $status;
		} else{
			return false; //failed to log case activity
		}

	}

	public function GetNotes(){
		$q = $this->pdo->prepare(getenv('GET_ALL_NOTES_QUERY'));
		$q->execute();
		return $q->fetchAll();
	}

	//get all reports
	public function GetReports($csoID){
		$report = array();

		$q = $this->pdo->prepare(getenv('GET_ALL_REPORTS_QUERY'));
		$q->execute();
		$reports["all"] = $q->fetchAll();

		$reports["user"] = $this->GetUserReports($csoID);

		return $reports;
	}

	private function GetUserReports($csoID){
		//get user specific data from join
		$uq = $this->pdo->prepare(getenv('GET_ALL_CSO_SPECIFIC_REPORTS_QUERY_1')." ".intval($csoID)." ".getenv('GET_ALL_CSO_SPECIFIC_REPORTS_QUERY_2'));
		$uq->execute();
		return $uq->fetchAll();
	}

	public function AddContactToReport($caseNumber, $contact){
		$q = getenv('UPDATE_CONTACT_QUERY_1')." '".$contact."' ".getenv('UPDATE_CONTACT_QUERY_2')." '".$caseNumber."'";
		$q = $this->pdo->prepare($q);
		return $q->execute();
	}

	//get all CSOs
	private function GetCSOs(){
		$query = $this->pdo->prepare(getenv('GET_ALL_CSOS_QUERY'));
		$query->execute();

		return $query->fetchAll();
	}

	//get district csos
	public function NearestDistrictCSO($district){
		//query
		$nCSO = $this->pdo->prepare(getenv('GET_CSO_BY_DISTRICT_QUERY'));
		$nCSO->execute(array("district" => $district));
		$centres = $nCSO->fetchAll();

		if (sizeof($centres) > 0) {
			return $centres;
		}

		return null;
	}

	//check auth
	public function CheckAuth($user, $hash){
		$query = $this->pdo->prepare(getenv('CHECKAUTH_QUERY'));
		
		$query->execute(array("hash" => $hash, "username" => $user));

		$result = $query->fetchAll();
		$userDetails = array();

		if (sizeof($result) > 0) {
			$userDetails['userid'] = $result[0]['userid'];
			$userDetails['cso_id'] = $result[0]['cso_id'];
		}
		return $userDetails;
	}

	// -- construct caseNumber ---TO-DO: refactor logic
	private function getCaseNumber($caseID, $prefix, $typeID){
		$casenum = $this->padCaseID($caseID);
		$yr = date('y');
		return $prefix.$yr.$casenum.strval($typeID);
	}

	//-pad case id based on length
	private function padCaseID($caseID){
		return strval(str_pad(strval($caseID), 6, "0", STR_PAD_LEFT)); //ensure value is always 6 digits-long
	}

	//log notification
	private function LogNotification($contact, $caseNumber, $type_of_notification, $notificationDate){
		$params = array(
			'contact' => strval($contact),
			'caseNumber' => $caseNumber,
			'type_of_notification' => $type_of_notification,
			'notificationDate' => $notificationDate
			);

		$stmt = $this->pdo->prepare(getenv('LOG_NOTIFICATION_QUERY'));

		$res = $stmt->execute(filter_var_array($params));

		return $res;
	}

	//log referral
	private function LogReferral($caseNumber, $csoID, $referralDate){
		$params = array(
			'caseNumber' => $caseNumber,
			'csoID' => intval($csoID),
			'referralDate' => $referralDate
			);

		$stmt = $this->pdo->prepare(getenv('LOG_REFERRAL_QUERY'));

		$res = $stmt->execute(filter_var_array($params));

		return $res;
	}

	public function LogAccess($user, $eventType ='login'){
		$params = array(
			'user' => $user,
			'eventType' => $eventType,
			'eventTime' =>  $this->dateUtil::now(getenv('SET_TIME_ZONE'))->toDateTimeString()
			);

			$stmt = $this->pdo->prepare(getenv('LOG_ACCESS_QUERY'));

			$res = $stmt->execute(filter_var_array($params));

			return $res;
	}
}
?>
