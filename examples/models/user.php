<?php

class User extends LdapEntry 
{
    protected static $options = array
    (
        'dnPattern'     => array('cn' => ''),
        'searchPath'    => array('ou' => 'users'),
        'objectClass'   => array('inetOrgPerson', 'mailAccount')
    );


    protected static $fields = array
    (
        'description' => array
        (       
            'required' => false
        ),
        'givenname' => array
        (       
            'required' => true,
            'minLength' => 1,
            'maxlength' => 30
        ),
        'sn' => array
        (       
            'required' => true,
            'minLength' => 1,
            'maxlength' => 30
        ),
        'displayname' => array
        (       
            'required' => true,
            'unique'    => true,
            'minLength' => 2,
            'maxlength' => 62
        ),
        'cn' => array
        (       
            'required' => true,
            'unique'    => true,
            'minLength' => 2,
            'maxlength' => 62
        ),
        'uid' => array
        (       
            'required' => true,
            'unique' => true,
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
            'required' => true,
            'unique'    => true,
            'maxlength' => 100,
            'pattern' => '#^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,6}$#'
        ),
        'mailalias' => array
        (   
            'required' => false,
            'maxlength' => 100,
            'pattern' => '#^[\w.-]+@[\w.-]+\.[a-zA-Z]{2,6}$#'
        )
    );
    
    public function beforeSave() 
    {
        $this->cn           = $this->givenname.' '.$this->sn;
        $this->displayname  = $this->givenname.' '.$this->sn;
        $this->dnPattern    = array('cn' => $this->givenname.' '.$this->sn);
    }

    public function afterSave() {}

}