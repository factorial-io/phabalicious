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

    public function test1PasswordCustomFieldLabels()
    {
        $payload = <<<JSON
{
  "id": "q5xmiish26fmmkafcu5fdk3fse",
  "title": "Foo Bar credentials",
  "version": 1,
  "vault": {
    "id": "n33i6edy47edsntxuj3a7lgiz4",
    "name": "Server-Infrastructure"
  },
  "category": "LOGIN",
  "last_edited_by": "EEL7JCOQEFBLXJHRDGXOCSQAKI",
  "created_at": "2025-10-06T15:08:57Z",
  "updated_at": "2025-10-06T15:08:57Z",
  "additional_information": "—",
  "sections": [
    {
      "id": "add more"
    }
  ],
  "fields": [
    {
      "id": "username",
      "type": "STRING",
      "purpose": "USERNAME",
      "label": "username",
      "value": "testuser",
      "reference": "op://Server-Infrastructure/Foo Bar credentials/username"
    },
    {
      "id": "password",
      "type": "CONCEALED",
      "purpose": "PASSWORD",
      "label": "password",
      "value": "testpass",
      "reference": "op://Server-Infrastructure/Foo Bar credentials/password",
      "password_details": {
        "history": ["bYZsdGPxM3XBCWokhiqMwwAz"]
      }
    },
    {
      "id": "notesPlain",
      "type": "STRING",
      "purpose": "NOTES",
      "label": "notesPlain",
      "value": "Please add all passwords for that instance in that item, to reduce clutter.",
      "reference": "op://Server-Infrastructure/Foo Bar credentials/notesPlain"
    },
    {
      "id": "4qcaxgvbcist7yse6kn5qr6mpu",
      "section": {
        "id": "add more"
      },
      "type": "CONCEALED",
      "label": "mm_access_token",
      "value": "1234",
      "reference": "op://Server-Infrastructure/Foo Bar credentials/add more/mm_access_token"
    },
    {
      "id": "lye3r6wihjq5bevwpm2shjccci",
      "section": {
        "id": "add more"
      },
      "type": "CONCEALED",
      "label": "oauth_client_secret",
      "value": "5678",
      "reference": "op://Server-Infrastructure/Foo Bar credentials/add more/oauth_client_secret"
    },
    {
      "id": "z4sjb7vdeqplu2xabdbb74raxm",
      "section": {
        "id": "add more"
      },
      "type": "CONCEALED",
      "label": "email_pw",
      "value": "9012",
      "reference": "op://Server-Infrastructure/Foo Bar credentials/add more/email_pw"
    },
    {
      "id": "icgamdd2oe73mqjg3o7cf6stpi",
      "section": {
        "id": "add more"
      },
      "type": "CONCEALED",
      "label": "app_allowed_domains",
      "reference": "op://Server-Infrastructure/Foo Bar credentials/add more/app_allowed_domains"
    },
    {
      "id": "y4upoih7zkgwumkxvxwico3mxu",
      "section": {
        "id": "add more"
      },
      "type": "CONCEALED",
      "label": "database",
      "value": "3456",
      "reference": "op://Server-Infrastructure/Foo Bar credentials/add more/database"
    },
    {
      "id": "amrph2cf7xanxvx24zq7d5sxoa",
      "section": {
        "id": "add more"
      },
      "type": "CONCEALED",
      "label": "postgres_string",
      "value": "7890",
      "reference": "op://Server-Infrastructure/Foo Bar credentials/add more/postgres_string"
    }
  ]
}
JSON;

        $mng = new PasswordManager();
        $mng->setContext($this->context);

        // Test matching by label for custom fields in sections
        $this->assertEquals('1234', $mng->extractSecretFrom1PasswordPayload($payload, 2, 'mm_access_token'));
        $this->assertEquals('5678', $mng->extractSecretFrom1PasswordPayload($payload, 2, 'oauth_client_secret'));
        $this->assertEquals('9012', $mng->extractSecretFrom1PasswordPayload($payload, 2, 'email_pw'));
        $this->assertEquals('3456', $mng->extractSecretFrom1PasswordPayload($payload, 2, 'database'));
        $this->assertEquals('7890', $mng->extractSecretFrom1PasswordPayload($payload, 2, 'postgres_string'));
    }

    public function test1PasswordConnectCustomFieldLabels()
    {
        $payload = <<<JSON
{"additionalInformation":"—","category":"LOGIN","createdAt":"2025-10-06T15:08:57Z","fields":[{"id":"username","label":"username","purpose":"USERNAME","type":"STRING","value":"testuser"},{"id":"password","label":"password","passwordDetails":{"history":["bYZsdGPxM3XBCWokhiqMwwAz"]},"purpose":"PASSWORD","type":"CONCEALED","value":"testpass"},{"id":"notesPlain","label":"notesPlain","purpose":"NOTES","type":"STRING","value":"Please add all passwords for that instance in that item, to reduce clutter."},{"id":"4qcaxgvbcist7yse6kn5qr6mpu","label":"mm_access_token","section":{"id":"add more"},"type":"CONCEALED","value":"1234"},{"id":"lye3r6wihjq5bevwpm2shjccci","label":"oauth_client_secret","section":{"id":"add more"},"type":"CONCEALED","value":"5678"},{"id":"z4sjb7vdeqplu2xabdbb74raxm","label":"email_pw","section":{"id":"add more"},"type":"CONCEALED","value":"9012"},{"id":"icgamdd2oe73mqjg3o7cf6stpi","label":"app_allowed_domains","section":{"id":"add more"},"type":"CONCEALED"},{"id":"y4upoih7zkgwumkxvxwico3mxu","label":"database","section":{"id":"add more"},"type":"CONCEALED","value":"3456"},{"id":"amrph2cf7xanxvx24zq7d5sxoa","label":"postgres_string","section":{"id":"add more"},"type":"CONCEALED","value":"7890"}],"id":"q5xmiish26fmmkafcu5fdk3fse","lastEditedBy":"EEL7JCOQEFBLXJHRDGXOCSQAKI","sections":[{"id":"add more"}],"title":"Foo Bar staging credentials","updatedAt":"2025-10-06T15:08:57Z","vault":{"id":"n33i6edy47edsntxuj3a7lgiz4","name":"Server-Infrastructure"},"version":1}
JSON;

        $mng = new PasswordManager();
        $mng->setContext($this->context);

        // Test 1Password Connect format (cli_version = 0 or false)
        // Test default password field extraction
        $this->assertEquals('testpass', $mng->extractSecretFrom1PasswordPayload($payload, 0, 'password'));

        // Test username field by id
        $this->assertEquals('testuser', $mng->extractSecretFrom1PasswordPayload($payload, 0, 'username'));

        // Test custom fields by label
        $this->assertEquals('1234', $mng->extractSecretFrom1PasswordPayload($payload, 0, 'mm_access_token'));
        $this->assertEquals('5678', $mng->extractSecretFrom1PasswordPayload($payload, 0, 'oauth_client_secret'));
        $this->assertEquals('9012', $mng->extractSecretFrom1PasswordPayload($payload, 0, 'email_pw'));
        $this->assertEquals('3456', $mng->extractSecretFrom1PasswordPayload($payload, 0, 'database'));
        $this->assertEquals('7890', $mng->extractSecretFrom1PasswordPayload($payload, 0, 'postgres_string'));

        // Test notes field
        $this->assertEquals('Please add all passwords for that instance in that item, to reduce clutter.', $mng->extractSecretFrom1PasswordPayload($payload, 0, 'notesPlain'));
    }
}
