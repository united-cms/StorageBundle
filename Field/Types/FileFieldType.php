<?php

namespace UnitedCMS\StorageBundle\Field\Types;

use Symfony\Component\Routing\Router;
use Symfony\Component\Validator\ConstraintViolation;
use UnitedCMS\CoreBundle\Entity\ContentTypeField;
use UnitedCMS\CoreBundle\Entity\SettingTypeField;
use UnitedCMS\CoreBundle\Field\FieldableFieldSettings;
use UnitedCMS\CoreBundle\Field\FieldType;
use UnitedCMS\CoreBundle\SchemaType\SchemaTypeManager;
use UnitedCMS\StorageBundle\Form\StorageFileType;

class FileFieldType extends FieldType
{
    const TYPE                      = "file";
    const FORM_TYPE                 = StorageFileType::class;
    const SETTINGS                  = ['file_types', 'bucket'];
    const REQUIRED_SETTINGS         = ['bucket'];

    private $router;

    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    function getFormOptions(): array
    {
        $url = null;

        if($this->field instanceof ContentTypeField) {
            $url = $this->router->generate('unitedcms_storage_sign_uploadcontenttype', [
              'organization' => $this->field->getEntity()->getDomain()->getOrganization()->getIdentifier(),
              'domain' => $this->field->getEntity()->getDomain()->getIdentifier(),
              'content_type' => $this->field->getEntity()->getIdentifier(),
            ], Router::ABSOLUTE_URL);
        }

        if($this->field instanceof SettingTypeField) {
            $url = $this->router->generate('unitedcms_storage_sign_uploadsettingtype', [
              'organization' => $this->field->getEntity()->getDomain()->getOrganization()->getIdentifier(),
              'domain' => $this->field->getEntity()->getDomain()->getIdentifier(),
              'content_type' => $this->field->getEntity()->getIdentifier(),
            ], Router::ABSOLUTE_URL);
        }

        return array_merge(parent::getFormOptions(), [
          'attr' => [
            'file-types' => $this->field->getSettings()->file_types,
            'field-path' => $this->field->getIdentifier(),
            'upload-sign-url' => $url
          ],
        ]);
    }

    function getGraphQLType(SchemaTypeManager $schemaTypeManager, $nestingLevel = 0) {
        return $schemaTypeManager->getSchemaType('StorageFile');
    }

    function getGraphQLInputType(SchemaTypeManager $schemaTypeManager, $nestingLevel = 0) {
        return $schemaTypeManager->getSchemaType('StorageFileInput');
    }

    function resolveGraphQLData($value)
    {
        if (!$this->fieldIsPresent()) {
            return 'undefined';
        }

        // Create full URL to file.
        $value['url'] = $this->field->getSettings()->bucket['endpoint'] . '/' . $value['id'] . '/' . $value['name'];
        return $value;
    }

    function validateSettings(FieldableFieldSettings $settings): array
    {
        // Validate allowed and required settings.
        $violations = parent::validateSettings($settings);

        // Validate bucket configuration.
        if(empty($violations)) {
            foreach(['endpoint', 'key', 'secret', 'bucket'] as $required_field) {
                if(!isset($settings->bucket[$required_field])) {
                    $violations[] = new ConstraintViolation(
                      'validation.required',
                      'validation.required',
                      [],
                      $settings->bucket,
                      'bucket.' . $required_field,
                      $settings->bucket
                    );
                }
            }
        }

        if(empty($violations)) {
            if(!preg_match("/^(http|https):\/\//", $settings->bucket['endpoint'])) {
                $violations[] = new ConstraintViolation(
                  'validation.absolute_url',
                  'validation.absolute_url',
                  [],
                  $settings->bucket,
                  'bucket.endpoint',
                  $settings->bucket
                );
            }
        }

        return $violations;
    }
}