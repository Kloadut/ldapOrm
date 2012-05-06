<?php

/* This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this file,
 * You can obtain one at http://mozilla.org/MPL/2.0/. */
 
/**
 *  LDAPORM - an LDAP micro ORM.
 * 
 *  For more informations: {@link http://github.com/kloadut/ldaporm}
 *  
 *  @author Alexis Gavoty <alexis@gavoty.fr>
 *  @license MPLv2 http://www.mozilla.org/MPL/2.0/
 *  @package yunohost
 */

# ============================================================================ #
#    LDAP CONNECTION FUNCTIONS COLLECTION                                      #
# ============================================================================ #
class LdapConnection
{

  /**
   * Variables with default values
   *
   * @access public
   */
  protected $connection = null;
  protected $baseDn;
  protected $baseDomain;
  protected $protocolVersion = 3;

  /**
   * Contructor that set the connection to the server and store domain
   *
   * @access public
   * @param string $server 
   * @param string $domain
   */
  public function __construct($server, $domain) 
  {
    $this->connection = ldap_connect($server);
    $this->baseDn = 'dc='.strtr($domain, array('.' => ',dc=')); // foo.example.com -> dc=foo,dc=example,dc=com
    $this->baseDomain = $domain;
  }

  /**
   * Set the private $protocolVersion variable
   *
   * @access public
   * @param int $newProtocolVersion 2 | 3
   */
  public function setProtocolVersion($newProtocolVersion) 
  {
    $this->protocolVersion = $newProtocolVersion;
  }

  /**
   * Bind to LDAP with an account
   *
   * @access public
   * @param string $userDnArray 
   * @param string $password
   * @return boolean
   */
  public function connect($userDnArray, $password) 
  {
    if ( $this->connection ) 
    {
      ldap_set_option($this->connection, LDAP_OPT_PROTOCOL_VERSION, $this->protocolVersion);
      return ldap_bind( $this->connection, $this->arrayToDn($userDnArray).$this->baseDn, $password);
    }
  }

  /**
   * Undind LDAP for disconnecting
   *
   * @access public
   * @return boolean
   */
  public function disconnect()
  {
    return ldap_unbind($this->connection);
  }

  /**
   * Throw or return LDAP error
   *
   * @access protected
   * @param boolean $throw 
   * @return exception|string
   */
  protected function ldapError($throw = true) 
  {
    $error = 'Error: ('. ldap_errno($this->connection) .') '. ldap_error($this->connection);
    if ($throw) throw new Exception($error);
    else return $error;
  }

  /**
   * Ldapify an array of attributes
   *
   * @access protected
   * @param array $array 
   * @return string
   */
  protected function arrayToDn($array, $trailingComma = true) 
  {
    $result = "";
    if (!empty($array)) 
    {
      $i = 0;
      foreach ($array as $prefix => $value) 
      {
        if ($i == 0) $result .= $prefix.'='.$value; 
        else $result .= ','.$prefix.'='.$value;
        $i++;
      }
      if ($trailingComma) $result .= ',';
    }
    return $result;
  }
}

# ============================================================================ #
#    LDAP ENTRY OPERATION FUNCTIONS                                            #
# ============================================================================ #
class LdapEntry extends LdapConnection
{

  /**
   * Protected variables with default values
   *
   * @access protected
   */
  protected $searchFilter = '(objectClass=*)';
  protected $searchPath = array();
  protected $attributesToFetch = array();
  protected $models = array();
  protected $actualDn;

  /**
   * Contructor that set the connection and import model
   *
   * @access public
   * @param string $server 
   * @param string $domain
   * @param string $modelPath - Absolute path to the model directory
   */
  public function __construct($server, $domain, $modelPath) 
  {
    parent::__construct($server, $domain);
    if ($handle = opendir($modelPath)) // Import models
    {
      while (false !== ($entry = readdir($handle))) 
      {
          if ($entry != '..' && $entry != '.')
          {
            require_once $modelPath.'/'.$entry;

            $entry = substr($entry, 0, strpos($entry, '.php'));
            $classname = ucfirst($entry);

            $this->$entry = new $classname(null, null, null);

            $this->$entry->connection   = $this->connection;
            $this->$entry->baseDn       = $this->baseDn;
            $this->$entry->baseDomain   = $this->baseDomain;

            foreach ($this->$entry->options as $option => $value) 
            {
              $this->$entry->$option = $value;
            }

            foreach ($this->$entry->fields as $attribute => $value) 
            {
              $this->$entry->$attribute = ''; // Erk
              $this->$entry->attributesToFetch[] = $attribute;
            }
          }
      }

      closedir($handle);
    }
  }

