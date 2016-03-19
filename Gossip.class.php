<?php
/**
 * @package
 * @author     Tobias Zeumer <github@verweisungsform.de>
 * @license    http://opensource.org/licenses/gpl-3.0.html
 * @copyright  Tobias Zeumer <github@verweisungsform.de>
 * @link       https://github.com/tzeumer/Sip2Wrapper
 * @date       2016-03-19
 */

/**
 * Gossip Class
 *
 * Gossip is an SIP2 server implementation (Java) with an extension for enhanced
 * payment options. It's possible to pay
 *  - a single outstanding position; "subtotal-payment"
 *  - an amount being below so total fees (but that sums up to complete
 *    positions being paid); "subtotal-payment"
 *  - an amount being below so total fees (but that might only pays one or more
 *    positions partially); "subtotal-payment + partial-fee-payment"
 *
 * SIP2 already provides everything for the payment part with positions. Example
 *  $transid = time();
 *  $ammount    = '0.10';    // this is what you get doing a
 *                           // msgPatronInformation(feeItems) - FA
 *  $feeId      = '1059648'; // this is what you get doing a
 *                           // msgPatronInformation(feeItems) - FB
 *  $msg = $test->msgFeePaid('01', '00', $ammount, 'EUR', $feeId, $transid);
 *  print_r($test->parseFeePaidResponse( $test->get_message($msg) ));
 *
 * Fee positions can be fetched using a Y a position 7 in a Patron Information
 * request (code 63)
 * It returns these additional fields:
 * FIELD    USED IN (Responses) Description
 * FA       64, 38              Ammount of for fee position (alway dot as decimal seperator)
 * FB       64, 38              ItemId of media for fee position
 * FC       64, 38              Date when fee was generated (alway "dd.MM.yyyy")
 * FD       64, 38              Description/title of fee position
 * FE       64, 38              Cost type of fee position
 * FF       64, 38              Description of cost type of fee position
 * FG       38                  Paid ammount for fee position
 *
 * Note: Sadly no official documentation for Gossip is available online. You
 * can only contact the developer (J. Hofmann) via
 * https://www.gbv.de/Verbundzentrale/serviceangebote/gossip-service-der-vzg
 */
require('Sip2.class.php');
class Gossip extends Sip2 {
    /**
     * Used protocol version (or extension)
     * @var string
     */
    protected $version = 'Gossip';

    /**
     * Generate Patron Information (code 63) request messages in sip2 format
     *
     * @param  string $type  type of information request (none, hold, overdue, charged, fine, recall, unavail, feeItems)
     * @param  string $start value for BP field (default 1)
     * @param  string $end   value for BQ field (default 5)
     * @return string        SIP2 request message
     * @api
     */
    function msgPatronInformation($type, $start = '1', $end = '5') {
        /*
        * According to the specification:
        * Only one category of items should be  requested at a time, i.e. it would take 8 of these messages,
        * each with a different position set to Y, to get all the detailed information about a patron's items.
        */
        $summary['none']     = '       ';
        $summary['hold']     = 'Y      ';
        $summary['overdue']  = ' Y     ';
        $summary['charged']  = '  Y    ';
        $summary['fine']     = '   Y   ';
        $summary['recall']   = '    Y  ';
        $summary['unavail']  = '     Y ';
        $summary['feeItems'] = '      Y';

        /* Request patron information */
        $this->_newMessage('63');
        $this->_addFixedOption($this->language, 3);
        $this->_addFixedOption($this->_datestamp(), 18);
        $this->_addFixedOption(sprintf("%-10s",$summary[$type]), 10);
        $this->_addVarOption('AO',$this->AO);
        $this->_addVarOption('AA',$this->patron);
        $this->_addVarOption('AC',$this->AC, true);
        $this->_addVarOption('AD',$this->patronpwd, true);
        $this->_addVarOption('BP',$start, true); /* old function version used padded 5 digits, not sure why */
        $this->_addVarOption('BQ',$end, true); /* old function version used padded 5 digits, not sure why */
        return $this->_returnMessage();
    }
}