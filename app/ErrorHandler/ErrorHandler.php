<?php

//namespace App;

class ErrorHandler {
    
    static function setErrorHandler ($app) {
        $c = $app->getContainer();
        $c['errorHandler'] = function ($c) {
            return function ($request, $response, $exception) use ($c) {
                list($class) = sscanf( get_class($exception), 'Firebase\JWT\%s');
                $class = get_class($exception);
                switch ($class) {
                    case 'UnexpectedValueException':
                        $status = 401; $msg = 'Provided JWT was invalid';
                        break;
                    case 'SignatureInvalidException':
                        $status = 401; $msg = 'Provided JWT was invalid because the signature verification failed';
                        break;
                    case 'BeforeValidException':
                        $status = 401; $msg = "Provided JWT is trying to be used before it's eligible as defined by 'nbf'";
                        break;
                    case 'BeforeValidException':
                        $status = 401; $msg = "Provided JWT is trying to be used before it's been created as defined by 'iat'";
                        break;
                    case 'ExpiredException':
                        $status = 401; $msg = "Provided JWT has since expired, as defined by the 'exp' claim";
                        break;
                    default:
                        $status = 500; $msg = $class;
                }
        
                return $c['response']->withStatus($status)
                                     ->withHeader('Content-Type', 'application/json')
                                     ->withJson(['error'=> ['msg'=>$msg]]);
            };
        };
    }
}