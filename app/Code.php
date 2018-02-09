<?php

namespace code;
use app\ErrorHandler;
use app\DatabaseHandler;
use app\Email;
use app\EmailHandler;
use app\Helper;
use event\Event;
use group\Group;
use gallery\Gallery;

class Code {
    private static $data;
    private static $eventCode;

   public static function processRegistration($data, $codeType, $code){
        self::$eventCode = $code;
        

       // if ($data['data']['mailingList'])


       if ($codeType=='child_reg') {

        $regIdList = self::addRegistration($data['data']);

        
        for ($i=0;$i<count($regIdList);$i++){
            $email = new Email(REGISTRATION_EMAIL_TEMPLATE,array('reg.reg_id'=>$regIdList[$i]));
            $email->init();
            $email->mAddNote = false;
            $email->SendAll();
            unset($email);
        }

        return Helper::GetSystemParamEntity("1.1.1.1");
         
       }
       if ($codeType=='contact_reg'){
         $galleryId = self::updateContact($data['data'], $code);
         
         if ($galleryId) {
            $email = new Email(EmailHandler::GetEventEmailTemplate($galleryId),$galleryId);
            $email->init();
            $email->SendAll();
            unset($email);

            return Helper::GetSystemParamEntity("1.1.2.1");
         }
         return "An unexpected error occured with your registration";
       }

       throw new \Exception("Invalid CodeType");
   }

   private static function addRegistration($data){
    
    
    $eventData = Event::getEventByCode(self::$eventCode);
    
    $sql = 'INSERT INTO registration (event_id, event_name, reg_contact_name, reg_phone_number, reg_email, reg_child_first_name, 
                        reg_child_surname, group_id, reg_group_name, reg_day_mon, reg_day_tue, reg_day_wed, reg_day_thu, reg_day_fri,	
                        reg_special_friends, reg_siblings, reg_info, reg_sibling_list) 
                values (:event_id, :event_name, :contact_name, :phone_number, :email, :child_first_name, :child_surname, :group_id, 
                        :group_name, :day_mon, :day_tue, :day_wed, :day_thu, :day_fri, :special_friends, :siblings, :info, :sibling_list)';
    
    $siblingList='';
    $numChildren = $data['contact']['numChildren'];
    if ($numChildren>1)
        for ($i=0;$i<$numChildren;$i++)
            $siblingList .= $data['children'][$i]['firstName'].' '.$data['children'][$i]['surname'].'; ';
        
  $data['contact']['phoneNumber'] = str_replace(" ","",$data['contact']['phoneNumber']);
  $data['contact']['phoneNumber'] = substr($data['contact']['phoneNumber'],0,4).' '.substr($data['contact']['phoneNumber'],4,3).' '.substr($data['contact']['phoneNumber'],7,3);    
   
    for ($i=0;$i<$numChildren;$i++){

        $sqlGroup = 'SELECT group_name FROM groups WHERE group_id = :group_id';
        $param = array('group_id' => $data['children'][$i]['group']);
        $groupName = DatabaseHandler::GetOne($sqlGroup,$param);
        
        $params = array(':event_id'=>$eventData['event_id'], 
                        ':event_name'=>$eventData['event_name'], 
                        ':contact_name'=>$data['contact']['contactName'], 
                        ':phone_number'=>$data['contact']['phoneNumber'], 
                        ':email'=>strtolower($data['contact']['emails']['email']), 
                        ':child_first_name'=>$data['children'][$i]['firstName'], 
                        ':child_surname'=>$data['children'][$i]['surname'], 
                        ':group_id'=>$data['children'][$i]['group'], 
                        ':group_name'=>$groupName, 
                        ':day_mon'=>($data['children'][$i]['days']['mon'])?'X':'', 
                        ':day_tue'=>($data['children'][$i]['days']['tue'])?'X':'', 
                        ':day_wed'=>($data['children'][$i]['days']['wed'])?'X':'', 
                        ':day_thu'=>($data['children'][$i]['days']['thu'])?'X':'', 
                        ':day_fri'=>($data['children'][$i]['days']['fri'])?'X':'', 
                        ':special_friends'=>'', //$data['children'][$i]['Special'], 
                        ':siblings'=>($data['children'][$i]['siblings'])?'X':'', 
                        ':info'=>(empty($data['children'][$i]['info']))?'':$data['children'][$i]['info'],
                        ':sibling_list'=>str_replace($data['children'][$i]['firstName'].' '.$data['children'][$i]['surname'].'; ', '', $siblingList));
            
        DatabaseHandler::Execute($sql,$params);

        $regIdList[$i] = DatabaseHandler::GetOne('SELECT MAX(reg_id) "reg_id" FROM registration WHERE event_id = :event_id AND reg_child_first_name = :child_first_name',
                                                 array(':event_id'=>$eventData['event_id'],':child_first_name'=>$data['children'][$i]['firstName']));
  }
  if ($data['mailingList'])
    Helper::AddToEmailListByRegistration($regIdList[0]);
  return $regIdList;
}

public static function updateContact($data, $code){
    
      // Build the SQL query
  $sql = 'UPDATE gallery 
          SET gallery_contact_name=:contact_name, gallery_contact_number=:contact_number, gallery_contact_email=:email 
          WHERE gallery_code = :gallery_code';

  $params = array (
    ':contact_name'=>$data['contact']['contactName'], 
    ':contact_number'=>$data['contact']['phoneNumber'], 
    ':email'=>strtolower($data['contact']['emails']['email']), 
    ':gallery_code'=>$code
    );
  
  // Execute the query and return the results
  DatabaseHandler::Execute($sql, $params);	
  
    if ($data['mailingList'])
        Helper::AddToEmailListByGallery($code);
    
    $result = Gallery::GetGalleryIdByCode($code);
  
    return $result;
  }

  
}