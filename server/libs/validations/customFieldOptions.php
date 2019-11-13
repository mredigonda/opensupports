<?php

namespace CustomValidations;

use Respect\Validation\Rules\AbstractRule;
use Respect\Validation\Validator as DataValidator;

class CustomFieldOptions extends AbstractRule {

    public function validate($optionsJSON) {
        if(!DataValidator::json()->validate($optionsJSON)) return false;

        $optionsNames = json_decode($optionsJSON);

        if(!is_array($optionsNames) || sizeof($optionsNames) > 20)
            return false;

        foreach($optionsNames as $optionName) {
            if(!is_string($optionName) || sizeof($optionName) > 50)
                return false;
        }

        return true;
    }
}