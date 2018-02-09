<?php
namespace app;
use \PHPMailer;
use Html2Text\Html2Text;
use app\DatabaseHandler;

class EmailHandler
{
	public static $mCopySelf;
	public static $mBccSelf;
	public static $mSenderEmailDisplayName;
	public static $mSenderEmailAddress;
	public static $mMail;
	public static $mErrorMessage;	
	public static $mReplyTo;
	public static $mOffline;
	public static $mEchoEmail;
	
  public static function GetAttachmentFileList(){
  	$i=0;
  	if ($dh = opendir(EMAIL_ATTACHMENT_PATH)) {
    	while (($file = readdir($dh)) !== false) {
        	if (is_file(EMAIL_ATTACHMENT_PATH.$file)){
            	$fileList[$i]=$file;
            	$i++;
            }
        }
        closedir($dh);
    }
    return $fileList;
  }
	
  public static function InitMailer($attachment)
  {
  	Helper::SystemParamLoader('Email');
  	
  	//require_once(PHPMAILER_DIR.'PHPMailerAutoload.php');
  	
  	//require_once(PHPMAILER_DIR.'class.phpmailer.php');
  	self::$mErrorMessage = null;
  	self::$mCopySelf = COPY_SELF;
	self::$mBccSelf = BCC_SELF;
	self::$mSenderEmailDisplayName = SENDER_DISPLAY_NAME;
	self::$mSenderEmailAddress = SENDER_EMAIL_ADDRESS;
	self::$mReplyTo = SENDER_EMAIL_ADDRESS;
	self::$mOffline = EMAIL_OFFLINE;
	
	//if (self::$mOffline)
	//	self::$mEchoEmail = true;
	// else 
		self::$mEchoEmail = false;
	
	self::$mMail = new PHPMailer;
	
	self::$mMail->IsSMTP();                                     // Set mailer to use SMTP 
	//self::$mMail->SMTPDebug = 2;
	//self::$mMail->Debugoutput = 'echo';
	self::$mMail->Timeout = 60;
	self::$mMail->Host = EMAIL_HOST;  // Specify main and backup server
	//$mail->SMTPAuth = true;                               // Enable SMTP authentication
	self::$mMail->Username = EMAIL_USERNAME;                            // SMTP username
	self::$mMail->Password = EMAIL_PASSWORD;                           // SMTP password
	self::$mMail->SMTPSecure = 'tls';                            // Enable encryption, 'ssl' also accepted
	
	self::$mMail->WordWrap = 50;                                 // Set word wrap to 50 characters
	self::$mMail->IsHTML(true);                                  // Set email format to HTML
	if (!empty($attachment) && file_exists(EMAIL_ATTACHMENT_PATH.$attachment))  	
		self::$mMail->AddAttachment(EMAIL_ATTACHMENT_PATH.$attachment);
  }
	
	
  // Save Email Template
  public static function SaveEmailTemplate($emailTemplateId, $emailTemplateSubject,$emailTemplateBody,$emailTemplateQuery,$emailTemplateRunFunction,$filename)
  {
    // Build the SQL query
    $sql = 'UPDATE email_template
    		SET email_template_subject = :subject,
    			email_template_body = :body,
    			email_template_query = :query,
    			email_template_run_function = :run_function,
    			email_template_attachment_filename = :filename
    		WHERE email_template_id = :id';

    // Build the parameters array
    $params = array (':subject' => $emailTemplateSubject,
                     ':body' => $emailTemplateBody,
    				 ':id' => $emailTemplateId,
    				 ':query' => $emailTemplateQuery,
    				 ':run_function'=>$emailTemplateRunFunction,
    				 ':filename'=>$filename);

    // Execute the query
    DatabaseHandler::Execute($sql, $params);
    return 'Email Template Updated';
  }

  //Gets the list of available email templates 
  public static function GetEmailTemplateList($type=null){
  
  	if (is_null($type)){
  		$sql = 'SELECT email_template_id, email_template_name FROM email_template';
  		return DatabaseHandler::GetAll($sql);
  	}
  	else {
    	$sql = 'SELECT email_template_id, email_template_name FROM email_template WHERE email_template_type = :type';
    	
	    return DatabaseHandler::GetAll($sql,array(':type'=>$type));
	 }
	 //echo $sql;
  }
  
	
	public static function GetEmailDetails($galleryId, $sql)
  {
    
    // Build the parameters array
    $params = array (':galleryId' => $galleryId);

    // Execute the query and return the results
    return DatabaseHandler::GetRow($sql, $params);
  }
  
	public static function GetEmailTemplateName($emailTemplate)
  {
    
  	if (is_numeric($emailTemplate))
  		$where = 'email_template_id = :emailTemplate';
  	else 
  		$where = 'email_template_name = :emailTemplate';
  	
  	// Build the SQL query
    $sql = 'SELECT email_template_name
    		FROM email_template
    		WHERE '.$where;

    // Build the parameters array
    $params = array (':emailTemplate' => $emailTemplate);

    // Execute the query and return the results
    return DatabaseHandler::GetOne($sql, $params);
  }
	
