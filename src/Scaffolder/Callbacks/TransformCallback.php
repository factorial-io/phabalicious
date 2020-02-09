<?php

namespace Phabalicious\Scaffolder\Callbacks;

use Phabalicious\Method\TaskContextInterface;
use Phabalicious\Scaffolder\Transformers\DataTransformerInterface;

class TransformCallback implements CallbackInterface
{

    protected $transformers = [];


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
        $this->transformers = $transformers;
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
     */
    protected function transform(TaskContextInterface $context, $transformer_key, $files_key, $target_folder)
    {
        $data = $context->get('scaffoldData');
        $tokens = $context->get('tokens');

        $files = $data[$files_key] ?? [];
        /** @var DataTransformerInterface $transformer */
        $transformer = $this->transformers[$transformer_key] ?? false;

        if (empty($files)) {
            throw new \InvalidArgumentException('Could not find key in scaffold file ' . $files_key);
        }
        if (!$transformer) {
            throw new \InvalidArgumentException(sprintf(
                'Unknown transformer %s, available transformers %s,',
                $transformer_key,
                implode(', ', array_keys($this->transformers))
            ));
        }

        $context->io()->comment(sprintf('Transforming %s ...', $files_key));

        $result = $transformer->transform($context, $files);

        $context->io()->progressStart(count($result));
        foreach ($result as $file_name => $file_content) {
            $full_path = $tokens['rootFolder'] . '/' . $target_folder . '/' . $file_name;
            $dir = dirname($full_path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($full_path, $file_content);
            $context->io()->progressAdvance();
        }
        $context->io()->progressFinish();
    }
}
