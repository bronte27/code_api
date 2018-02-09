<?php

namespace event;
use app\ErrorHandler;
use app\DatabaseHandler;

class Event {

    public static function getEventByCode($eventCode) {
        $sql = 'SELECT event_id, event_name FROM event WHERE event_code = :event_code';
        $param = array(':event_code' => $eventCode);
        return DatabaseHandler::GetRow($sql,$param);
    }

}
