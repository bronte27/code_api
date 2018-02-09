<?php

namespace gallery;
use app\ErrorHandler;
use app\DatabaseHandler;

class Gallery {

    public static function GetGalleryIdByCode($galleryCode) {
        $sql = 'SELECT gallery_id FROM gallery WHERE gallery_code = :galleryCode';
        $param = array(':galleryCode' => $galleryCode);
        return DatabaseHandler::GetOne($sql,$param);
    }
}
