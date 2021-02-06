<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Exception\TransformFailedException;
use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Transformers\DataTransformerInterface;

class TransformCallback implements CallbackInterface
{

    const TRANSFORMER_INPUT_FILENAME = 'transformer_input_file';
    protected static $transformers = [];


    /**
     * @inheritDoc
     */
    public static function getName()
    {
        return 'transform';
    }

    /**
     * @inheritDoc
     */
    public static function requires()
    {
        return '3.4';
    }

    public function setTransformers($transformers)
    {
        foreach ($transformers as $name => $instance) {
            self::$transformers[$name] = $instance;
        }
    }

    /**
     * @inheritDoc
     */
    public function handle(TaskContextInterface $context, ...$arguments)
    {
        $this->transform($context, $arguments[0], $arguments[1], $arguments[2]);
    }

    /**
     * Transform a bunch of files to another bunch of files.
     *
     * @param TaskContextInterface $context
     * @param $transformer_key
     * @param $files_key
     * @param $target_folder
     * @throws TransformFailedException
     */
    protected function transform(TaskContextInterface $context, $transformer_key, $files_key, $target_folder)
    {
        $data = $context->get('scaffoldData');
        $tokens = $context->get('tokens');

        /** @var DataTransformerInterface $transformer */
        $transformer = self::$transformers[$transformer_key] ?? false;

        if (!$transformer) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown transformer %s, available transformers %s,',
                $transformer_key,
                implode(', ', array_keys(self::$transformers))
            ));
        }

        $target_path = $tokens['rootFolder'] . '/' . $target_folder;

        $context->io()->comment(sprintf('Transforming %s ...', $files_key));

        try {
            $result = $transformer->transform($context, [$files_key], $target_path);
        } catch (\Exception $e) {
            if ($file = $context->getResult(self::TRANSFORMER_INPUT_FILENAME)) {
                throw new TransformFailedException($file, $e);
            }
        }

        $context->io()->progressStart(count($result));
        foreach ($result as $file_name => $file_content) {
            $full_path =  $target_path . '/' . $file_name;
            $dir = dirname($full_path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            if (false === file_put_contents($full_path, $file_content)) {
                throw new \RuntimeException(sprintf("Could not write to file `%s`", $full_path));
            }
            $context->io()->progressAdvance();
        }
        $context->io()->progressFinish();
    }
}
