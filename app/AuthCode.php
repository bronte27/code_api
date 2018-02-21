<?php

namespace app;
use Firebase\JWT\JWT;

class AuthCode
{

    
    public $expireAfterDuration = 1800;
    public $notBeforeOffset = 0;

 
    /**
     * It's only a validation example!
     * You should search user (on your database) by authorization token
     */
    public function getUserByToken($jwt)
    {
        //list($jwt) = sscanf( $token, 'Bearer %s');

        if ($jwt<>'undefined' && $jwt<>'null') {
            try {

                /*
                    * decode the jwt using the key from config
                    */
                $secretKey = base64_decode(JWTKEY);
                
                $token = JWT::decode($jwt, $secretKey, [JWTALGORITHM]);

                $user = $token->data;


            } catch (Exception $e) {
                /*
                    * the token was not able to be decoded.
                    * this is likely because the signature was not able to be verified (tampered token)
                    */
                    throw new UnauthorizedException('Invalid Token');
            }
        } else {
            /*
                * No token was able to be extracted from the authorization header
                */
                throw new UnauthorizedException('Unauthorised');
        }
        
        return $user;
   
    }

    public function encodeToken($payloadData){
        $tokenId    = base64_encode(bin2hex(openssl_random_pseudo_bytes(32)));
        $issuedAt   = time();
        $notBefore  = $issuedAt + $this->notBeforeOffset;  //Adding 10 seconds
        $expire     = $notBefore + $this->expireAfterDuration; // Adding 30 minutes
        $serverName = 'localhost';
        
        /*
         * Create the token as an array
         */
        $data = [
            'iat'  => $issuedAt,         // Issued at: time when the token was generated
            'jti'  => $tokenId,          // Json Token Id: an unique identifier for the token
            'iss'  => $serverName,       // Issuer
            'nbf'  => $notBefore,        // Not before
            'exp'  => $expire,           // Expire
            'data' => $payloadData       // Data related to the signer user
        ];
        
       
        /*
         * Extract the key, which is coming from the config file. 
         * 
         * Best suggestion is the key to be a binary string and 
         * store it in encoded in a config file. 
         *
         * Can be generated with base64_encode(openssl_random_pseudo_bytes(64));
         *
         * keep it secure! You'll need the exact key to verify the 
         * token later.
         */
        $secretKey = base64_decode(JWTKEY);
        

        
        /*
         * Encode the array to a JWT string.
         * Second parameter is the key to encode the token.
         * 
         * The output string can be validated at http://jwt.io/
         */
        $jwt = JWT::encode(
            $data,      //Data to be encoded in the JWT
            $secretKey, // The signing key
            JWTALGORITHM  // Algorithm used to sign the token, see https://tools.ietf.org/html/draft-ietf-jose-json-web-algorithms-40#section-3
            );
            
        return ['jwt' => $jwt];
    }


    public function validateCode($data) {
        $galleryURL = '';
        $groups = [];
        $friends = false;
        $siblings = false;



        if (array_key_exists('code',$data)) {
            $code = strtolower($data['code']);
        //if (array_key_exists('password',$data)) {
        //    $code = $data['password'];
            $sql = 'SELECT event_code, event_status_id, event_active, e.customer_id, c.customer_siblings as siblings, c.customer_friends as friends FROM event e, customer c WHERE  e.customer_id = c.customer_id AND event_code = :code';
            $params = array(':code'=>$code);
            $rs_code = DatabaseHandler::GetRow($sql, $params);

            if (!empty($rs_code)) {
                $codeType = "child_reg";

                $sql = 'SELECT group_id as id, group_name as name FROM groups WHERE customer_id = :customer_id and group_active = 1';
                $params = array('customer_id' => $rs_code['customer_id']);
                $groups = DatabaseHandler::GetAll($sql,$params);
            }
            else {  // not code check if gallery
                $sql = 'SELECT gallery_code, gallery_link, gallery_contact_email FROM gallery WHERE gallery_code = :code';
                $rs_gallery = DatabaseHandler::GetRow($sql, $params);
                if (!empty($rs_gallery)) {
                    if (empty($rs_gallery['gallery_contact_email'])){
                        $codeType = 'contact_reg';
                    } else {
                        $galleryURL = $rs_gallery['gallery_link'];
                        $codeType = 'gallery_url';
                    }
                }
                else {
                    $codeType = 'NOT FOUND';
                }
            }

            if ($codeType <> 'NOT FOUND') {
                /*
                * Password was generated by password_hash(), so we need to use
                * password_verify() to check it.
                * 
                * @see http://php.net/manual/en/ref.password.php
                */
                if ($codeType == 'contact_reg' || $codeType == 'gallery_url' || ($rs_code['event_status_id']<=5 && $rs_code['event_active']==1)) {
                    

                    $payload = [                  // Data related to the signer user
                            'code'   => $code, // code validated
                            'galleryURL' => $galleryURL,    // URL for redirect action
                            'groups' => $groups,
                            'codeType' => $codeType,
                            'siblings' => (($rs_code['siblings']==0)?true:false),
                            'friends' => (($rs_code['friends']==0)?true:false)
                    ];
                
                    return $payload;

                } else {
                    if ($rs_code['event_status_id']>5 || $rs_code['event_active']==0) {
                        return ['error'=>['status'=>401,'msg'=>'Code expired']];
                    } else {
                        return ['error'=>['status'=>401,'msg'=>'Invalid Code']];
                    }
                    
                }
            } else {
                return ['error'=>['status'=>404,'msg'=>'Not valid.  Please check the code was entered correctly']];
            }

                
        } else {
            return ['error'=>['status'=>400,'msg'=>'Bad Request (Required fields missing)']];
        }
    }

}