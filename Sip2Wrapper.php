<?php

/**
 * @author nathan@nathanjohnson.info
 */

/**
 * Sip2Wrapper
 * 
 * This is a wrapper class for the sip2.class.php from google code
 *
 * 2016-04:
 * Added these methods
 * - getPatronFeeItems()
 * - itemGetInformation()
 * - itemCheckout()
 * - itemCheckin()
 * - itemRenewAll()
 * - itemRenew()
 * - itemStatusUpdate()
 * - feePay()
 * Added for completness but pretty useles
 * - patronBlock()
 * - patronEnable()
 * - hold()
 *
 * Usage:
 *```php
 *     // require the class
 *     require_once 'Sip2Wrapper.php';
 *     
 *     // create the object
 *     $sip2 = new Sip2Wrapper(
 *       array(
 *         'hostname' => $hostname,
 *         'port' => 6001,
 *         'withCrc' => false,
 *         'location' => $location,
 *         'institutionId' => $institutionId
 *       )
 *     );
 *
 *     // login and perform self test
 *     $sip2->login($user, $pass);
 *
 *     // start a patron session and fetch patron status
 *     if ($sip2->startPatronSession($patron, $patronpwd)) {
 *       var_dump($sip2->patronScreenMessages);
 *     }
 *
 *
 * Example creating object with TLS and Gossip (everything else is the same
 * as above):
 *  $sip2 = new Sip2Wrapper(
 *      array(
 *          'hostname' => 'mySipServer.somwhere.net',
 *          'port' => 1290,
 *          'withCrc' => true,
 *          'location' => 'Reading Room 1',
 *          'institutionId' => 'My Library',
 *          'language' => '001',
 *
 *          'socket_tls_enable' => true,
 *          'socket_tls_options' => array(
 *              'peer_name'                 => 'mySipServer.somwhere.net',
 *              'verify_peer'               => true,
 *              'verify_peer_name'          => true,
 *              'allow_self_signed'         => true,
 *              'ciphers'                   => 'HIGH:!SSLv2:!SSLv3',
 *              'capture_peer_cert'         => true,
 *              'capture_peer_cert_chain'   => true,
 *              'disable_compression'       => true
 *          ),
 *
 *          'debug' => true
 *      ), true, 'Gossip'
 *  );
 *```
 */

class Sip2Wrapper {

    /**
     * protected variables, accessible read-only via magic getter method
     * For instance, to get a copy of $_sip2, you can call $obj->sip2
     */

    /**
     * sip2 object
     * @var object
     */
    protected $_sip2 = NULL;

    /**
     * connected state toggle
     * @var boolean
     */
    protected $_connected = false;

    /**
     * self check state toggle
     * @var boolean
     */
    protected $_selfChecked = false;

    /**
     * patron session state toggle
     * @var boolean
     */
    protected $_inPatronSession = false;

    /**
     * patron status
     * @var array
     */
    protected $_patronStatus = NULL;

    /**
     * patron information
     * @var array
     */
    protected $_patronInfo = NULL;

    /**
     * acs status
     * @var array
     */
    protected $_acsStatus = NULL;

    /**
     * @param string $name the member variable name
     * @throws Exception if mathing getter fucntion doesn't exist
     * @return mixed
     */
    public function __get($name) {
        /* look for a getter function named getName */
        $functionName = 'get'.ucfirst($name);
        if (method_exists($this, $functionName)) {
            return call_user_func(array($this, $functionName));
        }
        throw new Exception('Undefined parameter '.$name);
    }

    /**
     * getter function for $this->_sip2
     * @return sip2
     */
    public function getSip2() {
        return $this->_sip2;
    }


    /**
     * @throws Exception if patron session hasn't began
     * @return array the patron status
     */
    public function getPatronStatus() {
        if (!$this->_inPatronSession) {
            throw new Exception('Must start patron session before calling getPatronStatus');
        }
        if ($this->_patronStatus === NULL) {
            $this->fetchPatronStatus();
        }

        return $this->_patronStatus;
    }


