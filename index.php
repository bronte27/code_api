<?php
require_once 'app/config.php';
require_once 'vendor/autoload.php';
require_once 'app/ErrorHandler.php';


 



use Slim\App;
use app\AuthCode;
use Slim\Middleware\TokenAuthentication;
use code\Code;
use group\Group;
use event\Event;
use app\ErrorHandler;


$config = [
    'settings' => ['displayErrorDetails' => true]
];

$app = new App($config);

ErrorHandler::setErrorHandler($app);

$authenticator = function(&$request, TokenAuthentication $tokenAuth, $response){

    if($request->getMethod() == 'OPTIONS') {  // bypass if CORS validation
        return true;
    }
    else {

        // Find authorization token via header, parameters, cookie or attribute
        $token = $tokenAuth->findToken($request);
        
        // Call authentication logic class
        $auth = new \app\AuthCode();

        //  Check if token is valid; return user data
        $userData = $auth->getUserByToken($token);

        // Store code in request for later use
        $request = $request->withAttribute('code',$userData->code);
        $request = $request->withAttribute('codeType',$userData->codeType);
    }
};

$authError = function($request, $response, TokenAuthentication $tokenAuth) {
    return ErrorHandler::setAuthErrorHandler($request, $response, $tokenAuth);
};



// Add token authentication middleware
$app->add(new TokenAuthentication([
    'path' =>   '/api',
    'passthrough' => '/api/auth',
    'authenticator' => $authenticator,
    'error' => $authError
]));




//Enable CORS
$app->options('/{name:.+}', function($request, $response, $args){
    return $response->withHeader('Access-Control-Allow-Origin', '*')
        ->withHeader('Access-Control-Allow-Headers', 'X-Requested-With, Content-Type, Accept, Origin, Authorization')
        ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
});

// Setup Routes
$app->post('/auth/{type}', function($request, $response, $args){
    switch ($args['type']) {
        case 'code': 
            $auth = new \app\AuthCode();

            $code = (string)$request->getBody();
            // Validate code and get token payload
            $output = $auth->validateCode(json_decode($code,true));
            if (array_key_exists('error',$output)) {
                // invalid token; return error
                $response = $response->withJson(array('error'=>array('message'=>$output['error']['msg'])), $output['error']['status']);
            } else {
                // valid token; return token
                $token = $auth->encodeToken($output);
                $response = $response->withJson($token, 200);
            }
        break;
        //case 'login':
        default:
        if (VERBOSE_ERRORS == 'true') 
            $errorMessage['verbose_msg'] = 'Invalid authorisation type requested';
        $errorMessage['msg'] = "Not Authorised";
        $response = $response->withJson(['error'=> $errorMessage], 401);
    }

    return $response;
});


// Restricted Routes; requires valid token

$app->get('/', function($request, $response){
    $output = [];
    $output['data'] = ['msg'=>'It\'s a restrict area. Token authentication works! '.$request->getAttribute('user')];
    $response = $response->withJson($output, 200);

    return $response;
});

$app->post('/api/registration', function($request, $response){
    $output = [];
    
    //require_once 'code/Code.php';
    $output = Code::processRegistration(json_decode((string)$request->getBody(),true),$request->getAttribute ('codeType'), $request->getAttribute ('code'));
    $response = $response->withJson(array('data'=>array('HTML'=>$output)), 200);

    return $response;
});

$app->run();