<?php
/**
 * The authentication and authorization class.
 */
/**
 *
 */
defined('BASEPATH') OR exit('No direct script access allowed');

require_once('PasswordManager.php');
require_once('AuthExceptions.php');
require_once('User.php');

define('POWERAUTH_VERSION', '1.0.0');

use powerauth\AuthExceptions;
use powerauth\PasswordManager;
use powerauth\User;
use powerorm\exceptions\ObjectDoesNotExist;

/**
 *
 * Responsible for authentication and authentication of users into the system
 *
 * <h3><strong>Auth Signals </strong></h3>
 *
 * - auth.login_success
 * - auth.logout_success
 * - auth.before_logout
 *
 * <h4><strong> Usage:</strong></h4>
 *
 *
 *
 * @package POWERCI
 * @author Eddilbert Macharia (http://eddmash.com) <edd.cowan@gmail.com>
 */
class Auth{


    /**
     * @ignore
     * @var
     */
    protected $_ci;
    /**
     * This will hold the currently logged in user, this is loaded each time this class is loaded.
     * This variable is not meant to hold the current user through sessions
     * because of how codeigniter works with libraries, That is , this class loaded with every request,
     * meaning this variable is reset with every request. thats where session comes into play.
     *
     * Session will hold the user identifier only, and this variable is loaded with each request
     * @var null
     */
    public $user = NULL;

    /**
     * The permissions for the logged in user.
     * @var array
     */
    public $permissions = array();

    /**
     * The roles of the logged in user.
     * @var array
     */
    public $roles = array();

    /**
     * @ignore
     * @var
     */
    protected $model;

    /**
     * Any authentication errors.
     * @var null
     */
    public $errors = null;

    /**
     * @ignore
     * @var array
     */
    private $_error_messages = array(
        'invalid_credentials'=>'Invalid credentials. Please try again.',
 
        'inactive_account' => 'Your account is not active.',
        'old_password_mismatch' => 'The old password is not a match to what we have.',
        'new_password_mismatch' => 'The new password and the repeat password don\'t match.' 
    );


    /**
     * @ignore
     * @throws AuthExceptions
     */
    public function __construct(){
        log_message('INFO', sprintf('************************Library `%s` loaded*****************', get_class($this)));

        // we are passing CodeIgniter instance by reference to CI variable,reason being
        // we don't want to create a copy of the instance but access the already esisting one
        $this->_ci =& get_instance();

        // check if user set model to use
        $auth_model_input = $this->_ci->config->item('auth_model');

        if(!isset($auth_model_input)){
            throw new AuthExceptions(sprintf("Auth Model to be used has not been set in the config file"));
        }

        // load auth model
        $auth_model = strtolower($auth_model_input);
        $this->user_model = new User($auth_model);

        // if user is authenticated hydrate this model
        // remember CI creates all libraries with each request
        $this->is_authenticated();

    }

    /**
     * Determines if user has rights to access the system but does not log the in.
     * useful when authorizing in an enviroment that does not have sessions like mobile api.
     *
     * <h4>Usage</h4>
     *
     * To authorize a user using username and password.
     *
     * <pre><code>$user = $this->auth->authorize($username, $password);</code></pre>
     *
     *
     * @param string $username the username
     * @param string $password the password
     * @param bool|TRUE $user_back if true, returns the authorized user on success,
     * if false returns boolean true on success.
     * @return mixed
     */
    public function authorize($username, $password, $user_back=TRUE){

        try {
            $user = $this->user_model->get(array("username" => $username));
        }catch (ObjectDoesNotExist $e){
            $this->errors = $this->_error_messages['invalid_credentials'];
            return FALSE;
        }


        $password_manger = new PasswordManager($password);

        // ensure user exist
        if(!isset($user->username)):
            $this->errors = $this->_error_messages['invalid_credentials'];
            return FALSE;
        endif;

        // verify the user with that username password matches the one provided
        if(!$password_manger->verify_hash($user->password)):
            $this->errors = $this->_error_messages['invalid_credentials'];
            return FALSE;
        endif;

        // tODO CHECK if passsword needs to rehashed

        // check if user is active
        if (!$user->active)
        {
            $this->errors = $this->_error_messages['inactive_account'];
            return false;
        }

        return $user_back ? $user : true;


    }

