# powerorm

A Simple to use codeigniter authentication system .


# Install

- Via Composer

`composer require eddmash/powerauth`

- Download or Clone package from github.

# Load the Library

Load the library like any other Codeigniter library.

`$autoload['libraries'] = array('session', 'powerorm/orm', 'powerauth/auth')`


# Configuration

- Set the model to use for Authentication, on the `application/config/config.php`
       
       `$config['auth_model'] = 'User_model';`

     
# Related CODEIGNITER Libraries.

 - powerorm
 
   A light weight easy to use CodeIgniter ORM. https://github.com/eddmash/powerorm
  
 - powerdispatch
 
    An Event Dispatching mechanism for Codeigniter https://github.com/eddmash/powerdispatch