    /**
     * parses patron status to determine if login was successful.
     * @return boolean returns true if valid, false otherwise
     */
    public function getPatronIsValid() {
        $patronStatus = $this->getPatronStatus();
        if (strcmp($patronStatus['variable']['BL'][0], 'Y') !== 0 || strcmp($patronStatus['variable']['CQ'][0], 'Y') !== 0) {
            return false;
        }
        return true;
    }

    /**
     * Returns the total fines from patron status call
     * @return number the float value of the fines
     */
    public function getPatronFinesTotal() {
        $status = $this->getPatronStatus();
        if (isset($status['variable']['BV'][0])) {
            return (float)$status['variable']['BV'][0];
        }
        return 0.00;
    }

    /**
     * returns the Screen Messages field of the patron status, which can include
     * for example blocked or barred
     *
     * @return array the screen messages
     */

    public function getPatronScreenMessages() {
        $status = $this->getPatronStatus();
        if (isset($status['variable']['AF']) && is_array($status['variable']['AF'])) {
            return $status['variable']['AF'];
        }
        else {
            return array();
        }
    }

    /**
     * gets the patron info hold items field
     * @return array Hold Items
     */
    public function getPatronHoldItems() {
        $info = $this->fetchPatronInfo('hold');
        if (isset($info['variable']['AS'])) {
            return $info['variable']['AS'];
        }
        return array();
    }

    /**
     * Get the patron info overdue items field
     * @return array overdue items
     */
    public function getPatronOverdueItems() {
        $info = $this->fetchPatronInfo('overdue');
        if (isset($info['variable']['AT'])) {
            return $info['variable']['AT'];
        }
        return array();
    }

    /**
     * get the charged items field
     * @return array charged items
     */

    public function getPatronChargedItems() {
        $info = $this->fetchPatronInfo('charged');
        if (isset($info['variable']['AU'])) {
            return $info['variable']['AU'];
        }
        return array();
    }

    /**
     * return patron fine detail from patron info
     * @return array fines
     */
    public function getPatronFineItems() {
        $info = $this->fetchPatronInfo('fine');
        if (isset($info['variable']['AV'])) {
            return $info['variable']['AV'];
        }
        return array();
    }

    /**
     * return patron recall items from patron info
     * @return array patron items
     */
    public function getPatronRecallItems() {
        $info = $this->fetchPatronInfo('recall');
        if (isset($info['variable']['BU'])) {
            return $info['variable']['BU'];
        }
        return array();
    }


    /**
     * return patron unavailable items from patron info
     * @return array unavailable items
     */

    public function getPatronUnavailableItems() {
        $info = $this->fetchPatronInfo('unavail');
        if (isset($info['variable']['CD'])) {
            return $info['variable']['CD'];
        }
        return array();
    }

    /**
     * worker function to call out to sip2 server and grab patron information.
     * @param string $type One of 'none', 'hold', 'overdue', 'charged', 'fine', 'recall', or 'unavail'
     * @throws Exception if startPatronSession has not been called with success prior to calling this
     * @return array the parsed response from the server
     */
    public function fetchPatronInfo($type = 'none') {
        if (!$this->_inPatronSession) {
            throw new Exception('Must start patron session before calling fetchPatronInfo');
        }
        if (is_array($this->_patronInfo) && isset($this->_patronInfo[$type])) {
            return $this->_patronInfo[$type];
        }
        $msg = $this->_sip2->msgPatronInformation($type);
        $info_response = $this->_sip2->parsePatronInfoResponse($this->_sip2->get_message($msg));
        if ($this->_patronInfo === NULL) {
            $this->_patronInfo = array();
        }
        $this->_patronInfo[$type] = $info_response;
        return $info_response;
    }

    /**
     * getter for acsStatus
     * @return Ambigous <NULL, multitype:string multitype:multitype:  >
     */
    public function getAcsStatus() {
        return $this->_acsStatus;
    }

