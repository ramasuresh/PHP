<?php
/**
 * @file
 * iATS API PHP wrapper.
 */

/*! \mainpage iATS API PHP wrapper
 *
 * \section intro_sec Introduction
 *
 * iATS API PHP wrapper documentation.
 *
 */

namespace iATS;

/**
 * Class Core
 *
 * @package iATS
 *
 * The core class.
 */
class Core {

  /**
   * @var string $na_server
   *   North America server url.
   * @var string $uk_server
   *   UK server url.
   */
  private $na_server = 'https://www.iatspayments.com';
  private $uk_server = 'https://www.uk.iatspayments.com';

  // Protected properties.
  /**
   * @var string $agentcode
   *   iATS account agent code.
   * @var string $password
   *   iATS account password.
   * @var string $server_id
   *   Server identifier.
   *   \see setsServer()
   * @var string $endpoint
   *   Service endpoint
   * @var string $params
   *   Requrest parameters
   */
  protected $agentcode = '';
  protected $password = '';
  protected $server_id = '';
  protected $endpoint = '';
  protected $params = array();

  // Public properties.
  /**
   * @var string $result_name
   *   The result name
   */
  public $result_name = '';
  public $format = '';
  public $restrictedservers = array();

  /**
   * IATS class constructor.
   *
   * @param string $agentcode
   *   iATS account agent code.
   * @param string $password
   *   iATS account password.
   * @param string $server_id
   *   Server ID.
   *   \see setServer()
   */
  public function __construct($agentcode, $password, $server_id = 'NA') {
    $this->agentcode = $agentcode;
    $this->password = $password;
    $this->server_id = $server_id;
  }

  /**
   * Create SoapClient object.
   *
   * @param string $endpoint
   *   Service endpoint
   * @param array $options
   *   SoapClient options
   *  \see http://www.php.net/manual/en/soapclient.soapclient.php
   *
   * @return \SoapClient
   *   Returns IATS SoapClient object
   */
  protected function getSoapClient($endpoint, $options = array('trace' => TRUE)) {
    $this->setServer($this->server_id);
    $wsdl = $this->server . $endpoint;
    return new \SoapClient($wsdl, $options);
  }

  /**
   * Set the server to use based on a server id.
   *
   * @param string $server_id
   *   Server ID
   *
   * @throws \Exception
   */
  private function setServer($server_id) {
    switch ($server_id) {
      case 'NA':
        $this->server = $this->na_server;
        break;

      case 'UK':
        $this->server = $this->uk_server;
        break;

      default:
        throw new \Exception('Invalid Server ID.');
    }
  }

  /**
   * Make web service requests to the iATS API.
   *
   * @param string $method
   *   The name of the method to call.
   * @param array $params
   *   Parameters to pass the API.
   *
   * @return object
   *   XML object or boolean.
   * @throws \SoapFault
   */
  protected function apiCall($method, $params) {
    try {
      $this->params = $params;
      $this->defaultparams();
      $soap = $this->getSoapClient($this->endpoint);
      return $soap->$method($this->params);
    }
    catch (\SoapFault $exception) {
      throw new \SoapFault($exception->faultcode, $exception->faultstring);
    }

  }

  /**
   * Set default paramaters, agent code and password.
   */
  protected function defaultparams() {
    $this->params['agentCode'] = $this->agentcode;
    $this->params['password'] = $this->password;
  }

