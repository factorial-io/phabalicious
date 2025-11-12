<?php

namespace Phabalicious\Method\Callbacks;

use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Method\WebhookMethod;
use Phabalicious\Scaffolder\Callbacks\CallbackInterface;
use Phabalicious\Utilities\Utilities;
use Phabalicious\Validation\ValidationErrorBag;

class WebHookCallback implements CallbackInterface
{
    protected $method;

    public static function getName(): string
    {
        return 'webhook';
    }

    public static function requires(): string
    {
        return '3.6';
    }

    public function __construct(?WebhookMethod $method = null)
    {
        $this->method = $method;
    }

    protected function getMethod(TaskContextInterface $context): WebhookMethod
    {
        if (!$this->method) {
            $configurationService = $context->getConfigurationService();
            $this->method = new WebhookMethod($configurationService->getLogger());
            $errors = new ValidationErrorBag();
            $config = [
                'webhooks' => $configurationService->getSetting('webhooks', []),
            ];
            $config = Utilities::mergeData($this->method->getGlobalSettings($configurationService)->asArray(), $config);
            $configurationService->setSetting('webhooks', $config['webhooks']);
            $this->method->validateGlobalSettings(
                $configurationService->getRawSettings(),
                $errors
            );
            if ($errors->hasErrors()) {
                throw new ValidationFailedException($errors);
            }
        }

        return $this->method;
    }

    /**
     * @throws ValidationFailedException
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
        $result = $this->getMethod($context)->runWebhook($webhook_name, $host_config, $cloned_context);
        $this->getMethod($context)->handleWebhookResult(
            $cloned_context,
            $result,
            $webhook_name,
            sprintf('Could not find webhook `%s`', $webhook_name)
        );
        $context->mergeResults($cloned_context);
    }
}