  /**
   * Dynamic method builder (PHP is magic...)
   *
   * @access public
   * @param string $method - The undeclared method
   * @param array $arguments - Arguments passed to this undeclared method
   */
  function __call($method, $arguments) 
  {
    // Get
    if ($result = $this->getModelAndAttributeFromMethod('get', $method)) 
      return $this->$result['model']->$result['attr'];
    
    // Set
    if ($result = $this->getModelAndAttributeFromMethod('set', $method))
      $this->$result['model']->$result['attr'] = $arguments[0];
    
    // Save
    if ($model = $this->getModelFromMethod('save', $method))
      $this->save($model);
    
    // Populate
    if ($model = $this->getModelFromMethod('populate', $method))
      $this->populate($model, $arguments[0]);

    // Delete
    if ($model = $this->getModelFromMethod('delete', $method))
    {
      $dn = $this->arrayToDn(array_merge($this->$model->options['dnPattern'], $arguments[0], $this->$model->options['searchPath'])).$this->baseDn;
      return $this->delete($dn);
    }

    // Validate uniqueness
    if ($result = $this->getModelAndAttributeFromMethod('validateUniquenessOf', $method))
      return $this->validateUniquenessOf($result['attr'], $result['model']);
    
    // Validate length
    if ($result = $this->getModelAndAttributeFromMethod('validateLengthOf', $method))
      return $this->validateLengthOf($result['attr'], $result['model'], $arguments[0], $arguments[1]);
    
    // Validate format
    if ($result = $this->getModelAndAttributeFromMethod('validateFormatOf', $method))
      return $this->validateFormatOf($result['attr'], $result['model'], $arguments[0]);


    return false;
    
  }

  /**
   * Local function to get model and attribute from the self-made method
   *
   * @access public
   * @param string $methodName - The undeclared self-made name
   * @param string $method - The full method name
   * @return array|boolean - An array containing model name and attribute name, or false
   */
  public function getModelAndAttributeFromMethod($methodName, $method)
  {
    if (preg_match('#^'.$methodName.'#', $method))
    {
      $substract = strlen($methodName);
      $modelAndAttr = substr($method,$substract,strlen($method)-$substract);
      $modelAndAttr{0} = strtolower($modelAndAttr{0});
      $result['model'] = preg_replace('#[A-Z][a-z0-9]+#', '', $modelAndAttr);
      $result['attr'] = strtolower(preg_replace("#$model#", '', $modelAndAttr));
      return $result;
    } else return false;
  }

  /**
   * Local function to get model from the self-made method
   *
   * @access public
   * @param string $methodName - The self-made method name
   * @param string $method - The full method name
   * @return string|boolean - The model name or false
   */
  public function getModelFromMethod($methodName, $method)
  {
    if (preg_match('#^'.$methodName.'#', $method))
    {
      $substract = strlen($methodName);
      return strtolower(substr($method,$substract,strlen($method)-$substract));
    } else return false;
  }

  /**
   * Set the protected $searchFilter variable
   *
   * @access public
   * @param $string $newSearchFilter
   */
  public function setSearchFilter($newSearchFilter) 
  {
    $this->searchFilter = $newSearchFilter;
  }

  /**
   * Set the protected $searchPath variable
   *
   * @access public
   * @param string $newSearchPath
   */
  public function setSearchPath($newSearchPath)
  {
    $this->searchPath = $newSearchPath;
  }

  /**
   * Set the protected $attributesToFetch variable
   *
   * @access public
   * @param array $newAttributesArray
   */
  public function setAttributesToFetch($newAttributesArray) 
  {
    $this->attributesToFetch = $newAttributesArray;
  }

  /**
   * Populate all entity attributes by finding it with a typical attribute
   *
   * @access public
   * @param string $model - The model name
   * @param array $attributeArray - The attribute key with its value
   */
  public function populate($model, $attributeArray) 
  {
    $result = $this->$model->findOneBy($attributeArray);

    foreach ($result as $attribute => $attrValue) 
    {
      $this->$model->$attribute = $attrValue;
    }
    
    foreach ($this->$model->options['dnPattern'] as $patternKey => $patternValue) {
      if (array_key_exists($patternKey, $result))
        $this->$model->actualDn[$patternKey] = $result[$patternKey];
    }
  }

  /**
   * Get LDAP entries with the default search filter or a pattern
   *
   *  Pattern example : (&(objectclass=person)(cn=Alex*)(!(mail=*example.org)))
   *  -> All the persons with a name beginning with Alex and a mail that does not finish with example.org
   *
   * @access public
   * @param string $pattern - The search pattern (default all objects)
   * @param boolean $toArray
   * @return array|boolean - Filtered array by default, multidimensionnal array, or false
   */
  public function findAll($pattern = null, $toArray = true) 
  {
    if (empty($pattern)) $pattern = $this->searchFilter;

    $result = ldap_search
              (
                  $this->connection, 
                  $this->arrayToDn($this->searchPath).$this->baseDn, 
                  $pattern, 
                  $this->attributesToFetch
              );

    if ($result) 
    {
      if ($toArray) return $this->entriesToArray($result);
      else return ldap_get_entries($this->connection, $result);
    } 
    else return false;
  }

