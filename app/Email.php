<?php

Namespace app;

use app\EmailHandler;
use app\Helper;
use notes\Notes;

class Email
{
	public $mDataSet;
	public $mDataSetCount;
	public $mCounter;
	public $mEmailTemplate;
	public $mTemplateSubject;
	public $mTemplateBody;
	public $mDataDefined = false;
	public $mError = false;
	public $mMessage;
	public $mSubject;
	public $mAddNote=true;
	public $mNoteText;
	public $mUserName = 'SYSTEM';
	public $mEmailAddress;
	public $mEmailTemplateName;
	public $mTemplateRunFunction;
	public $mRecipientName;
	public $mAttachmentFilename;
	public $mSql;
  
	public function __construct($emailTemplate, $criteria, $where='')
	{
		Helper::SystemParamLoader('Email');
		$mEmailTemplate = EmailHandler::GetEmailTemplate($emailTemplate);
		$this->mTemplateSubject = stripslashes($mEmailTemplate['email_template_subject']);
    	$this->mTemplateBody = stripslashes($mEmailTemplate['email_template_body']);
    	$this->mEmailTemplateName = $mEmailTemplate['email_template_name'];
    	$this->mNoteText = '"'.$this->mEmailTemplateName.'" email sent';
    	$this->mTemplateRunFunction = $mEmailTemplate['email_template_run_function'];
    	$this->mAttachmentFilename = $mEmailTemplate['email_template_attachment_filename'];
    	if (isset($_SESSION['user_logged']['user_description']))
    		$this->mUserName = $_SESSION['user_logged']['user_description'];
    	else 
    		$this->mUserName = 'System';
		
		if (is_numeric($criteria)) {
			if ($where=='order_id')
				$where = ' AND orders.order_id = '.$criteria;
			else
				$where .= ' AND gallery.gallery_id = '.$criteria;
		}
		else 	
			foreach ($criteria as $key => $value)
			{
				if (!empty($value))
				{
					$pos = strpos($key,'_'); 
					if (is_numeric($value))
						$where .= ' AND '.$key.' = '.$value;
					else {
						if (strtolower($value)<>'all')
							$where .= ' AND '.$key." = '{$value}'";
					}
						
				} 
			}
		
		$sql = stripslashes($mEmailTemplate['email_template_query']).$where;
		
		$this->mDataSet = DatabaseHandler::GetAll($sql);
		$this->mDataSetCount = count($this->mDataSet,0);
		if ($this->mDataSetCount==0){
			$this->mError="No email to send";
			trigger_error("No email to send/n".'SQL: '.$sql,E_USER_NOTICE);
		}
		$this->mSql = $sql;
	}
	
	public function init()
	{
		EmailHandler::InitMailer($this->mAttachmentFilename);
		self::PopulateEmail();
	}
	
	private function PopulateEmail()
	{
		if (is_null($this->mCounter))
			$this->mCounter = 0;
		$findValues = array();
		$replaceValues = array();
		foreach ($this->mDataSet[$this->mCounter] as $key => $value){
			array_push($findValues, "%{$key}%");
			if (substr($key, -4)=="link") $value = '<a href="http://'.$value.'">'.$value.'</a>';
			array_push($replaceValues, "{$value}");
		}
		//var_dump($findValues,$replaceValues);

		$this->mMessage = str_replace($findValues, $replaceValues, $this->mTemplateBody);
		$this->mSubject = str_replace($findValues, $replaceValues, $this->mTemplateSubject);
		$this->mEmailAddress = $this->mDataSet[$this->mCounter]['email_address'];
		if (isset($this->mDataSet[$this->mCounter]['gallery_contact_name']))
			$this->mRecipientName = $this->mDataSet[$this->mCounter]['gallery_contact_name'];
		if ($this->mTemplateRunFunction)
			self::RunFunction();
	}
	
	private function RunFunction(){

		$pStart = strpos($this->mMessage,'^^');
		if ($pStart!=false) {
			//require_once BUSINESS_DIR.'helpers.php';
			$pEnd = strpos($this->mMessage,'^^',$pStart+2);
			$tokenStr = substr($this->mMessage, $pStart, $pEnd-$pStart+2);
			$funcStr =  substr($tokenStr, 2, $pEnd-$pStart-2);
			eval('$replace = '.$funcStr.';');
			$this->mMessage = str_replace($tokenStr, $replace, $this->mMessage);
			//$this->mMessage .=$tokenStr.' - '.$funcStr;
		}

	}
	
