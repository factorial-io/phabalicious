<?php

namespace Phabalicious\Method\Callbacks;

use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Method\WebhookMethod;
use Phabalicious\Scaffolder\Callbacks\CallbackInterface;
use Phabalicious\Utilities\Utilities;

class WebHookCallback implements CallbackInterface
{

    protected $method;

    public static function getName(): string
    {
        return 'webhook';
    }

    public static function requires(): string
    {
        return "3.6";
    }

    public function __construct(WebhookMethod $method)
    {
        $this->method = $method;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function handle(TaskContextInterface $context, ...$args)
    {
        $webhook_name = array_shift($args);
        $cloned_context = clone $context;
        if (!empty($args)) {
            $named_args = Utilities::parseArguments($args);

            $variables = $cloned_context->get('variables', []);
            $variables['arguments'] = $named_args;
            $cloned_context->set('variables', $variables);
        }
        $host_config = $context->get('host_config');
        $result = $this->method->runWebhook($webhook_name, $host_config, $cloned_context);
        $this->method->handleWebhookResult(
            $cloned_context,
            $result,
            $webhook_name,
            sprintf('Could not find webhook `%s`', $webhook_name)
        );
    }
}
