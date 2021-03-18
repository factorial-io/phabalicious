<?php

namespace Phabalicious\Method;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\RequestOptions;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Scaffolder\CallbackOptions;
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
            $this->handleWebhookResult(
                $context,
                $result,
                $webhook_name,
                sprintf('Could not find webhook `%s` invoked for taskt `%s`', $webhook_name, $task)
            );
        }
    }
    /**
     * Run fallback webhooks.
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
     * Run preflight webhooks.
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
     * Run postflight webhooks.
     *
     * @param string $task
     * @param HostConfig $config
     * @param TaskContextInterface $context
     */
    public function postflightTask(string $task, HostConfig $config, TaskContextInterface $context)
    {
        parent::postflightTask($task, $config, $context);

        // Make sure, that task-specific webhooks get called.
        // Other methods may have called them already, so
        // handledTaskSpecificWebhooks keep track of them.
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

        $variables = Utilities::buildVariablesFrom($config, $context);
        $replacements = Utilities::expandVariables($variables);

        $webhook['url'] = Utilities::expandAndValidateString($webhook['url'], $replacements);

        if (!empty($webhook['payload'])) {
            $payload = $webhook['payload'];
            $payload = Utilities::expandStrings($payload, $replacements);
            $payload = $context->getConfigurationService()->getPasswordManager()->resolveSecrets($payload);

            if ($webhook['method'] == 'get') {
                $webhook['url'] .= '?' . http_build_query($payload);
            } elseif ($webhook['method'] == 'post') {
                $format = $webhook['format'];
                $webhook['options'][$format] = $payload;
            }
        }

        $this->logger->info(sprintf(
            'Invoking webhook at `%s`, method `%s` and format `%s`',
            $webhook['url'],
            $webhook['method'],
            $webhook['format']
        ));
        $this->logger->debug('guzzle options: ' . print_r($webhook['options'], true));

        $client = new Client();
        $response = $client->request(
            $webhook['method'],
            $webhook['url'],
            $webhook['options']
        );

        $this->logger->debug(sprintf(
            'Response status code: %d, body: `%s`',
            $response->getStatusCode(),
            (string) $response->getBody()
        ));

        return $response;
    }


    /**
     * Implements alter hook script callbacks
     *
     * @param CallbackOptions $options
     */
    public function alterScriptCallbacks(CallbackOptions &$options)
    {
        $options->addCallback('webhook', [$this, 'webhookScriptCallback']);
    }

    public function webhookScriptCallback(TaskContextInterface $context, $webhook_name, ...$args)
    {
        $cloned_context = clone $context;
        if (!empty($args)) {
            $named_args = Utilities::parseArguments($args);

            $variables = $cloned_context->get('variables', []);
            $variables['arguments'] = $named_args;
            $cloned_context->set('variables', $variables);
        }
        $host_config = $context->get('host_config');
        $result = $this->runWebhook($webhook_name, $host_config, $cloned_context);
        $this->handleWebhookResult(
            $cloned_context,
            $result,
            $webhook_name,
            sprintf('Could not find webhook `%s`', $webhook_name)
        );
    }

    /**
     * @param TaskContextInterface $context
     * @param Response|bool $result
     * @param string $webhook_name
     * @param string $msg
     */
    protected function handleWebhookResult(TaskContextInterface $context, $result, string $webhook_name, string $msg)
    {
        if (!$result) {
            throw new \InvalidArgumentException($msg);
        } else {
            $result = (string)$result->getBody();
            if (!empty($result)) {
                $context->io()->block($result, $webhook_name);
            }
        }
    }
}
