<?php
use Respect\Validation\Validator as DataValidator;

/**
 * @api {post} /system/add-custom-field Add a custom field
 * @apiVersion 4.5.0
 *
 * @apiName Add Custom field
 *
 * @apiGroup System
 *
 * @apiDescription This path creates a Custom field.
 *
 * @apiPermission staff2
 *
 * @apiParam {Number} name Name of the custom field.
 * @apiParam {String} type One of 'text' and 'select'.
 * @apiParam {String} description Description of the custom field.
 * @apiParam {String} options JSON array of strings with the option names.

 * @apiUse NO_PERMISSION
 * @apiUse INVALID_NAME
 * @apiUse INVALID_CUSTOM_FIELD_TYPE
 * @apiUse INVALID_CUSTOM_FIELD_OPTIONS
 * @apiUse CUSTOM_FIELD_ALREADY_EXISTS
 *
 * @apiSuccess {Object} data Empty object
 *
 */

class AddCustomFieldController extends Controller {
    const PATH = '/add-custom-field';
    const METHOD = 'POST';

    private $type;

    public function validations() {
        return [
            'permission' => 'staff_2',
            'requestData' => [
                'name' => [
                    'validation' => DataValidator::length(2, 100),
                    'error' => ERRORS::INVALID_NAME
                ],
                'type' => [
                    'validation' => DataValidator::oneOf(
                        DataValidator::equals('text'),
                        DataValidator::equals('select')
                    ),
                    'error' => ERRORS::INVALID_CUSTOM_FIELD_TYPE
                ],
                'options' => [
                    'validation' => DataValidator::oneOf(
                        DataValidator::json(),
                        DataValidator::nullType()
                    ),
                    'error' => ERRORS::INVALID_CUSTOM_FIELD_OPTIONS
                ]
            ]
        ];
    }

    public function handler() {
        $name = Controller::request('name');
        $this->type = Controller::request('type');
        $description = Controller::request('description');
        $options = Controller::request('options');
        $optionsList = $this->getOptionsList($options);

        if(!Customfield::getDataStore($name, 'name')->isNull())
            throw new Exception(ERRORS::CUSTOM_FIELD_ALREADY_EXISTS);

        if($this->type === 'select' && $optionsList->isEmpty())
            throw new Exception(ERRORS::INVALID_CUSTOM_FIELD_OPTIONS);

        $customField = new Customfield();
        $customField->setProperties([
            'name' => $name,
            'type' => $this->type,
            'description' => $description,
            'ownCustomfieldoptionList' => $optionsList
        ]);

        $customField->store();

        Response::respondSuccess();
    }

    public function getOptionsList($optionsJSON) {
        $options = new DataStoreList();
        if(!$optionsJSON) return $options;

        if($this->type !== 'select')
            throw new Exception(ERRORS::INVALID_CUSTOM_FIELD_OPTIONS);

        $optionsNames = json_decode($optionsJSON);

        if(!is_array($optionsNames))
            throw new Exception(ERRORS::INVALID_CUSTOM_FIELD_OPTIONS);

        foreach($optionsNames as $optionName) {
            if(!is_string($optionsName))
                throw new Exception(ERRORS::INVALID_CUSTOM_FIELD_OPTIONS);

            $option = new Customfieldoption();
            $option->setProperties([
                'name' => $optionName,
            ]);
            $options->add($option);
        }

        return $options;
    }
}
