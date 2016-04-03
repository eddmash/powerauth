<?php
namespace powerauth;

defined('BASEPATH') OR exit('No direct script access allowed');


/**
 * Responsible for password management e. hashing
 * Class PasswordManager
 *
 * @package POWERCI
 * @author Eddilbert Macharia (http://eddmash.com) <edd.cowan@gmail.com>
 */
class PasswordManager{

    protected $_plain_password;
    public $_password_hash;

    public function __construct($password){
        $this->_plain_password = $password;
        $this->hash_password();
 
    }

    /**
     * Creates a hash from the provided plain password
     */
    protected function hash_password(){
        $this->_password_hash = password_hash($this->_plain_password, PASSWORD_DEFAULT);
    }

    /**
     * Verifies the provided plain password matches earlier password
     * @param $password
     * @return bool
     */
    public function verify_password($password){
        return password_verify($password, $this->_password_hash);
    }

    /**
     * Verifies the provided hash resulted from the password provided during initialisation
     * @param $hash
     * @return bool
     */
    public function verify_hash($hash){
        return password_verify($this->_plain_password, $hash);

    }


}