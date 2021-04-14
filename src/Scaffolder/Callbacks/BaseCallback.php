<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Utilities\Utilities;
use Symfony\Component\Yaml\Yaml;

abstract class BaseCallback implements CallbackInterface
{

    protected function getData(TaskContextInterface $context)
    {
        $data = $context->get('scaffoldData');
        if ($data) {
            return $data;
        }
        return $context->getConfigurationService()->getAllSettings();
    }

    protected function getAbsoluteFilePath(TaskContextInterface $context, $file_name)
    {

        if ($file_name[0] == '/') {
            return $file_name;
        } else {
            // Are we scaffolding?
            $data = $context->get('scaffoldData');
            if ($data) {
                $tokens = $context->get('tokens');
                return $tokens['rootFolder'] . '/' . $file_name;
            } else {
                return $context->getShell()->getHostConfig()['rootFolder'] . '/' . $file_name;
            }
        }
    }

    protected function alterFile(
        TaskContextInterface $context,
        $file_name,
        $data_key,
        callable $read_fn,
        callable $write_fn
    ) {
        $file_path = $this->getAbsoluteFilePath($context, $file_name);
        if (!file_exists($file_path)) {
            $context->io()->warning('Could not find file ' . $file_path);
            return;
        }

        $input = $read_fn($file_path);

        $data = $this->getData($context);
        if ($override = Utilities::getProperty($data, $data_key, false)) {
            $variables = $context->get('tokens', []);
            $replacements = Utilities::expandVariables($variables);
            $override = Utilities::expandStrings($override, $replacements);
            $override = $context->getConfigurationService()->getPasswordManager()->resolveSecrets($override);

            $output = Utilities::mergeData($input, $override);
            $write_fn($file_path, $output);
        } else {
            $context->io()->warning('Could not find override-data ' . $data_key);
        }
    }
}
