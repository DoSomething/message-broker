<?php
/*
 * User campaign signup import script from csv file.
 */

 // Load up the Composer autoload magic
require_once __DIR__ . '/vendor/autoload.php';

// Load configuration settings common to the Message Broker system
// symlinks in the project directory point to the actual location of the files
require __DIR__ . '/mb-secure-config.inc';
require __DIR__ . '/mb-config.inc';

class MBI_ProduceCampaignActivity {
 
 /**
  * AMQPConnection
  */
  public $messageBroker = NULL;
 
  public function __construct($credentials, $config) {
   $this->messageBroker = new MessageBroker($credentials, $config);
  }

  /**
   * Submit user campaign activity to the UserAPI
   *
   * @param array $payload
   *   The contents of the queue entry
   */
  public function produceFromCSV($targetCSVFile) {

    echo '------- campaign-signup-import MBI_ProduceCampaignActivity produceFromCSV START' . date('D M j G:i:s T Y') . ' -------', "\n";

    $signupCount = 0;

    $targetCSVFile = __DIR__ . '/' . $targetCSVFile;
    $file_handle = FALSE;
    $file_handle = fopen($targetCSVFile, "r");

    // Was there a file found
    if ($file_handle != FALSE) {

      while (!feof($file_handle)) {
        $signup = fgets($file_handle);
        $signup = str_replace('"', '', $signup);
        $signup = str_replace("\n", '', $signup);
        $signupData = explode(',', $signup);
        $data = array(
          'activity' => 'campaign_signup',
          'email' => $signupData[1],
          'uid' => $signupData[0],
          'event_id' => $signupData[2],
          'activity_timestamp' => $signupData[3],
          'application_id' => 2,
        );

        $payload = serialize($data);
        $status = $this->messageBroker->publishMessage($payload);
        $signupCount++;

      }
      fclose($file_handle);

    }
    else {
      trigger_error('Invalid file ' . $targetCSVFile, E_USER_WARNING);
      return FALSE;
    }

    echo '------- campaign-signup-import MBI_ProduceCampaignActivity produceFromCSV : ' . $signupCount . ' added/updated... - END' . date('D M j G:i:s T Y') . ' -------', "\n";
  }

}


// Kick off
$targetFile = '';
if (isset($argv[1]) && $argv[1] != '') {
  $targetFile = $argv[1];
}
elseif (isset($_GET['targetFile'])) {
  $targetFile = $_GET['targetFile'];
}

if ($targetFile != '') {

  // Settings
  $credentials = array(
    'host' =>  getenv("RABBITMQ_HOST"),
    'port' => getenv("RABBITMQ_PORT"),
    'username' => getenv("RABBITMQ_USERNAME"),
    'password' => getenv("RABBITMQ_PASSWORD"),
    'vhost' => getenv("RABBITMQ_VHOST"),
  );
  
  $config = array(
    'exchange' => array(
      'name' => getenv("MB_TRANSACTIONAL_EXCHANGE"),
      'type' => getenv("MB_TRANSACTIONAL_EXCHANGE_TYPE"),
      'passive' => getenv("MB_TRANSACTIONAL_EXCHANGE_PASSIVE"),
      'durable' => getenv("MB_TRANSACTIONAL_EXCHANGE_DURABLE"),
      'auto_delete' => getenv("MB_TRANSACTIONAL_EXCHANGE_AUTO_DELETE"),
    ),
    'queue' => array(
      array(
       'name' => getenv("MB_USER_API_CAMPAIGN_ACTIVITY_QUEUE"),
       'passive' => getenv("MB_USER_API_CAMPAIGN_ACTIVITY_QUEUE_PASSIVE"),
       'durable' => getenv("MB_USER_API_CAMPAIGN_ACTIVITY_QUEUE_DURABLE"),
       'exclusive' => getenv("MB_USER_API_CAMPAIGN_ACTIVITY_QUEUE_EXCLUSIVE"),
       'auto_delete' => getenv("MB_USER_API_CAMPAIGN_ACTIVITY_QUEUE_AUTO_DELETE"),
       'bindingKey' => getenv("MB_USER_API_CAMPAIGN_ACTIVITY_QUEUE_TOPIC_MB_TRANSACTIONAL_EXCHANGE_PATTERN"),
      ),
    ),
    'routingKey' => 'campaign.drupal.import',
  );
 
  // Kick off
  $mbi = new MBI_ProduceCampaignActivity($credentials, $config);
  $mbi->produceFromCSV($targetFile);

}
else {
  echo('Target file needs to be provided as a parameter (?targetFile=).' . "\n\n");
}
