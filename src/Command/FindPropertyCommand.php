<?php

namespace Phabalicious\Command;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Yaml\Yaml;

class FindPropertyCommand extends BaseCommand
{
    protected static $defaultName = 'find:property';

    protected function configure(): void
    {
        parent::configure();
        $this
            ->setName('find:property')
            ->setDescription('Helps the user to find a specific property and shows some details about it')
            ->setHelp('
Provides an interactive way to search for a specific property without knowing
its exact name or location. Just type parts of the property after the prompt,
phab will try to autocomplete your input. If the autocomplete does not reveal
what you are looking for, just hit enter, phab will show a list of possible
candidates, from which you can choose one.

You can limit the search to the current (docker-)host-configuration by prefixing
your input with <info>host.</info> or <info>dockerHost.</info>.

Phab will output the current value of the searched property and from which
resource it was inherited. Please note, if your searched property is a branch
(means it has child values) then the inheritance source does only reflect the
source of the property itself, not necessarily for its enclosed children.


Examples:
<info>phab find:property --config mbb</info>
            ');
    }

    /**
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $context = $this->createContext($input, $output);

        if ($result = parent::execute($input, $output)) {
            return $result;
        }

        $autocompletes = [];
        $flat_nodes = [];

        $sources = [
            '' => $this->configuration->getData(),
            'host.' => $this->getHostConfig()->getData(),
        ];
        if ($this->getDockerConfig()) {
            $sources['dockerHost.'] = $this->getDockerConfig()->getData();
        }
        foreach ($sources as $prefix => $source) {
            foreach ($source->visit() as $data) {
                $full_key = $prefix.implode('.', $data->getStack());
                $autocompletes[] = $full_key;
                $flat_nodes[$full_key] = $data->getValue();
            }
        }

        $question = new Question('Search for');
        $question->setAutocompleterValues($autocompletes);
        do {
            $context->io()->comment('Search for a specific property, prefix your search whith `host.`'.
             ' or `dockerHost.` if you want to limit your search to the current (docker-)host-configuration.');
            $context->io()->comment('To exit leave your answer blank.');
            $key = $context->io()->askQuestion($question);
            if ($key && !isset($flat_nodes[$key])) {
                $possible_choices = [];
                foreach ($autocompletes as $k) {
                    if (false !== strpos($k, $key)) {
                        $possible_choices[] = $k;
                    }
                }
                if (count($possible_choices)) {
                    $choice_question = new ChoiceQuestion(
                        'Could not find exact key, please refine your search:',
                        $possible_choices
                    );
                    $key = $context->io()->askQuestion($choice_question);
                } else {
                    $context->io()->error(sprintf('could not find `%s`!', $key));
                }
            }
            if ($key && isset($flat_nodes[$key])) {
                $keys = explode('.', $key);
                if ('host' == $keys[0]) {
                    array_shift($keys);
                    array_unshift($keys, 'hosts', $this->getHostConfig()->getConfigName());
                } elseif ('dockerHost' == $keys[0]) {
                    array_shift($keys);
                    array_unshift(
                        $keys,
                        'dockerHosts',
                        str_replace('dockerHosts.', '', $this->getDockerConfig()->getConfigName())
                    );
                }

                $last_key = array_pop($keys);
                $context->io()->title('Property info');
                $context->io()->table([], [
                    ['Key', $last_key],
                    ['Value', Yaml::dump($flat_nodes[$key]->getValue(), 4, 2)],
                    ['Ancestors', implode(' > ', $keys)],
                    ['Read from ', $flat_nodes[$key]->getSource()->getSource()],
                ]);
            }
        } while (!empty($key));

        return 0;
    }
}