  /**
   * Check server restrictions.
   *
   * @param string $serverid
   *   Server identifier
   * @param array $restrictedservers
   *   Restricted servers array
   *
   * @return bool
   *   Result of server restricted check
   */
  protected function checkServerRestrictions($serverid, $restrictedservers) {
    if (in_array($serverid, $restrictedservers)) {
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Check Method of Payment (MOP) is available based on server and currency.
   *
   * @param string $serverid
   *   Server identifier
   * @param string $currency
   *   Currency
   * @param string $mop
   *   Method of Payment
   *
   * @return bool
   *   Result of check
   */
  protected function checkMOPCurrencyRestrictions($serverid, $currency, $mop) {
    $matrix = $this->getMOPCurrencyMatrix();
    if (isset($matrix[$serverid][$currency])) {
      $filter_result = array_filter($matrix[$serverid][$currency],
      function ($item) use ($mop) {
        return $item == $mop;
      }
      );
      return empty($filter_result) ? TRUE : FALSE;
    }
    else {
      return TRUE;
    }
  }

  /**
   * MOP Currency matrix.
   *
   * @return array
   *   Array of Server/Currency/MOP
   */
  protected function getMOPCurrencyMatrix() {
    return array(
      'NA' => array(
        'USD' => array(
          'VISA','MC', 'AMX', 'DSC',
          'VISA DEBIT', 'MC DEBIT',
        ),
        'CDN' => array(
          'VISA', 'MC', 'AMX',
          'VISA DEBIT',
        ),
      ),
      'UK' => array(
        'GBP' => array(
          'VISA', 'MC', 'AMX', 'MAESTRO',
          'VISA DEBIT',
        ),
        'EUR'  => array(
          'VISA', 'MC', 'AMX',
          'VISA DEBIT',
        ),
      ),
    );
  }

  /**
   * Create array from XML string.
   *
   * @param string $xmlstring
   *   An XML string to be processed.
   *
   * @return array
   *   Array.
   */
  protected function xml2array($xmlstring) {
    $xml = simplexml_load_string($xmlstring);
    $json = json_encode($xml);
    return json_decode($json, TRUE);
  }

  /**
   * Reject code lookup array.
   *
   * @param int $rejectcode
   *   Reject codes and their meaning.
   *
   * @return mixed
   *   Returns reject code meanin.
   */
  protected function reject($rejectcode) {
    $rejects = array(
      1 => 'Agent code has not been set up on the authorization system. Please call iATS at 1-888-955-5455.',
      2 => 'Unable to process transaction. Verify and re-enter credit card information.',
      3 => 'Invalid Customer Code.',
      4 => 'Incorrect expiration date.',
      5 => 'Invalid transaction. Verify and re-enter credit card information.',
      6 => 'Please have cardholder call the number on the back of the card.',
      7 => 'Lost or stolen card.',
      8 => 'Invalid card status.',
      9 => 'Restricted card status. Usually on corporate cards restricted to specific sales.',
      10 => 'Error. Please verify and re-enter credit card information.',
      11 => 'General decline code. Please have client call the number on the back of credit card',
      12 => 'Incorrect CVV2 or Expiry date',
      14 => 'The card is over the limit.',
      15 => 'General decline code. Please have client call the number on the back of credit card',
      16 => 'Invalid charge card number. Verify and re-enter credit card information.',
      17 => 'Unable to authorize transaction. Authorizer needs more information for approval.',
      18 => 'Card not supported by institution.',
      19 => 'Incorrect CVV2 security code',
      22 => 'Bank timeout. Bank lines may be down or busy. Re-try transaction later.',
      23 => 'System error. Re-try transaction later.',
      24 => 'Charge card expired.',
      25 => 'Capture card. Reported lost or stolen.',
      26 => 'Invalid transaction, invalid expiry date. Please confirm and retry transaction.',
      27 => 'Please have cardholder call the number on the back of the card.',
      32 => 'Invalid charge card number.',
      39 => 'Contact IATS 1-888-955-5455.',
      40 => 'Invalid card number. Card not supported by IATS.',
      41 => 'Invalid Expiry date.',
      42 => 'CVV2 required.',
      43 => 'Incorrect AVS.',
      45 => 'Credit card name blocked. Call iATS at 1-888-955-5455.',
      46 => 'Card tumbling. Call iATS at 1-888-955-5455.',
      47 => 'Name tumbling. Call iATS at 1-888-955-5455.',
      48 => 'IP blocked. Call iATS at 1-888-955-5455.',
      49 => 'Velocity 1 – IP block. Call iATS at 1-888-955-5455.',
      50 => 'Velocity 2 – IP block. Call iATS at 1-888-955-5455.',
      51 => 'Velocity 3 – IP block. Call iATS at 1-888-955-5455.',
      52 => 'Credit card BIN country blocked. Call iATS at 1-888-955-5455.',
      100 => 'DO NOT REPROCESS. Call iATS at 1-888-955-5455.',
    );
    return $rejects[$rejectcode];
  }
}