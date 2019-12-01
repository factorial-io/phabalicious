<?php

namespace Phabalicious\Method;

use GuzzleHttp\Client;
use Phabalicious\Configuration\ConfigurationService;
use Phabalicious\Configuration\HostConfig;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;
use ThibaudDauce\Mattermost\Attachment;
use ThibaudDauce\Mattermost\Mattermost;
use ThibaudDauce\Mattermost\Message;

class MatterMostNotificationMethod extends BaseNotifyMethod implements MethodInterface, NotifyMethodInterface
{

    public function getGlobalSettings(): array
    {
        $settings = parent::getGlobalSettings();
        $settings['notifications']['mattermost'] = [
            'username' => 'Phabalicious',
        ];

        return $settings;
    }

    /**
     * @param ConfigurationService $configuration_service
     * @param array $host_config
     * @return array
     * @throws ValidationFailedException
     */
    public function getDefaultConfig(ConfigurationService $configuration_service, array $host_config): array
    {
        $config = $configuration_service->getSetting('mattermost', []);
        $errors = new ValidationErrorBag();
        $validation = new ValidationService($config, $errors, 'mattermost');
        $validation->hasKey('webhook', 'Incoming webhook url of the mattermost-server');
        $validation->hasKey('channel', 'Channel to post notifcations into');
        $validation->hasKey('username', 'The username to use as author of the notification');

        if ($errors->hasErrors()) {
            throw new ValidationFailedException($errors);
        }

        $result = parent::getDefaultConfig($configuration_service, $host_config);
        return $result;
    }


    public function getName(): string
    {
        return 'mattermost';
    }

    public function supports(string $method_name): bool
    {
        return $method_name == $this->getName();
    }

    public function sendNotification(
        HostConfig $host_config,
        string $message,
        TaskContextInterface $context,
        string $type,
        array $meta
    ) {
        $channel = $context->get('channel', false);
        if (!$channel) {
            $channel = $context->getConfigurationService()->getSetting('mattermost.channel', false);
        }
        $this->logger->notice(
            sprintf('Sending notification `%s` of type `%s` into channel `%s`', $message, $type, $channel)
        );
        $config = $context->getConfigurationService()->getSetting('mattermost');

        $mattermost = new Mattermost(new Client());

        $mmm = (new Message())
            ->text($message)
            ->channel($channel)
            ->username($config['username'] . ' (' . get_current_user() . ')');

        $keys = ['iconUrl'];

        foreach ($keys as $key) {
            if (!empty($config[$key])) {
                $mmm->{$key}($config[$key]);
            }
        }

        $mmm->attachment(function (Attachment $attachment) use ($message, $type, $host_config, $meta) {
            $attachment->fallback($message);
            if ($type == BaseNotifyMethod::SUCCESS) {
                $attachment->success();
            } elseif ($type == BaseNotifyMethod::ERROR) {
                $attachment->error();
            }
            $attachment
                ->field('Configuration', $host_config['configName'], true)
                ->field('Branch', $host_config['branch'], true);

            /** @var MetaInformation $item */
            foreach ($meta as $item) {
                $item->applyToMatterMostAttachment($attachment);
            }
        });

        $mattermost->send($mmm, $config['webhook']);
    }
}
