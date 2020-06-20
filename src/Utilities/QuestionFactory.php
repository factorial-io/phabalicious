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
}
