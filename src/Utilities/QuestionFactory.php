<?php


namespace Phabalicious\Utilities;

use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Utilities\Questions\QuestionInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class QuestionFactory
{

    protected $factory = [];

    public function __construct()
    {
        $this->register(Questions\Question::class);
        $this->register(Questions\QuestionArray::class);
        $this->register(Questions\Confirm::class);
        $this->register(Questions\Choices::class);
    }

    public function register($class)
    {
        $this->factory[$class::getName()] = $class;
    }

    public function askAndValidate(SymfonyStyle $io, $question_data, $value)
    {

        $type = $question_data['type'] ?? 'question';

        if (!isset($this->factory[$type])) {
            throw new \RuntimeException("Unknown question type: $type");
        }
        /** @var QuestionInterface $question_wrapper */
        $question_wrapper = new $this->factory[$type]();
        $validation = $question_wrapper->setData($question_data);

        if ($validation->getErrorBag()->hasErrors()) {
            throw new ValidationFailedException($validation->getErrorBag());
        }

        if (is_null($value)) {
            if (!empty($question_data['help'])) {
                $io->comment($question_data['help']);
            }
            $value = $question_wrapper->ask($io);
        }

        $question_wrapper->validate($value);

        if (!empty($question_data['transform'])) {
            $transform = strtolower($question_data['transform']);
            $mapping = [
                'lowercase' => 'strtolower',
                'uppercase' => 'strtoupper',
            ];
            if (isset($mapping[$transform])) {
                $value = call_user_func($mapping[$transform], $value);
            }
        }

        return $value;
    }

    public function applyVariables(array $questions, array $variables)
    {
        $result = [];
        $replacements = Utilities::expandVariables($variables);
        foreach ($questions as $key => $question) {
            foreach (['help', 'question'] as $sub_key) {
                if (!empty($question[$sub_key])) {
                    $question[$sub_key] = Utilities::expandString($question[$sub_key], $replacements);
                }
            }
            $result[$key] = $question;
        }
        return $result;
    }

    public function askMultiple(array $questions, $context, $tokens, $alter_value_cb = false)
    {
        foreach ($questions as $key => $question_data) {
            $option_name = strtolower(preg_replace('%([a-z])([A-Z])%', '\1-\2', $key));
            $value = null;
            if (isset($tokens[$key])) {
                $value = $tokens[$key];
            }
            if ($alter_value_cb) {
                $alter_value_cb($key, $value);
            }
            $value = $this->askAndValidate(
                $context->io(),
                $question_data,
                $value
            );

            $tokens[$key] = is_array($value) ? $value : trim($value);
        }
        return $tokens;
    }
}
