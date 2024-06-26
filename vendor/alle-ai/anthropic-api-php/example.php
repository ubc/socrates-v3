<?php
require_once 'vendor/autoload.php'; // Include the Composer autoloader

$api_key = 'your-anthropic-api-key';

$anthropic_api = new Alle_AI\Anthropic\AnthropicAPI($api_key);
$prompt = "Write a short poem about AI.";
$data = array(
    'prompt' => '\n\nHuman: '.$prompt.'\n\nAssistant:', // Be sure to append these appropriately.
    'model' => 'claude-2',
    'max_tokens_to_sample' => 300,
    'stop_sequences' => array("\n\nHuman:")
  );
$response = $anthropic_api->generateText($data);

echo $response['completion']; // To display only completion