    /**
     * constructor
     * @param $sip2Params array of key value pairs that will set the corresponding member variables
     * in the underlying sip2 class
     * @param boolean $autoConnect whether or not to automatically connect to the server.  defaults
     * @param string $version   Currently either Sip2 (default) or Gossip
     * to true
     */
    public function __construct($sip2Params = array(), $autoConnect = true, $version = 'Sip2') {
        require_once ($version.'.class.php');

        $sip2 = new $version;
        foreach ($sip2Params as $key => $val) {
            switch($key) {
                case 'institutionId':
                    $key = 'AO';
                    break;
                case 'location':
                    $key = 'scLocation';
                    break;
            }
            if (property_exists($sip2, $key)) {
                $sip2->$key = $val;
            }
        }
        $this->_sip2 = $sip2;
        if ($autoConnect) {
            $this->connect();
        }
    }

    /**
     * Connect to the server
     * @throws Exception if connection fails
     * @return boolean returns true if connection succeeds
     */
    public function connect() {
        $returnVal = $this->_sip2->connect();
        if ($returnVal === true) {
            $this->_connected = true;
        }
        else {
            // Check via debug output or $this->_sip2->log what went wrong
            $this->_connected = false;
            return false;
        }
    }

    /**
     * authenticate with admin credentials to the backend server
     * @param string $bindUser The admin user
     * @param string $bindPass The admin password
     * @param string $autoSelfCheck Whether to call SCStatus after login.  Defaults to true
     * you probably want this.
     * @throws Exception if login failed
     * @return Sip2Wrapper - returns $this if login successful
     */
    public function login($bindUser, $bindPass, $autoSelfCheck=true) {
        $msg = $this->_sip2->msgLogin($bindUser, $bindPass);
        $login = $this->_sip2->parseLoginResponse($this->_sip2->get_message($msg));
        if ((int) $login['fixed']['Ok'] !== 1) {
            throw new Exception('Login failed');
        }
        /* perform self check */
        if ($autoSelfCheck) {
            $this->selfCheck();
        }
        return $this;
    }
    /**
     * Checks the ACS Status to ensure that the ACS is online
     * @throws Exception if ACS is not online
     * @return Sip2Wrapper returns $this if successful
     */
    public function selfCheck() {

        /* execute self test */
        $msg = $this->_sip2->msgSCStatus();
        $status = $this->_sip2->parseACSStatusResponse($this->_sip2->get_message($msg));
        $this->_acsStatus = $status;
        /* check status */
        if (strcmp($status['fixed']['Online'], 'Y') !== 0) {
            throw new Exception('ACS Offline');
        }

        return $this;
    }

    /**
     * This method is required before any get/fetch methods that have Patron in the name.  Upon
     * successful login, it sets the inPatronSession property to true, otherwise false.
     * @param string $patronId Patron login ID
     * @param string $patronPass Patron password
     * @return boolean returns true on successful login, false otherwise
     */
    public function startPatronSession($patronId, $patronPass) {
        if ($this->_inPatronSession) {
            $this->endPatronSession();
        }
        $this->_sip2->patron = $patronId;
        $this->_sip2->patronpwd = $patronPass;

        // set to true before call to getPatronIsValid since it will throw an exception otherwise
        $this->_inPatronSession = true;
        $this->_inPatronSession = $this->getPatronIsValid();
        return $this->_inPatronSession;
    }

    /**
     * method to grab the patron status from the server and store it in _patronStatus
     * @return Sip2Wrapper returns $this
     */
    public function fetchPatronStatus() {
        $msg = $this->_sip2->msgPatronStatusRequest();
        $patron = $this->_sip2->parsePatronStatusResponse($this->_sip2->get_message($msg));
        $this->_patronStatus = $patron;
        return $this;
    }

