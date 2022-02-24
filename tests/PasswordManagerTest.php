<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Configuration\Storage\Node;
use Phabalicious\Configuration\Storage\Store;
use Phabalicious\Exception\ArgumentParsingException;
use Phabalicious\Method\TaskContext;
use Phabalicious\Utilities\PasswordManager;
use Phabalicious\Utilities\Utilities;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class PasswordManagerTest extends PhabTestCase
{

    protected $context;

    public function setup(): void
    {

        $this->context = new TaskContext(
            $this->getMockBuilder(BaseCommand::class)->disableOriginalConstructor()->getMock(),
            $this->getMockBuilder(InputInterface::class)->getMock(),
            $this->getMockBuilder(OutputInterface::class)->getMock()
        );
        $this->context->setIo($this->getMockBuilder(SymfonyStyle::class)->disableOriginalConstructor()->getMock());
    }

    public function test1PasswordPayload()
    {

        $payload = <<<JSON
{"uuid":"jevlzzmoza4cyuab2vfhsgiy64","templateUuid":"102","trashed":"N","createdAt":"2022-02-24T08:56:36Z",
"updatedAt":"2022-02-24T09:33:11Z","changerUuid":"EEL7JCOQEFBLXJHRDGXOCSQAKI","itemVersion":2,"vaultUuid":
"mi7z2c6pn2r6qx7hsvzvhmadpe","details":{"notesPlain":"","passwordHistory":[],"sections":[{"fields":[{"k":
"menu","n":"database_type","t":"type","v":"mysql"},{"inputTraits":{"keyboard":"URL"},"k":"string","n":
"hostname","t":"server","v":"85.215.230.93"},{"inputTraits":{"keyboard":"NumberPad"},"k":"string","n":"port","t":
"port","v":"13306"},{"inputTraits":{"autocapitalization":"none"},"k":"string","n":"database","t":"database",
"v":"dz4_datacenter__prod"},{"inputTraits":{"autocapitalization":"none"},"k":"string","n":"username","t":
"username","v":"dz4-datacenter--prod"},{"k":"concealed","n":"password","t":"password","v":
"my-very-special-secret"},{"k":"string","n":"sid","t":"SID"},{"k":"string","n":"alias","t":"alias"},
{"k":"string","n":"options", "t":"connection options"}],"name":"","title":""},{"name":"linked items","title":
"Verwandte Objekte"}]},"overview":{"ainfo":"85.215.230.93","ps":100,"title":"datacenter__prod"}}
JSON;


        $mng = new PasswordManager();
        $mng->setContext($this->context);

        $this->assertEquals("my-very-special-secret", $mng->extractSecretFrom1PasswordPayload($payload, true));
    }
}
