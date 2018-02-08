<?php
/**
 * Created by PhpStorm.
 * User: franzwilding
 * Date: 08.02.18
 * Time: 09:19
 */

namespace UnitedCMS\StorageBundle\Tests;

use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\HttpKernel\Client;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use UnitedCMS\CoreBundle\Entity\Domain;
use UnitedCMS\CoreBundle\Entity\DomainMember;
use UnitedCMS\CoreBundle\Entity\Organization;
use UnitedCMS\CoreBundle\Entity\OrganizationMember;
use UnitedCMS\CoreBundle\Entity\User;
use UnitedCMS\CoreBundle\Tests\DatabaseAwareTestCase;


class ControllerTest extends DatabaseAwareTestCase {

  /**
   * @var Client $client
   */
  private $client;

  /**
   * @var string
   */
  private $domainConfiguration = '{
    "title": "Domain 1",
    "identifier": "d1", 
    "content_types": [
      {
        "title": "CT 1",
        "identifier": "ct1", 
        "fields": [
            { "title": "File", "identifier": "file", "type": "file" }
        ],
        "permissions": {
          "view content": [ "ROLE_EDITOR" ],
          "list content": [ "ROLE_EDITOR" ],
          "create content": [ "ROLE_EDITOR" ],
          "update content": [ "ROLE_EDITOR" ],
          "delete content": [ "ROLE_EDITOR" ]
        }
      },
      {
        "title": "CT 2",
        "identifier": "ct2", 
        "fields": [
            { "title": "File", "identifier": "file", "type": "file" }
        ],
        "permissions": {
          "view content": [ "ROLE_ADMINISTRATOR" ],
          "list content": [ "ROLE_ADMINISTRATOR" ],
          "create content": [ "ROLE_ADMINISTRATOR" ],
          "update content": [ "ROLE_ADMINISTRATOR" ],
          "delete content": [ "ROLE_ADMINISTRATOR" ]
        }
      }
    ], 
    "setting_types": [
      {
        "title": "ST 1",
        "identifier": "st1", 
        "fields": [
            { "title": "File", "identifier": "file", "type": "file" }
        ],
        "permissions": {
          "view setting": [ "ROLE_EDITOR" ],
          "update setting": [ "ROLE_EDITOR" ]
        }
      },
      {
        "title": "ST 2",
        "identifier": "st2", 
        "fields": [
            { "title": "File", "identifier": "file", "type": "file" }
        ],
        "permissions": {
          "view setting": [ "ROLE_ADMINISTRATOR" ],
          "update setting": [ "ROLE_ADMINISTRATOR" ]
        }
      }
    ]
  }';

  /**
   * @var Organization $org1
   */
  private $org1;

  /**
   * @var Domain $domain1
   */
  private $domain1;

  public function setUp()
  {
    parent::setUp();

    $this->org1 = new Organization();
    $this->org1->setIdentifier('org1')->setTitle('org1');
    $this->em->persist($this->org1);
    $this->em->flush($this->org1);

    $this->domain1 = $this->container->get('united.cms.domain_definition_parser')->parse($this->domainConfiguration);
    $this->domain1->setOrganization($this->org1);
    $this->em->persist($this->domain1);
    $this->em->flush($this->domain1);

    $editor = new User();
    $editor->setEmail('editor@example.com')->setFirstname('Editor')->setLastname('Editor')->setPassword('XXX')->setRoles([User::ROLE_USER]);
    $editorMember = new OrganizationMember();
    $editorMember->setRoles([Organization::ROLE_USER])->setOrganization($this->org1);
    $editorDomainMember = new DomainMember();
    $editorDomainMember->setRoles([Domain::ROLE_EDITOR])->setDomain($this->domain1);
    $editor->addOrganization($editorMember);
    $editor->addDomain($editorDomainMember);
    $this->em->persist($editor);
    $this->em->flush($editor);

    $this->client = $this->container->get('test.client');
    $this->client->followRedirects(false);

    $token = new UsernamePasswordToken($editor, null, 'main', $editor->getRoles());
    $session = $this->client->getContainer()->get('session');
    $session->set('_security_main', serialize($token));
    $session->save();
    $cookie = new Cookie($session->getName(), $session->getId());
    $this->client->getCookieJar()->set($cookie);
  }

  public function testPreSignFileUpload() {

    // Try to access with invalid method.
    $baseUrl = $this->container->get('router')->generate('unitedcms_storage_sign_uploadcontenttype', ['organization' => 'foo', 'domain' => 'baa', 'content_type' => 'foo']);
    $this->client->request('GET', $baseUrl);
    $this->assertEquals(405, $this->client->getResponse()->getStatusCode());
    $this->client->request('PUT', $baseUrl);
    $this->assertEquals(405, $this->client->getResponse()->getStatusCode());
    $this->client->request('DELETE', $baseUrl);
    $this->assertEquals(405, $this->client->getResponse()->getStatusCode());

    $baseUrl = $this->container->get('router')->generate('unitedcms_storage_sign_uploadsettingtype', ['organization' => 'foo', 'domain' => 'baa', 'setting_type' => 'foo']);
    $this->client->request('GET', $baseUrl);
    $this->assertEquals(405, $this->client->getResponse()->getStatusCode());
    $this->client->request('PUT', $baseUrl);
    $this->assertEquals(405, $this->client->getResponse()->getStatusCode());
    $this->client->request('DELETE', $baseUrl);
    $this->assertEquals(405, $this->client->getResponse()->getStatusCode());

    // Try to pre sign for invalid organization domain content type and setting type.
    foreach([
      ['organization' => 'foo', 'domain' => 'baa', 'content_type' => 'foo'],
      ['organization' => $this->org1->getIdentifier(), 'domain' => 'baa', 'content_type' => 'foo'],
      ['organization' => $this->org1->getIdentifier(), 'domain' => $this->domain1->getIdentifier(), 'content_type' => 'foo'],
    ] as $params) {
      $this->client->request('POST', $this->container->get('router')->generate('unitedcms_storage_sign_uploadcontenttype', $params), []);
      $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    foreach([
              ['organization' => 'foo', 'domain' => 'baa', 'setting_type' => 'foo'],
              ['organization' => $this->org1->getIdentifier(), 'domain' => 'baa', 'setting_type' => 'foo'],
              ['organization' => $this->org1->getIdentifier(), 'domain' => $this->domain1->getIdentifier(), 'setting_type' => 'foo'],
            ] as $params) {
      $this->client->request('POST', $this->container->get('router')->generate('unitedcms_storage_sign_uploadsettingtype', $params), []);
      $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    // Try to pre sign without CREATE permission.
    $this->client->request('POST', $this->container->get('router')->generate('unitedcms_storage_sign_uploadcontenttype', [
      'organization' => $this->org1->getIdentifier(),
      'domain' => $this->domain1->getIdentifier(),
      'content_type' => 'ct2',
    ]));
    $this->assertEquals(403, $this->client->getResponse()->getStatusCode());

    $this->client->request('POST', $this->container->get('router')->generate('unitedcms_storage_sign_uploadsettingtype', [
      'organization' => $this->org1->getIdentifier(),
      'domain' => $this->domain1->getIdentifier(),
      'setting_type' => 'st2',
    ]));
    $this->assertEquals(403, $this->client->getResponse()->getStatusCode());

    // Try to pre sign for invalid content type field.

    // Try to pre sign for invalid setting type field.

    // Try to pre sign for invalid content type nested field.

    // Try to pre sign for invalid setting type nested field.

    // Try to pre sign invalid file type.

    // Try to pre sign filename with special chars.

    // Try to pre sign valid file.

  }


}