    /**
     * method to send a patron session to the server
     * @throws Exception if patron session is not properly ended
     * @return Sip2Wrapper returns $this
     */
    public function endPatronSession() {
        $msg = $this->_sip2->msgEndPatronSession();
        $end = $this->_sip2->parseEndSessionResponse($this->_sip2->get_message($msg));
        if (strcmp($end['fixed']['EndSession'], 'Y') !== 0) {
            throw new Exception('Error ending patron session');
        }
        $this->_inPatronSession = false;
        $this->_patronStatus = NULL;
        $this->_patronInfo = NULL;
        return $this;
    }

    /**
     * 2016-04: method to get Item Information (17/18)
     * @return Sip2Wrapper returns $this
     */
    public function itemGetInformation($itemID) {
        $msg = $this->_sip2->msgItemInformation($itemID);
        $info = $this->_sip2->parseItemInfoResponse($this->_sip2->get_message($msg));
        return $info;
    }


    /**
     * 2016-04: Checkout item (changed order of parameters slightly)
     * @param  string $item      value for the variable length required AB field
     * @param  string $nbDateDue optional override for default due date (default '')
     * @param  string $scRenewal value for the renewal portion of the fixed length field (default N)
     * @param  string $itmProp   value for the variable length optional CH field (default '')
     * @param  string $fee       value for the variable length optional BO field (default N)
     * @param  string $noBlock   value for the blocking portion of the fixed length field (default N)
     * @param  string $cancel    value for the variable length optional BI field (default N)
     * @return array             SIP2 checkout response
     */
    public function itemCheckout($itemID, $itmProp ='', $fee='N', $noBlock='N', $nbDateDue ='', $scRenewal='N', $cancel='N') {
        $msg = $this->_sip2->msgCheckout($itemID, $nbDateDue, $scRenewal, $itmProp, $fee, $noBlock, $cancel);
        $info = $this->_sip2->parseCheckoutResponse($this->_sip2->get_message($msg));
        return $info;
    }

    /**
     * 2016-04: Checking item
     * @param  string $item          value for the variable length required AB field
     * @param  string $itmReturnDate value for the return date portion of the fixed length field
     * @param  string $itmLocation   value for the variable length required AP field (default '')
     * @param  string $itmProp       value for the variable length optional CH field (default '')
     * @param  string $noBlock       value for the blocking portion of the fixed length field (default N)
     * @param  string $cancel        value for the variable length optional BI field (default N)
     * @return array                 SIP2 checkin response
     */
    public function itemCheckin($itemID, $itmReturnDate, $itmLocation = '', $itmProp = '', $noBlock='N', $cancel = '') {
        $msg = $this->_sip2->msgCheckin($itemID, $itmReturnDate, $itmLocation = '', $itmProp = '', $noBlock='N', $cancel = '');
        $info = $this->_sip2->parseCheckinResponse($this->_sip2->get_message($msg));
        return $info;
    }


    /**
     * 2016-04: Renew all loaned items
     * @param  string $fee value for the optional variable length BO field
     * @return string      SIP2 request message
     * @return array                 SIP2 checkin response
     */
    public function itemRenewAll($fee = 'N') {
        $msg = $this->_sip2->msgRenewAll($fee);
        $info = $this->_sip2->parseRenewAllResponse($this->_sip2->get_message($msg));
        return $info;
    }


    /**
     * 2016-04: Renew single item  (changed order of parameters slightly)
     * Generate Renew (code 29) request messages in sip2 format
     * @param  string $item       value for the variable length optional AB field
     * @param  string $title      value for the variable length optional AJ field
     * @param  string $nbDateDue  value for the due date portion of the fixed length field
     * @param  string $itmProp    value for the variable length optional CH field
     * @param  string $fee        value for the variable length optional BO field
     * @param  string $noBlock    value for the blocking portion of the fixed length field
     * @param  string $thirdParty value for the party section of the fixed length field
     * @return array                 SIP2 checkin response
     */
    public function itemRenew($itemID = '', $title = '', $itmProp = '', $fee= 'N', $noBlock = 'N', $nbDateDue = '', $thirdParty = 'N') {
        $msg = $this->_sip2->msgRenew($itemID, $title, $nbDateDue, $itmProp, $fee, $noBlock, $thirdParty);
        $info = $this->_sip2->parseRenewResponse($this->_sip2->get_message($msg));
        return $info;
    }


