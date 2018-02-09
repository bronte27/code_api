<?php

namespace group;
use app\ErrorHandler;
use app\DatabaseHandler;

class Group {

    public static function getGroupName ($groupId) {
        $sql = 'SELECT group_name FROM groups WHERE group_id = :group_id';
        $param = array('group_id' => $data['children'][$i]['group']);
        return DatabaseHandler::GetOne($sql,$param);
    }
}
