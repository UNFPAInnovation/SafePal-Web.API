<?php
namespace SafePal;

use SendGrid as sendGrid;

/**
* -- handle email and SMS notifications
*/
final class SafePalNotifications
{
	protected $mailer;
	protected $messager;
	protected $caseNumber;
	protected $env;

	function __construct($caseNumber){
		$this->mailer = new \sendGrid(getenv('SENDGRID_KEY'));
		$this->messager = new AfricasTalkingGateway(getenv('AIT_USERNAME'), getenv('AIT_KEY'));
		$this->caseNumber = $caseNumber;
		$this->env = getenv('APP_ENV');
	}
	//send email notification
	public function sendEmailNotification($emails){

		if ($this->env == 'dev') {
			$emails = explode(",", getenv('DEV_EMAILS'));
		}

		$emailSent = false;

		//from
		$from = new \SendGrid\Email("SafePal Team", getenv('SFP_EMAIL'));

		//subject
		$subject = getenv('NOTIFICATION_SUBJECT')." ".$this->caseNumber;

		//content
		//$content = new SendGrid\Content("text/html", "".getenv('NOTIFICATION_MESSAGE')." <b>".$this->caseNumber."</b><br/>".getenv('NOTIFICATION_LOGIN_MESSAGE')." <a href='".getenv('DASHBOARD_LINK')."/reports/".$this->caseNumber."' 'target='_blank'> here to got to the dashboard </a>"."".getenv('SIGN_OUT_MESSAGE')."");
		$content = new sendGrid\Content("text/html", "".getenv('NOTIFICATION_MESSAGE')." <b>".$this->caseNumber."</b><br/>".getenv('NOTIFICATION_LOGIN_MESSAGE')." <a href='".getenv('DASHBOARD_LINK')."' 'target='_blank'> here to got to the dashboard </a>"."".getenv('SIGN_OUT_MESSAGE')."");

		//add recipients
		for ($i=0; $i < sizeof($emails); $i++) {
			$to = new sendGrid\Email(null, $emails[$i]);
			$mail = new sendGrid\Mail($from, $subject, $to, $content);
			$emailSent = $this->mailer->client->mail()->send()->post($mail);
		}

		return $emailSent;
	}

	//send smsNotification
	public function sendSMSNotification($recipients){

		if ($this->env == 'dev') {
			$recipients = explode(", ", getenv('DEV_NUMBERS'));
		}
		
		$message = "".getenv('NOTIFICATION_MESSAGE')." ".$this->caseNumber.". Log into the SafePal dashboard to view it";
		$failedRecipients = array();
		$passedRecipients = array();

		try {

			//$results = $this->messager->sendMessage($recipients, $message);

			$results = array();
			foreach ($recipients as $key => $contact) {
				$results = $this->messager->sendMessage($contact, $message);
			}

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