  /**
   * Get the first LDAP entry with the default search filter or a pattern
   *
   * @access public
   * @param string $pattern - The search pattern (default all objects)
   * @param boolean $toArray
   * @return array|boolean - Filtered array by default, multidimensionnal array, or false
   * @see findAll()
   */
  public function first($pattern, $toArray = true) 
  {
    $result = ldap_search
              (
                  $this->connection, 
                  $this->arrayToDn($this->searchPath).$this->baseDn, 
                  $pattern, 
                  $this->attributesToFetch
              );

    if ($result) 
    {
      if ($toArray) return $this->entryToArray($result);
      else return ldap_get_entries($this->connection, $result);
    } 
    else return false;
  }

  /**
   * Get an LDAP entry with an attribute and its value
   *
   * @access public
   * @param array $attributeArray - The attribute key with its value
   * @param boolean $toArray
   * @return array|boolean - Filtered array by default, multidimensionnal array, or false
   */
  public function findOneBy($attributeArray, $toArray = true) 
  {
    $result = ldap_search
              (
                  $this->connection, 
                  $this->arrayToDn($this->searchPath).$this->baseDn, 
                  "(".$this->arrayToDn($attributeArray, false).")", 
                  $this->attributesToFetch
              );

    if ($result) 
    {
      if ($toArray) return $this->entryToArray($result);
      else return ldap_get_entries($this->connection, $result);
    } 
    else return false;
  }

  /**
   * Save defined attributes
   *
   * @access public
   * @param string $model - The model name
   * @return boolean - Fail | Win
   */
  public function save($model) 
  {
    $this->$model->beforeSave();

    if (isset($this->$model->actualDn))
      $objectArray['actualDn'] = $this->arrayToDn(array_merge($this->$model->actualDn, $this->$model->searchPath)).$this->baseDn;

    $objectArray['newRdn'] = $this->arrayToDn($this->$model->dnPattern, false);
    $objectArray['newDn'] = $this->arrayToDn(array_merge($this->$model->dnPattern, $this->$model->searchPath)).$this->baseDn;

    $objectArray['objectClass'] = $this->$model->objectClass;
    foreach ($this->$model->fields as $attribute => $value) {
      $objectArray[$attribute] = $this->$model->$attribute;
    }

    if ($this->validate($model)) 
    {

      if (isset($this->$model->actualDn))  
        return $this->update($objectArray);
      else 
        return $this->create($objectArray);   
           
      $this->$model->afterSave();

      return true;
    }
  }

  public function beforeSave() {} // Void function to implement

  public function afterSave() {} // Void function to implement


  /**
   * Create an LDAP entry
   *
   * @access public
   * @param array $attributesArray
   * @return exception - If it fails to add
   */
  public function create($attributesArray) 
  {
    $newEntry = $this->attributesArrayFilter($attributesArray);

    if (!ldap_add($this->connection, $attributesArray['newDn'], $newEntry))
      return $this->ldapError();
  }

  /**
   * Update an LDAP entry
   *
   * @access public
   * @param array $attributesArray
   * @return exception - If it fails to modify
   */
  public function update($attributesArray) 
  {
    $modEntry = $this->attributesArrayFilter($attributesArray);

    if (ldap_rename($this->connection, $attributesArray['actualDn'], $attributesArray['newRdn'], null, true)) 
    {
      if (!ldap_mod_replace($this->connection, $attributesArray['newDn'], $modEntry)) {
        $this->ldapError();
      }
    }
    else $this->ldapError();
  }

  /**
   * Delete an LDAP entry
   *
   * @access public
   * @param string $dn
   * @return exception - If it fails to delete
   */
  public function delete($dn) 
  {
    if (!ldap_delete($this->connection, $dn))
      $this->ldapError();
  }

  /**
   * Filter $attributesArray to prepare LDAP operations
   *
   * @access protected
   * @param array $attributesArray
   * @return array - The filtered array
   */
  protected function attributesArrayFilter($attributesArray) 
  {
    foreach ($attributesArray as $attr => $value) 
    {
      if ('actualDn' == $attr || 'newRdn' == $attr || 'newDn' == $attr) 
          unset($attributesArray[$attr]);
      elseif (is_array($value)) 
      {
        foreach ($value as $subvalue) 
          $entry[$attr][] = $subvalue;
      } 
      else $entry[$attr] = $value;

      if (empty($value)) 
      {
        unset($entry[$attr]);
      }
    }

    return $entry;
  }