    /**
     * 2016-04: Item Status Update
     * Generate Item Status (code 19) request messages in sip2 format
     * @param  string $item     value for the variable length required AB field
     * @param  string $itmProp  value for the variable length required CH field
     * @return array                 SIP2 checkin response
     */
    public function itemStatusUpdate($itemId, $itmProp = '') {
        $msg = $this->_sip2->msgItemStatus($itemId, $itmProp);
        $info = $this->_sip2->parseItemStatusResponse($this->_sip2->get_message($msg));
        return $info;
    }


   /**
     * 2016-04: Pay fees
     * @param  int    $feeType   value for the fee type portion of the fixed length field
     * @param  int    $pmtType   value for payment type portion of the fixed length field
     * @param  string $pmtAmount value for the payment amount variable length required BV field
     * @param  string $curType   value for the currency type portion of the fixed field
     * @param  string $feeId     value for the fee id variable length optional CG field
     * @param  string $transId   value for the transaction id variable length optional BK field
     * @return array             SIP2 payment response
     */
    public function feePay($feeType, $pmtType, $pmtAmount, $curType = 'USD', $feeId = '', $transId = '') {
        $msg = $this->_sip2->msgFeePaid($feeType, $pmtType, $pmtAmount, $curType, $feeId, $transId);
        $info = $this->_sip2->parseFeePaidResponse($this->_sip2->get_message($msg));
        return $info;
    }


   /**
     * 2016-04: Create, modify, or delete a hold.
     * @param  string $mode         value for the mode portion of the fixed length field
     * @param  string $expDate      value for the optional variable length BW field
     * @param  string $holdtype     value for the optional variable length BY field
     * @param  string $item         value for the optional variable length AB field
     * @param  string $title        value for the optional variable length AJ field
     * @param  string $fee          value for the optional variable length BO field
     * @param  string $pkupLocation value for the optional variable length BS field
     * @return array             SIP2 hold response
     */
    public function hold($mode, $expDate = '', $holdtype = '', $item = '', $title = '', $fee='N', $pkupLocation = '') {
        $msg = $this->_sip2->msgHold($mode, $expDate, $holdtype, $item, $title, $fee, $pkupLocation);
        $info = $this->_sip2->parseHoldResponse($this->_sip2->get_message($msg));
        return $info;
    }


   /**
     * 2016-04: Generate Block Patron (code 11) request messages in sip2 format
     * Note: Even the protocol definition suggests, that this is pretty useless...
     * @param  string $message   message value for the required variable length AL field
     * @param  string $retained  value for the retained portion of the fixed length field (default N)
     * @return array             There is no response, so it's just the message that is returned
     */
    public function patronBlock($message, $retained='N') {
        $msg = $this->_sip2->msgBlockPatron($message, $retained);
        $info = $this->_sip2->get_message($msg);
        return $info;
    }


   /**
     * 2016-04: Generate Patron Enable (code 25) request messages in sip2 format
     * Note: Even the protocol definition suggests, that this is pretty useless...
     * @return string SIP2 request message
     * @return array             SIP2 enable response
     */
    public function patronEnable() {
        $msg = $this->_sip2->msgPatronEnable();
        $info = $this->_sip2->parsePatronEnableResponse($this->_sip2->get_message($msg));
        return $info;
    }


    /**
     * disconnect from the server
     * @return Sip2Wrapper returns $this
     */
    public function disconnect() {
        $this->_sip2->disconnect();
        $this->_connected = false;
        $this->_inPatronSession = false;
        $this->_patronInfo = NULL;
        $this->_acsStatus = NULL;
        return $this;
    }
}