    /**
     * Logs the user in and setsup the necessary session data.
     *
     * <h4>Usage</h4>
     *
     * To log in a user using username and password.
     *
     * <pre><code>$user = $this->auth->login($username, $password);</code></pre>
     *
     * @param string $username
     * @param string $password
     * @param bool|FALSE $remember
     * @return bool
     */
    public function login($username, $password, $remember=FALSE){
        $user = $this->authorize($username, $password);
        if(isset($user->username)):

            // Regenerate the session ID to help protect
            // against session fixation
            $this->_ci->session->sess_regenerate();

            // set user object
            $this->_ci->session->set_userdata('logged_in', $user->id);

            // set user
            $this->_setup_user($user);

            // signal
            if(class_exists('Signal')):
                $this->_ci->signal->dispatch('powerauth.auth.login_success', $this);
            endif;

            return TRUE;
        endif;

        return FALSE;

    }

    /**
     * Creates the authoroized user, with there roles and permissions
     * @ignore
     * @param $user
     */
    protected function _setup_user($user){
        $this->user = $user;

        // get roles
        // ensure auth model has get_roles and get_permissions models
        if(!empty($this->user) && method_exists($this->user, 'get_roles')):
            $this->roles = $this->user->get_roles();
        endif;

        // get permissions
        if(!empty($this->user) && method_exists($this->user, 'get_permissions')):
            $this->permissions = $this->user->get_permissions();
        endif;
    }

    /**
     * Tests if the user is authorized to use the system and hydrates the the user, role and permission fields.
     * @return bool
     */
    public function is_authenticated(){


        $user_id = $this->_ci->session->userdata('logged_in');

        /**
         * If user is set in the session user is authorized
         */
        if(empty($user_id)):
            return FALSE;
        endif;

        if(empty($this->user)):
            try {
                $user = $this->user_model->get($user_id);
                $this->_setup_user($user);
            }catch (ObjectDoesNotExist $e){
                return FALSE;
            }
        endif;


        return TRUE;

    }

    /**
     * Clears the session data and regenerated a new session id
     * @ignore
     */
    protected function clear_session_data(){

        // unset the user data
        $this->_ci->session->unset_userdata('user');

        unset($_SESSION['user']);
        session_unset();
        // destroy the whole session

        $this->_ci->session->sess_destroy();

        // Also, regenerate the session ID for a touch of added safety.
        $this->_ci->session->sess_regenerate(true);

    }

    /**
     * Logs the user out
     * @param string $redirect_url url to redirect to on successful logout
     */
    public function logout($redirect_url){
        if(class_exists('Signal')):
            $this->_ci->signal->dispatch('auth.before_logout', $this);
        endif;

        $this->clear_session_data();

        if(class_exists('Signal')):
            $this->_ci->signal->dispatch('auth.logout_success', $this);
        endif;
        redirect($redirect_url);
    }

    /**
     * Ensure that is able to access a controller only if the are logged in,
     *
     * <h4> usage: </h4>
     *
     * call this method inside a controller method to make sure the method is only accessed by logged in users
     * call this method inside a controller constructor to make the whole controller require login
     *
     * <pre><code>$this->auth->require_login();</code></pre>
     *
     * @param string $route the route to redirect to if user is not authenticated
     */
    public function require_login($route=NULL){
        if(!$this->is_authenticated()){
            if($route!=NULL){
                redirect($route);
            }else{
                redirect('login');
            }
        }
    }


    /**
     * Ensure that is able to access a controller only if the are logged in,
     *
     * <h4> usage: </h4>
     *
     * call this method inside a controller method to make sure the method is only accessed by logged in users
     * call this method inside a controller constructor to make the whole controller require login
     *
     *  <pre><code>$this->auth->require_login();</code></pre>
     *
     *
     * @param string $permissions the permission to check for.
     * @param string $route (optional)the route to redirect to if user is not authenticated
     *
     */
    public function require_perm($permissions, $route=NULL){
        if(!$this->has_perm($permissions)):
            if($route!=NULL){
                redirect($route);
            }else{
                redirect('unauthorized-access');
            }
        endif;
    }