  /**
   * Get a workable array instead of the insane result of ldap_get_entries()
   *
   * @access protected
   * @param array $result - The ldap_search() result 
   * @return array - Pimped array
   */
  protected function entriesToArray($result) 
  {
    $resultArray = array();
    $entry = ldap_first_entry($this->connection, $result);
    while ($entry) 
    {
      $row = array();
      $attr = ldap_first_attribute($this->connection, $entry);
      while ($attr) 
      {
        $val = ldap_get_values_len($this->connection, $entry, $attr);
        if (array_key_exists('count', $val) AND $val['count'] == 1)
          $row[strtolower($attr)] = $val[0];
        else 
        {
          unset($val['count']);
          $row[strtolower($attr)] = $val;
        }

        $attr = ldap_next_attribute($this->connection, $entry);
      }
      if ($row) $resultArray[] = $row;
      $entry = ldap_next_entry($this->connection, $entry);
    }
    return $resultArray;
  }

  /**
   * Get a workable array for a single entry
   *
   * @access protected
   * @param array $result - The ldap_search() result 
   * @return array - Simplified array
   */
  protected function entryToArray($result) 
  {
    $resultArray = array();
    if($entry = ldap_first_entry($this->connection, $result))
    {
      $attr = ldap_first_attribute($this->connection, $entry);
      while ($attr) 
      {
        $val = ldap_get_values_len($this->connection, $entry, $attr);
        if (array_key_exists('count', $val) AND $val['count'] == 1)
          $resultArray[strtolower($attr)] = $val[0];
        else 
        {
          unset($val['count']);
          $resultArray[strtolower($attr)] = $val;
        }


        $attr = ldap_next_attribute($this->connection, $entry);
      }
        
      return $resultArray;
      } else return array();
  }

  /**
   * Validate attributes of a model (from the settings of the model file)
   *
   * @access protected
   * @param string $model - The model name
   * @return boolean
   */
  protected function validate($model) 
  {
    foreach ($this->$model->fields as $attribute => $validationArray) {
      if (isset($validationArray['required']) && $validationArray['required'] == true)
      {
        if (!$this->validatePresenceOf($attribute, $model))
          return false;

        if (isset($validationArray['minLength'])) 
        {
          if (!$this->validateLengthOf($attribute, $model, $validationArray['minLength']))
            return false;
        }
        if (isset($validationArray['maxLength']))
        {
          if (!$this->validateLengthOf($attribute, $model, 0, $validationArray['maxLength']))
            return false;
        }
        if (isset($validationArray['pattern']))
        {
          if (!$this->validateFormatOf($attribute, $model, $validationArray['pattern']))
            return false;
        }
      }
      if (isset($validationArray['unique']) && $validationArray['unique'] == true) 
      {
        if (!$this->validateUniquenessOf($attribute, $model))
          return false;
      }
    }
    return true;
  }

  /**
   * Validate attribute's presence
   *
   * @access protected
   * @param string $attribute
   * @param string $model
   * @return boolean
   */
  protected function validatePresenceOf($attribute, $model) 
  {
    return !empty($this->$model->$attribute);
  }

  /**
   * Validate attribute's uniqueness
   *
   * @access protected
   * @param string $attribute
   * @param string $model
   * @return boolean
   */
  protected function validateUniquenessOf($attribute, $model) 
  {
    $alreadyExists = $this->findOneBy(array($attribute => $this->$model->$attribute));
    if (!empty($alreadyExists))
    {
      $actualCn = $this->$model->cn;
      $existingCn = $alreadyExists['cn'];

      return $existingCn === $actualCn;
    }
    else return true;
  }

  /**
   * Validate attribute's length
   *
   * @access protected
   * @param string $attribute
   * @param string $model
   * @param int $minLength
   * @param int $maxLength
   * @return boolean
   */
  protected function validateLengthOf($attribute, $model, $minLength = 0, $maxLength = 1024) 
  {
    if (is_array($this->$model->$attribute))
    {
      foreach ($this->$model->$attribute as $value) 
      {
        if (strlen($value) <= $maxLength && strlen($value) >= $minLength) continue;
        else return false;
          
      }
      return true;
    }
    else return strlen($this->$model->$attribute) <= $maxLength && strlen($this->$model->$attribute) >= $minLength;
  }

  /**
   * Validate attribute's pattern
   *
   * @access protected
   * @param string $attribute
   * @param string $model
   * @param string $regex
   * @return boolean
   */
  protected function validateFormatOf($attribute, $model, $regex) 
  {
    if (is_array($this->$model->$attribute))
    {
      foreach ($this->$model->$attribute as $value) 
      {
        if (preg_match($regex, $value)) continue;
        else return false;
      }
      return true;
    }
    else return preg_match($regex, $this->$model->$attribute);
  }
  
}

