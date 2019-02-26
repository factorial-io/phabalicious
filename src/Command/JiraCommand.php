<?php /** @noinspection PhpRedundantCatchClauseInspection */

namespace Phabalicious\Command;

use JiraRestApi\Configuration\ArrayConfiguration;
use JiraRestApi\Issue\IssueService;
use Phabalicious\Exception\EarlyTaskExitException;
use Phabalicious\Exception\ValidationFailedException;
use Phabalicious\Method\TaskContext;
use Phabalicious\Validation\ValidationErrorBag;
use Phabalicious\Validation\ValidationService;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class JiraCommand extends BaseOptionsCommand
{
    protected function configure()
    {
        parent::configure();
        $this
            ->setName('jira')
            ->setDescription('Shows open jira-tickets for this project')
            ->setHelp('Shows a table of open jira-tickets for this project. ');
    }

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int|null
     * @throws \Phabalicious\Exception\BlueprintTemplateNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotFoundException
     * @throws \Phabalicious\Exception\FabfileNotReadableException
     * @throws \Phabalicious\Exception\MethodNotFoundException
     * @throws \Phabalicious\Exception\MismatchedVersionException
     * @throws \Phabalicious\Exception\MissingDockerHostConfigException
     * @throws \Phabalicious\Exception\ShellProviderNotFoundException
     * @throws \Phabalicious\Exception\TaskNotFoundInMethodException
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->checkAllRequiredOptionsAreNotEmpty($input);
        $this->readConfiguration($input);
        $context = new TaskContext($this, $input, $output);

        $jira_config = $this->configuration->getSetting('jira', []);
        $errors = new ValidationErrorBag();
        $validation = new ValidationService($jira_config, $errors, 'jira');
        $validation->hasKey('host', 'the jira-host');
        $validation->hasKey('user', 'The jira-user');
        $validation->hasKey('pass', 'The password for the jira-user');
        if ($errors->hasErrors()) {
            throw new ValidationFailedException($errors);
        }

        $client = new IssueService(new ArrayConfiguration([
            'jiraHost' => $jira_config['host'],
            'jiraUser' => $jira_config['user'],
            'jiraPassword' => $jira_config['pass']
        ]));

        $jql = sprintf(
            'assignee="%s" and project="%s" AND statusCategory != Done',
            $jira_config['user'],
            $this->configuration->getSetting('jira.projectKey', $this->configuration->getSetting('key'))
        );

        $issues = $client->search($jql);
        $context->io()->title('My open tickets on ' . $this->configuration->getSetting('name'));
        $context->io()->table(
            ['Key', 'Summary', 'Url'],
            array_map(function ($issue) use ($jira_config) {
                return [
                    $issue->key,
                    $issue->fields->summary,
                    sprintf('%s/browse/%s', $jira_config['host'], $issue->key)
                ];
            }, $issues->issues)
        );


        return 0;
    }
}