    /**
     * Checks if the user has the provide roles,
     * if an array is provide it checks if a user has any one of the roles in the array
     *
     * Returns false if user is not authenticated or does not have the required roles
     *
     *
     * <h4> USAGE: </h4>
     *  To check if user has entrepreneur, investor, admin
     *
     *  <pre><code>$this->auth->has_role(array('entrepreneur', 'investor', 'admin')));</code></pre>
     *
     *  To check for one role
     *
     *  <pre><code>$this->auth->has_role('admin');</code></pre>
     *
     * @param (array|string) $check_roles (array|string)
     * @return bool
     */
    public function has_role($check_roles){
        $status = FALSE;

        // if an authenticated user exists check roles
        if($this->is_authenticated()) {

            // get user roles as an array of role objects
            $user_roles = $this->roles;

            if (is_array($check_roles)):
                foreach ($check_roles as $role) {
                    if (in_array($role, $user_roles)) {
                        $status = TRUE;
                    }
                }
            else:
                $status = in_array($check_roles, $user_roles);
            endif;
        }

        return $status;
    }


    /**
     * Checks if user has the permission specified.
     *
     * <h4> USAGE: </h4>
     *
     *  To check if user has permission, can_edit
     *
     *  <pre><code>$this->auth->has_perm('can_edit');</code></pre>
     *
     * @param string $perm the permission.
     * @return bool
     */
    public function has_perm($perm){
        $status = TRUE;

        if(!$this->is_authenticated()):
            return FALSE;
        endif;

        //if is superuser, always return true
        if($this->user->is_super_user):
            $status = TRUE;
        elseif(array_search($perm, $this->permissions) === FALSE ):
            $status = FALSE;
        endif;

        return $status;
    }

    /**
     * Checks if the user has all the passed in permissions.
     *
     * <h4> USAGE: </h4>
     *
     *  To check if user has permission, can_edit, can_delete
     *
     *  <pre><code>$this->auth->has_perm(['can_edit', 'can_delete']);</code></pre>
     *
     * @param array $perms a list of permissions.
     * @return bool
     * @throws AuthExceptions
     */
    public function has_perms($perms){
        $status = TRUE;
        if(!is_array($perms)){
            throw new AuthExceptions('Expected an array as argument');
        }

        foreach ($perms as $perm) :
            if($this->has_perm($perm)===FALSE){
                // if any of the permission is false then the test should fail
                $status =($status && FALSE);
            }
        endforeach;

        return $status; 
    }
 
    /**
     * Hashes the users plain password. and returns the hashed password
     *
     *
     * <h4> USAGE: </h4>
     *
     * To encode a plain password
     *
     * <pre><code>$ncoded_password this->auth->encode_password('homer');</code></pre>
     *
     * @param string $plain_password plain password to encode.
     * @return mixed
     */
    public function encode_password($plain_password){
        return $this->get_password_manager($plain_password)->_password_hash;
    }

    /**
     * Returns the password manager
     * @ignore
     * @param $password
     * @return PasswordManager
     */
    protected function get_password_manager($password){
        return new PasswordManager($password);
    }

    /**
     * Compare two passwords
     * @param string $password1
     * @param string $password2
     * @return bool True if the passwords are equal
     */
    public function compare_passwords($password1, $password2){
        // compare strictly so that values like 0 is not equal to Null
        return $password1===$password2;
    }

    /**
     * Allows currently authenticated users to change there passwords. and returns the new password hashed.
     *
     * <h4> USAGE: </h4>
     *
     * <pre><code>$pass = $this->auth->password_change($password_old, $password_new, $password_repeat_new);</code></pre>
     *
     * @param string $old_password the users old password.
     * @param string $new_password the new password
     * @param string $new_password_repeat the new password repeated
     * @return bool|mixed returns false if user is not able to change password, or the hash new password if they are able
     */
    public function password_change($old_password, $new_password, $new_password_repeat){

        if($this->is_authenticated()):
            // get stored password in db
            $current_password = $this->user->password;

            if($new_password !== $new_password_repeat):
                $this->errors = $this->_error_messages['new_password_mismatch'];
                return FALSE;
            endif;

            //verify the two
            $manager = new PasswordManager($old_password);

            $status = $manager->verify_hash($current_password);

            if($status):
                // hash the new password
                return $this->encode_password($new_password);
            else:
                $this->errors = $this->_error_messages['old_password_mismatch'];
                return FALSE;
            endif;

        endif;

        return False;
    }
 
}