	public static function GetEmailTemplate($emailTemplate)
  {
    
  	if (is_numeric($emailTemplate))
  		$where = 'email_template_id = :emailTemplate';
  	else 
  		$where = 'email_template_name = :emailTemplate';
  	
  	// Build the SQL query
    $sql = 'SELECT email_template_name, email_template_id, email_template_subject, email_template_body, email_template_query, email_template_run_function, email_template_attachment_filename
    		FROM email_template
    		WHERE '.$where;

    // Build the parameters array
    $params = array (':emailTemplate' => $emailTemplate);

    // Execute the query and return the results
    return DatabaseHandler::GetRow($sql, $params);
  }
  
  public static function GetEventEmailTemplate($galleryId)
  {
  	// Build the SQL query
    $sql = 'SELECT event_intro_email_template_id
    		FROM gallery g, event e
    		WHERE g.event_id = e.event_id
    		AND g.gallery_id = :galleryId';

    // Build the parameters array
    $params = array (':galleryId' => $galleryId);

    // Execute the query and return the results
    return DatabaseHandler::GetOne($sql, $params);
  }
  
  public static function GetInvoiceEmailTemplate($galleryId)
  {
  	// Build the SQL query
    $sql = 'SELECT event_invoice_email_template_id
    		FROM gallery g, event e
    		WHERE g.event_id = e.event_id
    		AND g.gallery_id = :galleryId';

    // Build the parameters array
    $params = array (':galleryId' => $galleryId);

    // Execute the query and return the results
    return DatabaseHandler::GetOne($sql, $params);
  }
  
	public static function SendEmail($emailAddress, $recipientName, $replyToAddress, $replyToName, $message, $subject, $fileAttachment=null, $copySelf=false, $bccSelf=false)
	{
		if (self::$mBccSelf)
			self::$mMail->AddBCC($this->mSenderEmailAddress);
		
		
		
		//echo $emailAddress.' '.$recipientName."<br>\r\n".$senderEmailAddress.' '.$senderEmailDisplayName."<br>\r\n".$subject."<br>\r\n".$message."<br>\r\n".$copySelf.' '.$bccSelf;
		
		// Include the class definition file. 
		
		
		$message = stripslashes($message);
		// Instantiate a new instance of the class. Passing the string 
		// variable automatically loads the HTML for you. 
		$h2t = new html2text($message); 
		
		// Simply call the get_text() method for the class to convert 
		// the HTML to the plain text. Store it into the variable. 
		$text = $h2t->get_text(); 
		
		if (self::$mMail->SMTPDebug == TRUE)  echo date("Y-m-d H:i:s").' Start Debug\r\n<br\>';
		if (!self::Mail($emailAddress, $recipientName, $replyToAddress, $replyToName, $message, $text, stripslashes($subject)))
			return false;	
		if (self::$mCopySelf==true)
		{
			
			
			if (!self::Mail(self::$mSenderEmailAddress, self::$mSenderEmailDisplayName, $emailAddress, $recipientName, $message, $text, stripslashes($subject)))
				return false;
		}
		return true;
	
	}
	
	private static function Mail ($toEmailAddress, $toName, $replyToAddress, $replyToName, $messageHTML, $messageText, $subject){
		self::$mMail->ClearAllRecipients();
		self::$mMail->ClearReplyTos();	
		if (is_null($replyToAddress))
			$replyToAddress = self::$mSenderEmailAddress;
		if (is_null($replyToName)) {
			$replyToName = self::$mSenderEmailDisplayName;
			$fromName = $replyToName .' (system)';
		}
		else 
			$fromName = $replyToName;
			
		
		self::$mMail->From = self::$mSenderEmailAddress;
		self::$mMail->FromName = $fromName;
		self::$mMail->AddAddress($toEmailAddress, $toName);  // Add a recipient
		self::$mMail->AddReplyTo($replyToAddress, $replyToName);
		
		self::$mMail->Subject = $subject;
		self::$mMail->Body    = $messageHTML;
		self::$mMail->AltBody = $messageText;
		
		if (!self::$mOffline) {
			//restore_error_handler();	
			if(!self::$mMail->Send()) {
			   self::$mErrorMessage =  'Mailer Error: ' . self::$mMail->ErrorInfo;
			  // ErrorHandler::SetHandler();
			   trigger_error(self::$mErrorMessage, E_USER_NOTICE);
			   return false;
			}	
			ErrorHandler::SetHandler();
		}
		elseif (self::$mEchoEmail) 
			echo "{$toEmailAddress} {$toName} <br/> {$subject} <br/> {$messageHTML}<br/>";		
			
		self::$mMail->ClearAllRecipients();
		self::$mMail->ClearReplyTos();
	
		return true;
	}
	
	
	
}