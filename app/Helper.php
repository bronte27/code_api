<?php

namespace app;

use app\DatabaseHandler;

class Helper
{
	public static function SystemParamLoader($paramCategory){
		$sql = 'SELECT system_param_name, system_param_value 
				FROM system_param sp, param_category pc 
				WHERE sp.param_category_id= pc.param_category_id 
				AND param_category_name = :param_category';
		
		// Build the parameters array
	    $params = array (':param_category' => $paramCategory);
	
	    // Execute the query and return the results
	    $result = DatabaseHandler::GetAll($sql, $params);
	    
		for($i=0;$i<count($result);$i++) {
			if (!defined($result[$i]['system_param_name'])){
		    	if (is_numeric($result[$i]['system_param_value']))
		    		define($result[$i]['system_param_name'], (int) $result[$i]['system_param_value']);
		    	elseif (strtoupper($result[$i]['system_param_value'])=='TRUE')
		    		define($result[$i]['system_param_name'], true);
		    	elseif (strtoupper($result[$i]['system_param_value'])=='FALSE')
		    		define($result[$i]['system_param_name'], false);
		    	else
		   			define($result[$i]['system_param_name'], $result[$i]['system_param_value']);
			}
	   	}
	}


	public static function GetSystemParamEntity($node){
		$node = explode('.',$node);
		$params[':l1'] = ($node[0]==0)?null:intval($node[0]);
		$params[':l2'] = ($node[1]==0)?null:intval($node[1]);
		$params[':l3'] = ($node[2]==0)?null:intval($node[2]);
		$params[':l4'] = ($node[3]==0)?null:intval($node[3]);
		$sql = 'SELECT sp_node_type, sp_node_description, sp_entity_content 
				FROM system_param_node spn LEFT JOIN system_param_entity spe ON spn.sp_entity_id = spe.sp_entity_id
				WHERE spn.sp_node_l1 = :l1 AND spn.sp_node_l2 = :l2 AND spn.sp_node_l3 = :l3 AND spn.sp_node_l4 = :l4';
		
		$result = DatabaseHandler::GetRow($sql,$params);

		if ($result['sp_node_type']=='HTML')
			return $result['sp_entity_content'];
	}

	public static function AddToEmailListByGallery($galleryCode){
		$sql = 'DELETE FROM email_list 
				WHERE email_list.email_list_address IN (
					SELECT g.gallery_contact_email 
					FROM gallery g 
					WHERE g.gallery_code = :galleryCode)';
			
		$param = array(':galleryCode'=>$galleryCode);
		
		DatabaseHandler::Execute($sql,$param);
		
		$sql = 'INSERT INTO email_list (customer_id, email_list_name, email_list_address, email_list_date, email_list_active)
				SELECT e.customer_id, g.gallery_contact_name, gallery_contact_email, CURDATE(), 1
				FROM gallery g, event e
				WHERE g.gallery_code = :galleryCode
				  AND g.event_id = e.event_id';
				  
		
		DatabaseHandler::Execute($sql,$param);
			
	}

	public static function AddToEmailListByRegistration($regId){
		$sql = 'DELETE FROM email_list WHERE email_list.email_list_address IN (SELECT r.reg_email FROM registration r WHERE r.reg_id = :regId)';
			
		$param = array(':regId'=>$regId);
		
		DatabaseHandler::Execute($sql,$param);
		
		$sql = 'INSERT INTO email_list (customer_id, email_list_name, email_list_address, email_list_date, email_list_active)
				SELECT e.customer_id,  r.reg_contact_name, r.reg_email, CURDATE(), 1
				FROM registration r, event e
				WHERE r.event_id = e.event_id
				  AND r.reg_id = :regId';
		
		DatabaseHandler::Execute($sql,$param);
			
	}








  public static function Print_R_HTML($var){
  	return str_replace(array("\r\n", "\n", "\r", " "), array('<br/>','<br/>','<br/>','.'), print_r($var,true));
  }
	
  public static function GetEventYearsList(){
  	// build list of years from first year to current
  	for ($i=date('Y')-FIRST_EVENT_YEAR;$i>=0;$i--)
  		$list[date('Y')-(FIRST_EVENT_YEAR+$i)]=$i+FIRST_EVENT_YEAR;
  		
    return $list;
  }
	
	public static function BuildOrderHTML($order_id){
		require_once BUSINESS_DIR.'order.php';
		return Order::BuildOrderHTML($order_id);
	}

	public static function SavedValue($name, $default){
		if (isset($_GET[$name]))
	  		$return = $_GET[$name];
	    elseif (isset($_SESSION[$name]))
	    	$return = $_SESSION[$name];
	    else
	  		$return = $default;
	  	$_SESSION[$name] = $return;
	  	
	  	return $return;
	}
	
	public static function BuildBreadCrumb($list){
		$breadCrumb = '';
		foreach ($list as $key => $value){
			if (!empty($breadCrumb))
				$breadCrumb .= ' > ';
			$breadCrumb .= '<a href="'.$value.'">'.$key.'</a>'; 
		}
		return $breadCrumb;
	}
	
