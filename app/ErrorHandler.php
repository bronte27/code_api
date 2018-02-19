<?php

namespace app;
use Slim\Middleware\TokenAuthentication;

class ErrorHandler {
    
    static function generalErrorHandler($e) {
        echo $e->getMessage();
    }

    static function setErrorHandler ($app) {
        $c = $app->getContainer();
        $c['errorHandler'] = function ($c) {
            return function ($request, $response, $exception) use ($c) {
                list($class) = sscanf( get_class($exception), 'Firebase\JWT\%s');
                if (empty($class)){
                    $class = get_class($exception);
                }

                switch ($class) {
                    case 'UnexpectedValueException':
                        return self::errorResponseJWT($c,401, "An unexpected error has occured");
                        break;
                    case 'DomainException':
                    case 'SignatureInvalidException':
                        $status = 400; $errorMessage['msg'] = 'Provided JWT was invalid because the signature verification failed';
                        break;
                    case 'BeforeValidException':
                        $status = 401; $errorMessage['msg'] = "Provided JWT is trying to be used before it's eligible";
                        break;
                    case 'BeforeValidException':
                        $status = 401; $errorMessage['msg'] = "Provided JWT is trying to be used before it's been created";
                        break;
                    case 'ExpiredException':
                        $status = 401; $errorMessage['msg'] = "Provided JWT has since expired";
                        break;
                    case 'PDOException':
                        $status = 500; $errorMessage['msg'] = "Unexpected error has occured: ".$class;
                        break;
                    default:
                        $status = 500; $errorMessage['msg'] = "Unexpected error has occured: ".$class;
                }

                if (VERBOSE_ERRORS == 'true') 
                    $errorMessage['verbose_msg'] = $exception->__toString();
                return $c['response']->withStatus($status)
                                     ->withHeader('Content-Type', 'application/json')
                                     ->withJson(['error'=> $errorMessage]);
            };
        };
        $c['notAllowedHandler'] = function ($c) {
            return function ($request, $response, $methods) use ($c) {
                return $c['response']
                    ->withStatus(405)
                    ->withHeader('Allow', implode(', ', $methods))
                    ->withHeader('Content-Type', 'application/json')
//                    ->withJson(['error'=> ['msg'=>'Invalid request method']]);
                    ->withJson(['error'=> ['msg'=>'Invalid request method ('.$request->getUri()->getPath().')'], 'verbose_msg'=>debug_backtrace()]);
            };
        };
    }

    static function errorResponseJWT($c, $status, $msg){
        return $c['response']->withStatus($status)
                             ->withHeader('Content-Type', 'application/json')
                             ->withJson(['error'=> ['msg'=>$msg]]);
    }

    static function setAuthErrorHandler($request, $response, TokenAuthentication $tokenAuth) {
        $responseMessage = $tokenAuth->getResponseMessage();
        switch ($responseMessage){
            case 'Invalid Token':
                $status = 400; $msg = "An unexpected error has occured";
                break;
            case 'Unauthorised':
                $status = 401; $msg = "Not Authorised";
                break;
            case 'Token not found':
                $status = 401; $msg = "Not Authorised";
                if (VERBOSE_ERRORS == 'true') 
                    $errorMessage['verbose_msg'] = $responseMessage;
                break;
            default:
                $status = 500; $msg = "Unexpected error has occured: ".$class;
        }
        $output = [];
        $output['error'] = [
            'msg' => $msg
        ];
        if (VERBOSE_ERRORS == 'true') 
            $output['error']['verbose_msg'] = $responseMessage;
        return $response->withJson($output, $status);
    }
}