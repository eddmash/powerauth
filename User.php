<?php
namespace powerauth;


class User{
    public $_ci;
    public $model_name;

    public function __construct($model_name){
        $this->_ci=& get_instance();
        $this->model_name = $model_name;

    }

    public function user_model(){
        // load the class
        if(!class_exists($this->model_name, FALSE)):
            $this->_ci->load->model($this->model_name);
            return $this->_ci->{$this->model_name};
        else:
            return  $this->_ci->{$this->model_name};
        endif;

    }

    /**
     * @ignore
     * @param $key
     * @return mixed
     */
    public function __get($key){
        if(!property_exists($this, $key)):

            return $this->user_model()->$key;
        endif;
    }

    /**
     * @ignore
     * @param $method
     * @param $args
     * @return mixed
     */
    public function __call($method, $args){

        if(!method_exists($this, $method)):
            if(empty($args)):
                return call_user_func(array($this->user_model(), $method));
            else:
                if(is_array($args)):
                    return call_user_func_array(array($this->user_model(), $method), $args);
                else:
                    return call_user_func(array($this->user_model(), $method), $args);
                endif;
            endif;
        endif;


    }
}