	public static function Criteria($criteria)
	{
		switch ($criteria) {
	    		case 'all':
	    			return '';
	    		case 'not_viewed':
	    			return ' AND gallery_viewed = 0';
	    			break;
	    		case 'viewed':
	    			return ' AND gallery_viewed = 1';
	    			break;
	    		case 'ordered':
	    			return ' AND gallery.gallery_id IN (SELECT gallery_id
								 FROM orders o1
								 WHERE o1.gallery_id = gallery.gallery_id
				  				   AND o1.order_status_id <> 4
				  				   AND o1.order_type <> 1)';
	    			break;
	    		case 'not_ordered':
	    			return ' AND gallery.gallery_id NOT IN (SELECT gallery_id
								 FROM orders o1
								 WHERE o1.gallery_id = gallery.gallery_id
				  				   AND o1.order_status_id <> 4
				  				   AND o1.order_type <> 1)';
	    			break;
	    		case 'ordered_not_paid':
	    			return ' AND gallery_ordered != 0 AND gallery.gallery_id in (SELECT o1.gallery_id
                           FROM orders o1
                           WHERE gallery.gallery_id = o1.gallery_id 
                           AND order_paid = 0
                           AND order_amount > order_payments
		  				   AND o1.order_status_id < 4
		  				   AND o1.order_type <> 1)';

	    			break;
	    		case 'ordered_paid':
	    			return  ' AND gallery_ordered != 0 AND gallery.gallery_id in (SELECT o1.gallery_id
                           FROM orders o1
                           WHERE gallery.gallery_id = o1.gallery_id
				  		   AND o1.order_type <> 1 
                           AND (order_paid = 1
                                OR order_amount <= order_payments))';
	    			break;
	    		case 'task':
	    			return ' AND task_count > 0';
	    		case 'new_orders':
	    			return ' AND new_order_count > 0';
	    	}
	}   	
	

	
	public static function FindTags($string, $open, $close=null){
		$finish = false;
		$pos = 0;
		$results = array();
		while (!$finish) {  // loop through string
			$start = strpos($string, $open, $pos);
			if ($start===false)
				$finish=true;
			else { // found tag start
				$pos=$start+1;
				$end = strpos($string, $close, $pos);
				if ($end===false)
					$finish=true;
				elseif ($start+1==$end){
					$pos=$end+1;
				}
				else {  // found tag end
					$pos=$end+1;
					array_push($results, substr($string, $start, $end-$start+1));
				}
			} 
				
		}
		return $results;
	}
	
	public static function EchoSqlDetails($sql, $params){
		$paramStr ='';
	
	foreach ($params as $key => $value) {
	  	$paramStr .= $key.': '.$value.'; ';
	}
    echo '<BR>SQL: '.$sql.'<BR>SQL: '.$paramStr;
	}
	
	public static function GetMessageText($message_code, $group_id=null, $event_id=null, $customer_id=null, $customer_type=null){
		

		$sql = 'SELECT message_text 
				FROM message_text
			    WHERE message_code = :message_code';
		$param[':message_code']=$message_code;
				
		if (is_null($group_id))
			$sql .= ' AND group_id IS NULL';
		else {
			$sql .= ' AND (group_id IS NULL OR group_id=:group_id)';
			$param[':group_id']=$group_id;
		}
		
		if (is_null($event_id))
			$sql .= ' AND event_id IS NULL';
		else {
			$sql .= ' AND (event_id IS NULL OR event_id=:event_id)';
			$param[':event_id']=$event_id;
		}
		
		if (is_null($customer_id))
			$sql .= ' AND customer_id IS NULL';
		else {
			$sql .= ' AND (customer_id IS NULL OR customer_id=:customer_id)';
			$param[':customer_id']=$customer_id;
		}
		
		if (is_null($customer_type))
			$sql .= ' AND customer_type IS NULL';
		else {
			$sql .= ' AND (customer_type IS NULL OR customer_type=:customer_type)';
			$param[':customer_type']=$customer_type;
		}
		$sql .= ' ORDER BY group_id desc, event_id desc, customer_id desc, customer_type desc LIMIT 1';
		
		
		$result = DatabaseHandler::GetOne($sql, $param);
		
		return $result;
	}
	
	public static function FindDayDate($offset, $startDate=null){
		if (is_null($startDate))
			$date = new DateTime();
		else
			$date = new DateTime($startDate);
	    $day = $date->format('w');
	    if ($day>$offset){
	    	$sub = $day-$offset;
	    	$date->sub(new DateInterval('P0'.$sub.'D'));
	    }
	  	if ($day<$offset){
	    	$add = $offset-$day;
	    	$date->add(new DateInterval('P0'.$add.'D'));
	    }
	    
		return $date->format('Y-m-d');
	} 	
}
?>