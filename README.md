ldapOrm
=======

A simple PHP LDAP ORM
---------------------

## Usage


1. Include the single php file `require 'ldapOrm.php';`
2. Create a 'models' directory
3. Create your models
4. Initialize the ldapOrm with the command

```
$ldap = new LdapEntry('yourLdapServer', 'your.domain.name', 'your/model/directory');
```

*LDAP Server is usually localhost, and domain name corresponds to the `dc` attribute in LDAP*

### Model building

There is some settings to do in your model file:

* Name your file in lowercase, with the exact name of your model, e.g `mymodel.php`
* Initialize the model class by calling `class Mymodel extends LdapEntry {...}`

**The model class' first letter name must be uppercased and must correspond to the model filename.**

* The `$options` array contains 3 parameters:
  - dnPattern: contains the key attribute of the DN
  - searchPath: contains the name of the group if exists
  - objectClass: objectClasses of the model

```php
$options = array
           (
              'dnPattern'     => array('cn' => ''),
              'searchPath' 	  => array('ou' => 'mygroupname'),
              'objectClass' 	=> array('myFirstObjectClass', 'mySecondObjectClass')
           );
```

* The `$fields` array contains all the model attributes and its optionnal parameters listed below:
  - **required**:   true|false (default false)
  - **unique**:     true|false (default false) - Set it to true if 
  - **minLength**:  (default 0) - Minimum length of the field value
  - **maxLength**:  (default 1024) - Maximum length of the field value
  - **pattern**:    (default null) - Pattern that the field must match, regex style

```php
$fields = array
(
    'mymailfield' => array
    (       
        'required' => true,
        'unique'   => true,
        'pattern'  => '#^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,6}$#'
    ),
    'myotherfield' => array
    (
        'required' => false,
        'minLength' => 1,
        'maxlength' => 30
    )
);
```

**You can see a full example in the [user.php model](https://github.com/Kloadut/ldapOrm/blob/master/examples/models/user.php)**
