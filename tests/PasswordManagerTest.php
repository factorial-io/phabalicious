<?php

namespace Phabalicious\Tests;

use Phabalicious\Command\BaseCommand;
use Phabalicious\Method\TaskContext;
use Phabalicious\Utilities\PasswordManager;
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

        $this->assertEquals('my-very-special-secret', $mng->extractSecretFrom1PasswordPayload($payload, 1, 'password'));
    }

    public function test1PasswordCustomPropName()
    {
        $payload = <<<JSON
{
  "additionalInformation": "bearer",
  "category": "API_CREDENTIAL",
  "createdAt": "2024-09-09T09:19:04Z",
  "fields": [
    {
      "id": "notesPlain",
      "label": "notesPlain",
      "purpose": "NOTES",
      "type": "STRING"
    },
    {
      "id": "username",
      "label": "Benutzername",
      "type": "STRING",
      "value": "MM_ACCESS_TOKEN"
    },
    {
      "id": "credential",
      "label": "Anmeldedaten",
      "type": "CONCEALED",
      "value": "zackbummpeng"
    },
    {
      "id": "type",
      "label": "Typ",
      "type": "MENU",
      "value": "bearer"
    },
    {
      "id": "filename",
      "label": "Dateiname",
      "type": "STRING"
    },
    {
      "id": "validFrom",
      "label": "Gültig ab",
      "type": "DATE"
    },
    {
      "id": "expires",
      "label": "Gültig bis",
      "type": "DATE"
    },
    {
      "id": "hostname",
      "label": "Host-Name",
      "type": "STRING",
      "value": "a.simple.domain.name"
    }
  ],
  "id": "lajdlahdldjh",
  "lastEditedBy": "jjhkjhk",
  "title": "Mattermost DEV: API Admin Access Token",
  "updatedAt": "2024-09-09T09:19:41Z",
  "vault": {
    "id": "lakjdladkj",
    "name": "Some Vault"
  },
  "version": 2
}
JSON;
        $mng = new PasswordManager();
        $mng->setContext($this->context);

        $this->assertEquals('zackbummpeng', $mng->extractSecretFrom1PasswordPayload($payload, 0, 'credential'));
        $this->assertEquals('MM_ACCESS_TOKEN', $mng->extractSecretFrom1PasswordPayload($payload, 0, 'username'));
    }

    public function test1PasswordCliCustomPropName()
    {
        $payload = <<<JSON
{
  "id": "SOME_UUID_WHATEVER",
  "title": "Mattermost DEV: API Admin Access Token",
  "version": 2,
  "vault": {
    "id": "bvjl7wmmyqdw37vkt7ldoixovm",
    "name": "FooBar Name"
  },
  "category": "API_CREDENTIAL",
  "last_edited_by": "GIQ64YLYRZECLEBAEJ6GF25G74",
  "created_at": "2024-09-09T09:19:04Z",
  "updated_at": "2024-09-09T09:19:41Z",
  "additional_information": "bearer",
  "fields": [
    {
      "id": "notesPlain",
      "type": "STRING",
      "purpose": "NOTES",
      "label": "notesPlain",
      "reference": "op://FooBar Name/SOME_UUID_WHATEVER/notesPlain"
    },
    {
      "id": "username",
      "type": "STRING",
      "label": "Benutzername",
      "value": "MM_ACCESS_TOKEN",
      "reference": "op://FooBar Name/SOME_UUID_WHATEVER/Benutzername"
    },
    {
      "id": "credential",
      "type": "CONCEALED",
      "label": "Anmeldedaten",
      "value": "zackbummpeng",
      "reference": "op://FooBar Name/SOME_UUID_WHATEVER/Anmeldedaten"
    },
    {
      "id": "type",
      "type": "MENU",
      "label": "Typ",
      "value": "bearer",
      "reference": "op://FooBar Name/SOME_UUID_WHATEVER/Typ"
    },
    {
      "id": "filename",
      "type": "STRING",
      "label": "Dateiname",
      "reference": "op://FooBar Name/SOME_UUID_WHATEVER/Dateiname"
    },
    {
      "id": "validFrom",
      "type": "DATE",
      "label": "Gültig ab",
      "reference": "op://FooBar Name/SOME_UUID_WHATEVER/validFrom"
    },
    {
      "id": "expires",
      "type": "DATE",
      "label": "Gültig bis",
      "reference": "op://FooBar Name/SOME_UUID_WHATEVER/expires"
    },
    {
      "id": "hostname",
      "type": "STRING",
      "label": "Host-Name",
      "value": "a.simple.domain.name",
      "reference": "op://FooBar Name/SOME_UUID_WHATEVER/Host-Name"
    }
  ]
}
JSON;

        $mng = new PasswordManager();
        $mng->setContext($this->context);

        $this->assertEquals('zackbummpeng', $mng->extractSecretFrom1PasswordPayload($payload, 2, 'credential'));
        $this->assertEquals('MM_ACCESS_TOKEN', $mng->extractSecretFrom1PasswordPayload($payload, 2, 'username'));
    }
}
