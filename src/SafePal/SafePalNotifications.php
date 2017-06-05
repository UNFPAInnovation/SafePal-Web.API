<?php

namespace SafePal;

//require_once('../../vendor/africastalking/africastalking/src/AfricasTalkingGateway.php');

use SendGrid;
//use AfricasTalkingGateway;


/**
* -- handle email and SMS notifications
*/
final class SafePalNotifications
{
	protected $mailer;
	protected $configurator;
	protected $messager;
	protected $caseNumber;

	function __construct($caseNumber){
		$this->mailer = new \SendGrid(getenv('SENDGRID_KEY'));
		$this->messager = new AfricasTalkingGateway(getenv('AIT_USERNAME'), getenv('AIT_KEY'));
		$this->caseNumber = $caseNumber;
	}
	//send email notification
	public function sendEmailNotification($emails){

		//dev
		$emails = array("joshoke2003@gmail.com", "otine@unfpa.org");

		$emailSent = false;

		//from
		$from = new \SendGrid\Email("SafePal Team", getenv('SFP_EMAIL'));

		//subject
		$subject = getenv('NOTIFICATION_SUBJECT')." ".$this->caseNumber;

		//content
		//$content = new SendGrid\Content("text/html", "".getenv('NOTIFICATION_MESSAGE')." <b>".$this->caseNumber."</b><br/>".getenv('NOTIFICATION_LOGIN_MESSAGE')." <a href='".getenv('DASHBOARD_LINK')."/reports/".$this->caseNumber."' 'target='_blank'> here to got to the dashboard </a>"."".getenv('SIGN_OUT_MESSAGE')."");
		$content = new SendGrid\Content("text/html", "".getenv('NOTIFICATION_MESSAGE')." <b>".$this->caseNumber."</b><br/>".getenv('NOTIFICATION_LOGIN_MESSAGE')." <a href='".getenv('DASHBOARD_LINK')."' 'target='_blank'> here to got to the dashboard </a>"."".getenv('SIGN_OUT_MESSAGE')."");

		//add recipients
		for ($i=0; $i < sizeof($emails); $i++) { 
			$to = new SendGrid\Email(null, $emails[$i]);
			$mail = new SendGrid\Mail($from, $subject, $to, $content);
			$emailSent = $this->mailer->client->mail()->send()->post($mail);
		}
		
		return $emailSent;
	}

	//send smsNotification
	public function sendSMSNotification($recipients){
		//dev
		//$recipients = "+256753601781,+256793396525,+256792587250";

		$message = "".getenv('NOTIFICATION_MESSAGE')." ".$this->caseNumber.". Log into the SafePal dashboard to view it";
		$failedRecipients = array();
		$passedRecipients = array();

		try {

			$results = $this->messager->sendMessage($recipients, $message);

			for ($i=0; $i < sizeof($results); $i++) { 
				if ($results[$i]->status !== "Success") {
					array_push($failedRecipients, $results[$i]->number);
				}else{
					array_push($passedRecipients, $results[$i]->number);
				}
			}

		} catch (Exception $e) {
			$messageStatus = array("status" => "error", "error" => $e->getErrorMessage());
		}

		$messageStatus = array("successful" => $passedRecipients, "failed" => $failedRecipients);

		return $messageStatus; 
	}
	
}

?>