<?php

class User extends LdapEntry 
{
    var $options = array
    (
        'dnPattern'     => array('cn' => ''),
        'searchPath'    => array('ou' => 'users'),
        'objectClass'   => array('inetOrgPerson', 'mailAccount')
    );


    var $fields = array
    (
        'description' => array
        (       
            'required' => false
        ),
        'givenname' => array
        (       
            'required'  => true,
            'minLength' => 1,
            'maxlength' => 30
        ),
        'sn' => array
        (       
            'required'  => true,
            'minLength' => 1,
            'maxlength' => 30
        ),
        'displayname' => array
        (       
            'required'  => true,
            'unique'    => true,
            'minLength' => 2,
            'maxlength' => 62
        ),
        'cn' => array
        (       
            'required'  => true,
            'unique'    => true,
            'minLength' => 2,
            'maxlength' => 62
        ),
        'uid' => array
        (       
            'required'  => true,
            'unique'    => true,
            'minLength' => 2,
            'maxlength' => 30,
            'unique'    => true
        ),
        'userpassword' => array
        (       
            'required' => true
        ),
        'mail' => array
        (       
            'required'  => true,
            'unique'    => true,
            'maxlength' => 100,
            'pattern'   => '#^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,6}$#'
        ),
        'mailalias' => array
        (   
            'required'  => false,
            'maxlength' => 100,
            'pattern'   => '#^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,6}$#'
        )
    );
    
    public function beforeSave() 
    {
        $fullname = $this->givenname.' '.$this->sn;

        $this->cn           = $fullname;
        $this->displayname  = $fullname;
        $this->dnPattern    = array('cn' => $fullname);
    }

    public function afterSave() {}

}
