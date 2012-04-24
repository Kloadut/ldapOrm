<?php

// That's the only file to include
require 'ldapOrm.php';


// Set your LDAP server adress (usually localhost)
$server = 'localhost'; 

// Set the domain that will generate the 'dc' LDAP prefixes
$domain = 'example.org';

// Define your model directory (all the files inside will be automaticaly include as models)
$modelPath = dirname(__FILE__).'/models';


// Initialize LDAP object with above parameters
$ldap = new LdapEntry($server, $domain, $modelPath);


// Assuming you have 'User' model, you can try
$ldap->setUserGivenname('John');
$ldap->setUserSn('Doe');
$ldap->saveUser();

// Other syntaxes
$ldap->user->givenname = 'John';
$ldap->user->sn = 'Doe';
$ldap->save('user');

// Edit your 'User' entry
$ldap->populateUser();
$ldap->setUserGivenname('Bob');
$ldap->saveUser();

// Get some values
echo $ldap->getUserGivenname();
echo $ldap->getUserSn();

// Delete user (DN based)
$ldap->deleteUser('John Doe');


// There's some other tricks that I will document later on :)