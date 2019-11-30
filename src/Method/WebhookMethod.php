<?php

namespace Phabalicious\Method;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Monolog\Handler\Curl\Util;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBagInterface;
use Phabalicious\Validation\ValidationService;

class WebhookMethod extends BaseMethod implements MethodInterface
{

    private $handletaskSpecificWebhooks = [];

    public function getName(): string
    {
        return 'webhook';
    }

    public function supports(string $method_name): bool
    {
        return $method_name == $this->getName();
    }

    public function getGlobalSettings(): array
    {
        $settings = parent::getGlobalSettings();
        $settings['webhooks'] = [
            'defaults' => [
                'format' => RequestOptions::JSON,
                'options' => [
                    'headers' => [
                        'User-Agent' => 'phabalicious',
                        'Accept'     => 'application/json',
                    ],
                ],
            ]
        ];

        return $settings;
    }


    public function validateGlobalSettings(array $settings, ValidationErrorBagInterface $errors)
    {
        parent::validateGlobalSettings($settings, $errors);
        if (!is_array($settings['webhooks'])) {
            return;
        }
        $defaults = $settings['webhooks']['defaults'] ?? [];

        foreach ($settings['webhooks'] as $name => $webhook) {
            if ($name == 'defaults') {
                continue;
            }

            $webhook = Utilities::mergeData($defaults, $webhook);

            $validation = new ValidationService($webhook, $errors, "Webhook $name");
            $validation->hasKey('url', 'A webhook needs an url');
            $validation->hasKey('method', 'A webhook needs a method');
            $validation->isOneOf('method', ['post', 'get', 'delete']);
            $validation->isOneOf('format', [RequestOptions::JSON, RequestOptions::FORM_PARAMS]);
            $validation->isArray('payload', '`payload need to be an array`');
        }
    }

    /**
     * @param HostConfig $config
     * @param string $task
     * @param TaskContextInterface $context
     */
    public function runTaskSpecificWebhooks(HostConfig $config, string $task, TaskContextInterface $context)
    {
        $mapping = $config->get(
            'webhooks',
            $context->getConfigurationService()->getSetting('webhooks', [])
        );

        if (!empty($mapping[$task])) {
            $webhook_name = $mapping[$task];
            $this->logger->info(sprintf('Invoking webhook `%s` for task `%s`', $webhook_name, $task));
            $result = $this->runWebhook($webhook_name, $config, $context);
            if (!$result) {
                throw new \InvalidArgumentException(sprintf(
                    'Could not find webhook `%s` invoked for taskt `%s`',
                    $webhook_name,
                    $task
                ));
            } else {
                $result = (string) $result->getBody();
                if (!empty($result)) {
                    $context->io()->block($result, $webhook_name);
                }
            }
        }
    }
    /**
     * Run fallback scripts.
     *
     * @param string $task
     * @param HostConfig $config
     * @param TaskContextInterface $context
     */
    public function fallback(string $task, HostConfig $config, TaskContextInterface $context)
    {
        parent::fallback($task, $config, $context);
        $this->runTaskSpecificWebhooks($config, $task, $context);
    }

    /**
     * Run preflight scripts.
     *
     * @param string $task
     * @param HostConfig $config
     * @param TaskContextInterface $context
     */
    public function preflightTask(string $task, HostConfig $config, TaskContextInterface $context)
    {
        parent::preflightTask($task, $config, $context);
        $this->runTaskSpecificWebhooks($config, $task . 'Prepare', $context);
    }

    /**
     * Run postflight scripts.
     *
     * @param string $task
     * @param HostConfig $config
     * @param TaskContextInterface $context
     */
    public function postflightTask(string $task, HostConfig $config, TaskContextInterface $context)
    {
        parent::postflightTask($task, $config, $context);

        // Make sure, that task-specific scripts get called.
        // Other methods may have called them already, so
        // handledTaskSpecificScripts keep track of them.
        if (empty($this->handletaskSpecificWebhooks[$task])) {
            $this->runTaskSpecificWebhooks($config, $task, $context);
        }

        $this->runTaskSpecificWebhooks($config, $task . 'Finished', $context);

        foreach ([$task . 'Prepare', $task, $task . 'Finished'] as $t) {
            unset($this->handletaskSpecificWebhooks[$t]);
        }
    }
    
    public function webhook(HostConfig $host_config, TaskContextInterface $context)
    {
        $webhook_name = $context->get('webhook_name');
        if (!$webhook_name) {
            throw new \InvalidArgumentException('Missing webhook_name in context');
        }

        $result = $this->runWebhook($webhook_name, $host_config, $context);
        $context->setResult('webhook_result', $result ? $result->getBody() : false);
    }

    public function runWebhook($webhook_name, HostConfig $config, TaskContextInterface $context)
    {
        $webhook = $context->getConfigurationService()->getSetting("webhooks.$webhook_name", false);
        if (!$webhook) {
            return false;
        }

        $defaults = $context->getConfigurationService()->getSetting('webhooks.defaults', []);
        $webhook = Utilities::mergeData($defaults, $webhook);
        

        
        if (!empty($webhook['payload'])) {
            $payload = $webhook['payload'];
            $variables = Utilities::buildVariablesFrom($config, $context);
            $replacements = Utilities::expandVariables($variables);
            $payload = Utilities::expandStrings($payload, $replacements);
            if ($webhook['method'] == 'get') {
                $webhook['url'] .= '?' . http_build_query($payload);
            } elseif ($webhook['method'] == 'post') {
                $format = $webhook['format'];
                $webhook['options'][$format] = $payload;
            }
        }
        
        $client = new Client();
        $response = $client->request(
            $webhook['method'],
            $webhook['url'],
            $webhook['options']
        );

        return $response;
    }
}