	public function SendNext()
	{

		if (!$this->mCounter)
			self::PopulateEmail();
		if (!self::SendEmail())
				return false;
		if ($this->mAddNote)
			Notes::AddNote($this->mUserName, $this->mNoteText.' ('.$this->mEmailAddress.')', 'Email Notification', $this->mDataSet[$this->mCounter]['gallery_id']);
		$this->mCounter++;
		if ($this->mCounter < $this->mDataSetCount) 
			self::PopulateEmail();

		
		return true;
	}
	
	public function SendAll()
	{
		if (is_null($this->mCounter))
			self::PopulateEmail();
		for ($i = $this->mCounter; $i < $this->mDataSetCount; $i++) 
		{
			if (!self::SendEmail())
				return false;
			if ($this->mAddNote)
				Notes::AddNote($this->mUserName, $this->mNoteText.' ('.$this->mEmailAddress.')', 'Email Notification', $this->mDataSet[$this->mCounter]['gallery_id']);
			$this->mCounter++;
			if ($this->mCounter < $this->mDataSetCount) 
				self::PopulateEmail();
		}
		return true;
	}
	
	
	public function SendEmail()	{
		$result = EmailHandler::SendEmail($this->mEmailAddress, $this->mRecipientName, null, null, $this->mMessage, $this->mSubject);
		
		if (!$result)
			$this->mError=EmailHandler::$mErrorMessage; 
		return $result;
	}
/*		
		$mime_boundary = "--PHP-alt-".md5(date('r', time()));
		$headers = self::BuildEmailHeader($this->mSenderEmailAddress, $this->mSenderEmailDisplayName, $mime_boundary, $this->mBccSelf);
		// Include the class definition file. 

		require_once(SITE_ROOT . '/libs/HTML2text/class.html2text.inc'); 
		
		$message_text = $this->mMessage;
		
		$this->mMessage = stripslashes($this->mMessage);
		// Instantiate a new instance of the class. Passing the string 
		// variable automatically loads the HTML for you. 
		$h2t = new html2text($this->mMessage); 
		
		// Simply call the get_text() method for the class to convert 
		// the HTML to the plain text. Store it into the variable. 
		$text = $h2t->get_text(); 
		
		$this->mMessage = self::BuildEmailBody($this->mMessage, $text, $mime_boundary);
		mail($this->mEmailAddress, stripslashes($this->mSubject), $this->mMessage, $headers);
		//echo "{$this->mEmailAddress} <br/> {$headers} <br/> {$this->mSubject} <br/> {$this->mMessage}<br/>"; 
		if ($this->mCopySelf==true)
		{
			$headers = self::BuildEmailHeader($this->mEmailAddress, $this->mRecipientName, $mime_boundary);
			mail($this->mSenderEmailAddress, stripslashes($this->mSubject), $this->mMessage, $headers);
			//echo "{$this->mEmailAddress} <br/> {$headers} <br/> {$this->mSubject} <br/> {$this->mMessage}<br/>"; 
		}
		
	
	}
	
	private function BuildEmailHeader($senderEmailAddress, $senderEmailDisplayName, $mime_boundary, $copySelf=false)
	{
		
		
		$headers  = '';
		if (empty($senderEmailDisplayName))
			$headers .= "From: {$senderEmailAddress}". "\r\n";
		else {
			$headers .= 'From: ';
			$emailList = explode(',', $senderEmailAddress);
			for ($i=0;$i<count($emailList);$i++) {
				$coma = ($i>0)?', ':'';
				$headers .= $coma."{$senderEmailDisplayName} <$emailList[$i]>";
			}
			$headers .= "\r\n";
		}
		
	    if ($copySelf =='bcc')
			$headers .= "Bcc: {$senderEmailAddress}" . "\r\n";
		$headers .= "MIME-Version: 1.0\r\n";
		$headers .= "Content-Type: multipart/alternative; boundary=\"$mime_boundary\"\r\n";
		
		return $headers;
	}
	
	private function BuildEmailBody($htmlVersion, $textVersion, $mime_boundary)
	{
		$message = "--$mime_boundary\r\n";
		$message .= "Content-Type: text/plain; charset=UTF-8\n";
		$message .= "Content-Transfer-Encoding: 8bit\n\n";
		
		$message .= $textVersion;
				
		$message .= "\r\n--$mime_boundary\r\n";
		$message .= "Content-Type: text/html; charset=UTF-8\n";
		$message .= "Content-Transfer-Encoding: 8bit\n\n";
		
		$message .= "<html>\r\n";
		$message .= "<body>\r\n";
		$message .= $htmlVersion;
		$message .= "</body>\r\n";
		$message .= "</html>\r\n";
		
		$message .= "--$mime_boundary--\r\n";
		
		return $message;
	}
	
*/
		

	
}




?>