
  This is a wrapper class for the sip2.class.php from google code
 
  usage:
 
      require_once 'Sip2Wrapper.php';
      $sip2 = new Sip2Wrapper(
       array(
           'hostname' => $hostname,
           'port' => 6001,
           'withCrc' => false,
           'location' => $location,
           'institutionId' => $institutionId
       )
      );
    
      $sip2->login($user, $pass);
    
      if ($sip2->startPatronSession($patron, $patronpwd)) {
        var_dump($sip2->patronScreenMessages);
      }

All of the methods that are prefixed by the word "get" can also be used as properties,
and all protected properties have appropriately named getter methods so that you can effectively
have read-only access via the magic getter.  For instance, 

while you can't do this:  

    $sip2 = $mySip2Wrapper->_sip2;
    
You can do this:

    $sip2 = $mySip2Wrapper->sip2;
    
The other get methods can be called in this way as well and can be used as virtual properties.

For instance, this works even though there is no property named patronStatus:

    $patronStatus = $sip2->patronStatus;
    
Behind the scenes it calls the getPatronStatus() method and returns the value.