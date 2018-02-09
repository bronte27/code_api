<?php

namespace notes;
use app\ErrorHandler;
use app\DatabaseHandler;

class Notes
{
  // Add Note 
  public static function AddNote($user_name, $note_text, $note_type, $id, $entity_type='GALLERY')
  {

  		
  	$sql = 'INSERT INTO note (note_entity_id, note_entity_type, note_type, note_date, user_name, note_text) '
  			.'VALUES (:id, :entity_type, :note_type, :note_date, :user_name, :note_text)';

    // Build the parameters array
    $params = array (':id'=>$id,
    				 ':entity_type'=>$entity_type, 
    				 ':note_type'=>$note_type, 
    				 ':note_date'=>date('Y-m-d H:i:s'),
    				 ':user_name'=>$user_name,
    				 ':note_text'=>$note_text);

    // Execute the query
    DatabaseHandler::Execute($sql, $params);
    return 'Note Added';
  }
  
  public static function UpdateNote($note_id, $note_text){
  	$sql = 'UPDATE note 
  			SET note_text = :note_text
  			WHERE note_id = :note_id';
  	
  	$params = array(':note_text'=>$note_text, ':note_id'=>$note_id);
  	
  	DatabaseHandler::Execute($sql, $params);
    return 'Note Updated';
  	
  }
  
  // Get Notes 
  public static function GetNotes($entity_id, $enity_type='GALLERY')
  {
	$sql = 'SELECT * 
		   FROM note
		   WHERE note_entity_id = :id
		   AND note_entity_type = :type
		   ORDER BY note_date DESC';

    // Build the parameters array
    $params = array (':id'=>$entity_id, ':type'=>$enity_type);

    // Execute the query
    
    return DatabaseHandler::GetAll($sql, $params);
  	
  }
  
  
}